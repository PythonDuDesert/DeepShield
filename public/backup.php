<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'backup';

ds_require_admin($auth, $dbError);

/**
 * Lance mysqldump en sous-processus (même approche défensive que
 * AnalysisRunner::run) et renvoie soit le contenu SQL, soit une erreur
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

/** Export JSON de secours, sans dépendre de mysqldump (jamais d'échec silencieux). */
function ds_build_json_export(PDO $pdo): array
{
    $export = ['generated_at' => gmdate('c'), 'tables' => []];
    foreach (['users', 'videos', 'assistance', 'login_attempts', 'account_deletion_logs'] as $table) {
        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $rows = $stmt->fetchAll();
        if ($table === 'users') {
            foreach ($rows as &$row) {
                unset($row['password_hash'], $row['reset_token'], $row['email_token']);
            }
            unset($row);
        }
        $export['tables'][$table] = $rows;
    }
    return $export;
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

if ($dbError === null && $action === 'json') {
    $export = ds_build_json_export($pdo);
    $filename = 'deepshield_export_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$flash = ds_flash_get();
$tableCounts = [];
if ($dbError === null) {
    foreach (['users', 'videos', 'assistance', 'login_attempts', 'account_deletion_logs'] as $table) {
        $stmt = $pdo->query('SELECT COUNT(*) AS n FROM `' . $table . '`');
        $tableCounts[$table] = (int) $stmt->fetch()['n'];
    }
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
      <p>Export ponctuel des données DeepShield (base « <?= e($config['db_name']) ?> »).</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <?php if ($backupError !== null): ?>
      <div class="notice" style="margin-bottom:18px;border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);"><?= e($backupError) ?></div>
    <?php elseif ($flash): ?>
      <div class="notice" style="margin-bottom:18px;"><?= e($flash['message']) ?></div>
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

    <div class="section-block">
      <div class="section-block-head"><h2>Lancer une sauvegarde</h2></div>
      <div class="panel">
        <p class="muted">
          La sauvegarde SQL utilise <code>mysqldump</code> et produit un fichier restaurable tel quel.
          Si <code>mysqldump</code> n'est pas disponible sur ce serveur, utilisez l'export JSON de secours
          (mots de passe et jetons exclus).
        </p>
        <div class="actions">
          <a class="btn-primary" href="backup.php?action=sql">Télécharger la sauvegarde SQL</a>
          <a class="btn-secondary" href="backup.php?action=json">Télécharger l'export JSON</a>
        </div>
        <p class="disclaimer">⚠ Ces fichiers contiennent des données personnelles (noms, e-mails). Conservez-les uniquement sur un support sécurisé.</p>
      </div>
    </div>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
