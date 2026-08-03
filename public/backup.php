<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
define('DB_HOST', $config['db_host']);
define('DB_USER', $config['db_user']);
define('DB_PASS', $config['db_pass']);
define('DB_NAME', $config['db_name']);
require_once __DIR__ . '/../src/backup_functions.php';
$navActive = 'backup';

ds_require_admin($auth, $dbError);

$currentUser = $auth->currentUser();
$user_id = $currentUser['id'] ?? null;
$email = $currentUser['email'] ?? '';

date_default_timezone_set('Europe/Paris');
$backupDir = __DIR__ . '/../BDD/backups';
$historyFile = $backupDir.'/backup_history.json';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$backupError = null;
$backupFiles = listBackupFiles($backupDir);
$historyEntries = array_reverse(loadBackupHistory($historyFile));

/**
 * Lance mysqldump en sous-processus et renvoie soit le contenu SQL, soit une erreur
 * explicite — jamais d'échec silencieux (exigence 5.3).
 */
function ds_run_mysqldump(array $config): array
{
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'error' => "La fonction PHP proc_open() est désactivée sur ce serveur, impossible de lancer mysqldump."];
    }

    $cmd = [
        'mysqldump',
        '--host=' . $config['db_host'],
        '--port=' . (string) $config['db_port'],
        '--user=' . $config['db_user'],
        '--single-transaction',
        '--skip-lock-tables',
    ];
    if ($config['db_pass'] !== '') {
        $cmd[] = '--password=' . $config['db_pass'];
    }
    $cmd[] = $config['db_name'];

    $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        return ['ok' => false, 'error' => "Impossible de démarrer mysqldump. Vérifiez qu'il est installé et accessible dans le PATH."];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || trim($stdout) === '') {
        return ['ok' => false, 'error' => "mysqldump a échoué (code $exitCode). " . ($stderr !== '' ? trim($stderr) : "Aucune sortie.")];
    }

    return ['ok' => true, 'sql' => $stdout];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$backupError = null;

if ($dbError === null && $action === 'sql') {
    $result = ds_run_mysqldump($config);
    if ($result['ok']) {
        $filename = 'deepshield_backup_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($result['sql']));
        echo $result['sql'];
        exit;
    }
    $backupError = $result['error'];
}

$flash = ds_flash_get();
$tableCounts = [];
if ($dbError === null) {
    foreach (['users', 'videos', 'assistance', 'login_attempts', 'account_deletion_logs'] as $table) {
        $stmt = $pdo->query('SELECT COUNT(*) AS n FROM `' . $table . '`');
        $tableCounts[$table] = (int) $stmt->fetch()['n'];
    }
}

// Téléchargement d'un backup
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $fileName = sanitizeBackupName($_GET['download']);
    $filePath = $backupDir . '/' . $fileName;
    
    if (file_exists($filePath)) {
        addHistoryEntry($historyFile, 'téléchargement', $fileName, 'Téléchargé par ' . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
        log_BDD_backup($user_id, $email, 'téléchargement', $fileName); // LOGS
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $_SESSION['message'] = 'Le fichier demandé n\'existe pas.';
        $_SESSION['message_type'] = 'error';
        header('Location: backup.php');
        exit;
    }
}

// Création d'un backup manuel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $fileName = buildManualBackupFileName();
    $target = $backupDir . '/' . $fileName;
    $tables = 0;
    $rows = 0;
    
    $error = generateDatabaseDump($target, $tables, $rows);
    
    if (!$error) {
        $size = formatBytes(filesize($target));
        addHistoryEntry(
            $historyFile,
            'création manuelle',
            $fileName,
            "Créé par " . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '') . ". Tables: $tables, Lignes: $rows, Taille: $size"
        );
        log_BDD_backup($user_id, $email, 'création manuelle', $fileName, "Tables: $tables, Lignes: $rows, Taille: $size"); // LOGS
        $_SESSION['message'] = 'Backup créé avec succès : ' . $fileName;
        $_SESSION['message_type'] = 'success';
    } else {
        addHistoryEntry(
            $historyFile,
            'erreur création',
            $fileName,
            "Erreur: $error"
        );
        log_BDD_backup_error($user_id, $email, $fileName, $error); // LOGS
        $_SESSION['message'] = 'Erreur lors de la création du backup : ' . $error;
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: backup.php');
    exit;
}

// Suppression d'un backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $fileName = sanitizeBackupName($_POST['file_name'] ?? '');
    $filePath = $backupDir . '/' . $fileName;
    
    if (file_exists($filePath)) {
        $size = formatBytes(filesize($filePath));
        unlink($filePath);
        addHistoryEntry(
            $historyFile,
            'suppression',
            $fileName,
            "Supprimé par " . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '') . " (Taille: $size)"
        );
        log_BDD_backup($user_id, $email, 'suppression', $fileName, "Taille: $size"); // LOGS
        $_SESSION['message'] = 'Backup supprimé avec succès.';
        $_SESSION['message_type'] = 'success';
    } else {
        $error = 'Fichier introuvable';
        log_BDD_backup_error($user_id, $email, $fileName, $error);
        $_SESSION['message'] = 'Le fichier à supprimer n\'existe pas.';
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: backup.php');
    exit;
}

