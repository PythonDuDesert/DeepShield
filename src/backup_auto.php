<?php
/**
 * backup_auto.php — Sauvegarde automatique (cron)
 * =================================================
 * Lancé par le planificateur de tâches Windows.

 * Utilise exactement la même pile que les pages web :
 *   bootstrap.php → config.php → .env
 * Aucune référence à db_config.php (fichier supprimé lors du refactor).
 *
 * Jamais d'échec silencieux (exigence 5.3) : chaque étape est loguée
 * explicitement, et le script se termine avec un code de sortie non-nul
 * en cas d'erreur pour que le scheduler puisse le détecter.
 */

declare(strict_types=1);

// ── Résolution du répertoire racine ──────────────────────────────────────────
// Ce script peut être appelé depuis n'importe quel répertoire courant,
// donc on construit tous les chemins à partir de __DIR__.
$srcDir  = __DIR__;                    // …/DeepShield/src
$rootDir = dirname($srcDir);          // …/DeepShield

require_once $srcDir . '/bootstrap.php';
// Après bootstrap : $config, $pdo (ou null), $dbError sont disponibles.

// ── Répertoire de destination des sauvegardes ────────────────────────────────
$backupDir = $rootDir . '/BDD/backups';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0775, true)) {
        cron_log("ERREUR : impossible de créer le répertoire de sauvegarde « $backupDir ».");
        exit(1);
    }
}

// ── Vérification de la base de données ───────────────────────────────────────
if ($dbError !== null) {
    cron_log("ERREUR : connexion à la base de données impossible. Détail : $dbError");
    exit(1);
}

// ── Vérification de l'âge de la dernière sauvegarde ──────────────────────────
// Le planificateur exécute ce script chaque minute, mais une nouvelle
// sauvegarde n'est créée que si la précédente a au moins une heure.

$backups = array_merge(
    glob($backupDir . '/deepshield_auto_*.sql') ?: [],
    glob($backupDir . '/deepshield_auto_*.json') ?: []
);

if ($backups !== []) {
    usort($backups, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $lastBackup = $backups[0];
    $age = time() - filemtime($lastBackup);

    if ($age < 3600) {
        $remaining = 3600 - $age;

        cron_log(sprintf(
            "Sauvegarde ignorée : dernière sauvegarde il y a %d min (%s). Prochaine dans %d min.",
            intdiv($age, 60),
            basename($lastBackup),
            intdiv($remaining, 60)
        ));

        exit(0);
    }
}

// ── Lancement de la sauvegarde ────────────────────────────────────────────────
cron_log("Démarrage de la sauvegarde automatique (base « {$config['db_name']} »)…");

$result = run_mysqldump($config);

if ($result['ok']) {
    $filename = $backupDir . '/deepshield_auto_' . date('Ymd_His') . '.sql';
    $written  = file_put_contents($filename, $result['sql']);

    if ($written === false) {
        cron_log("ERREUR : mysqldump a réussi mais l'écriture du fichier « $filename » a échoué.");
        exit(1);
    }

    $kb = round($written / 1024, 1);
    cron_log("Sauvegarde SQL écrite : $filename ({$kb} Ko).");

    // Rotation : on ne garde que les 30 dernières sauvegardes automatiques.
    rotate_backups($backupDir, 'deepshield_auto_*.sql', 30);
    cron_log("Rotation terminée (max 30 fichiers conservés).");
    exit(0);
}

// mysqldump a échoué : on tente le JSON de secours.
cron_log("AVERTISSEMENT : mysqldump a échoué — {$result['error']}");
cron_log("Tentative d'export JSON de secours…");

try {
    $export   = build_json_export($pdo);
    $filename = $backupDir . '/deepshield_auto_' . date('Ymd_His') . '.json';
    $written  = file_put_contents($filename, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if ($written === false) {
        cron_log("ERREUR : impossible d'écrire le fichier JSON « $filename ».");
        exit(1);
    }

    $kb = round($written / 1024, 1);
    cron_log("Export JSON de secours écrit : $filename ({$kb} Ko).");
    rotate_backups($backupDir, 'deepshield_auto_*.json', 30);
    exit(0);
} catch (Throwable $e) {
    cron_log("ERREUR : l'export JSON a également échoué. Détail : " . $e->getMessage());
    exit(1);
}

// ── Fonctions ─────────────────────────────────────────────────────────────────

/**
 * Écrit une ligne horodatée sur stdout (redirigé vers cron.log par le scheduler).
 */
function cron_log(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

/**
 * Lance mysqldump en sous-processus de façon défensive (même approche
 * qu'AnalysisRunner::run et que la fonction identique dans backup.php).
 *
 * @return array{ok:bool, sql?:string, error?:string}
 */
function run_mysqldump(array $config): array
{
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'error' => "proc_open() est désactivé sur ce serveur."];
    }

    $mysqldump = 'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe';

    $cmd = [
        $mysqldump,
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
        return ['ok' => false, 'error' => "Impossible de démarrer mysqldump (vérifiez le PATH)."];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || trim((string) $stdout) === '') {
        $detail = $stderr !== '' ? trim($stderr) : 'Aucune sortie.';
        return ['ok' => false, 'error' => "mysqldump a échoué (code $exitCode). $detail"];
    }

    return ['ok' => true, 'sql' => $stdout];
}

/**
 * Export JSON de secours sans dépendre de mysqldump.
 * Les mots de passe et jetons sont exclus (exigence 5.2).
 */
function build_json_export(PDO $pdo): array
{
    $export = ['generated_at' => gmdate('c'), 'tables' => []];
    $tables = ['users', 'videos', 'assistance', 'login_attempts', 'account_deletion_logs'];

    foreach ($tables as $table) {
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

/**
 * Supprime les fichiers les plus anciens d'un répertoire pour n'en
 * conserver que $keep (rotation simple par date de modification).
 */
function rotate_backups(string $dir, string $pattern, int $keep): void
{
    $files = glob($dir . '/' . $pattern) ?: [];
    if (count($files) <= $keep) {
        return;
    }
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b)); // plus anciens en premier
    $toDelete = array_slice($files, 0, count($files) - $keep);
    foreach ($toDelete as $file) {
        @unlink($file);
        cron_log("Rotation : suppression de « " . basename($file) . " ».");
    }
}