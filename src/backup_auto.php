<?php
/**
 * backup_auto.php — Sauvegarde automatique (cron)
 * =================================================
 * Lancé par le planificateur de tâches Windows.
 */

declare(strict_types=1);

$srcDir  = __DIR__;                    // …/DeepShield/src
$rootDir = dirname($srcDir);          // …/DeepShield

require_once $srcDir . '/bootstrap.php';
// Après bootstrap : $config, $pdo (ou null), $dbError sont disponibles.

require_once $srcDir . '/backup_functions.php';

// ── Répertoire de destination des sauvegardes ────────────────────────────────
$backupDir = $rootDir . '/BDD/backups';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0775, true)) {
        cron_log("ERREUR : impossible de créer le répertoire de sauvegarde « $backupDir ».");
        exit(1);
    }
}
$historyFile = $backupDir.'/backup_history.json';

// ── Vérification de la base de données ───────────────────────────────────────
if ($dbError !== null) {
    cron_log("ERREUR : connexion à la base de données impossible. Détail : $dbError");
    exit(1);
}


cron_log("Lancement du cycle de sauvegarde automatique...");
try {
    runAutoBackupCycle($config, $backupDir, $historyFile);
    cron_log("Cycle terminé.");
    exit(0);
} catch (Throwable $e) {
    cron_log("ERREUR : " . $e->getMessage());
    exit(1);
}


// FONCTION
/**
 * Écrit une ligne horodatée sur stdout (redirigé vers cron.log par le scheduler).
 */
function cron_log(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}