// Restauration d'un backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $fileName = sanitizeBackupName($_POST['file_name'] ?? '');
    $filePath = $backupDir . '/' . $fileName;
    if (!file_exists($filePath)) {
        $_SESSION['message'] = 'Le fichier à restaurer n\'existe pas.';
        $_SESSION['message_type'] = 'error';
        header('Location: backup.php');
        exit;
    }
    
    // Restaurer la base
    $queriesExecuted = 0;
    $error = restoreDatabaseFromSQL($filePath, $queriesExecuted);
    if (!$error) {
        addHistoryEntry(
            $historyFile,
            'restauration',
            $fileName,
            "Restauré par " . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '') . " ($queriesExecuted requêtes exécutées)"
        );
        log_BDD_backup($user_id, $email, 'restauration', $fileName,"$queriesExecuted requêtes exécutées"); // LOGS
        $_SESSION['message'] = "Base de données restaurée avec succès ! ($queriesExecuted requêtes exécutées)";
        $_SESSION['message_type'] = 'success';
    } else {
        addHistoryEntry(
            $historyFile,
            'erreur restauration',
            $fileName,
            "Erreur: $error"
        );
        log_BDD_backup_error($user_id, $email, $fileName, $error); // LOGS
        $_SESSION['message'] = 'Erreur lors de la restauration : ' . $error;
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: backup.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sauvegarde — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/bouclier.ico" type="image/x-icon">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>Sauvegarde de la base de données</h1>
      <p><?= e($config['db_name']) ?></p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

      <?php if ($backupError !== null): ?>
        <div class="notice" style="margin-bottom:18px;border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);"><?= e($backupError) ?></div>
      <?php elseif ($flash): ?>
        <div class="notice" style="margin-bottom:18px;"><?= e($flash['message']) ?></div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="section-block">
      <div class="section-block-head"><h2>Contenu actuel de la base</h2></div>
      <div class="panel">
        <table class="table">
          <thead><tr><th>Table</th><th>Lignes</th></tr></thead>
          <tbody>
            <?php foreach ($tableCounts as $table => $n): ?>
              <tr><td><?= e($table) ?></td><td><?= $n ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <br>
    <form action="backup.php" method="POST" style="margin-top: 15px;">
        <button type="submit" name="create_backup" class="btn-primary">Créer un backup</button>
    </form>
    <br>

    <div class="section-block">
      <div class="section-block-head"><h3>Liste des backups disponibles</h3></div>
      <div class="data-table">
        <table id="backup-table">
          <thead>
            <tr>
              <th>FICHIER</th>
              <th>DATE</th>
              <th>TAILLE</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($backupFiles)): ?>
              <tr>
                <td colspan="4" style="text-align:center; color:#888;">Aucun backup disponible</td>
              </tr>
            <?php else: ?>
              <?php foreach ($backupFiles as $backup): ?>
                <tr>
                  <td><?php echo e($backup['name']); ?></td>
                  <td><?php echo e($backup['created_at']); ?></td>
                  <td><?php echo e($backup['size_label']); ?></td>
                  <td>
                    <a class="btn-details backup-btn-download" style="margin-right:8px;" href="backup.php?download=<?php echo urlencode($backup['name']); ?>">Télécharger</a>                                            
                    <form action="backup.php" method="POST" style="display:inline; margin-right:8px;" onsubmit="return confirm('⚠️ ATTENTION : Importer ce backup va REMPLACER toutes les données actuelles de la base !\n\nÊtes-vous sûr de vouloir continuer ?');">
                      <input type="hidden" name="file_name" value="<?php echo e($backup['name']); ?>">
                      <button type="submit" name="restore_backup" class="btn-primary backup-btn-import">Importer</button>
                    </form>
                    
                    <form action="backup.php" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce backup ?');">
                      <input type="hidden" name="file_name" value="<?php echo e($backup['name']); ?>">
                      <button type="submit" name="delete_backup" class="btn-close backup-btn-delete">Supprimer</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head"><h3>Historique des backups</h3></div>
      <div class="data-table">
        <table id="backup-history-table">
          <thead>
            <tr>
              <th>DATE</th>
              <th>ACTION</th>
              <th>FICHIER</th>
              <th>DÉTAILS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($historyEntries)): ?>
              <tr>
                <td colspan="5" style="text-align:center; color:#888;">Aucun historique disponible</td>
              </tr>
            <?php else: ?>
              <?php foreach ($historyEntries as $entry): ?>
                <tr>
                  <td><?php echo e($entry['timestamp'] ?? 'N/A'); ?></td>
                  <td><?php echo e($entry['action'] ?? 'N/A'); ?></td>
                  <td><?php echo e($entry['file'] ?? 'N/A'); ?></td>
                  <td><?php echo e($entry['details'] ?? 'N/A'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
