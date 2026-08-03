<?php
/**
 * Script d'auto-backup pour le cron
 * Ce script s'exécute indépendamment du rôle utilisateur
 * Il vérifie toutes les minutes si un backup de moins d'1 heure existe
 * Si aucun backup récent n'existe, il en crée un automatiquement
 * 
 * Configuration CRON suggérée (s'exécute toutes les minutes):
 * * * * * * php /chemin/vers/backup_auto.php >> /var/log/backup_auto.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    exit('Accès interdit. Ce script doit être exécuté en ligne de commande.');
}

require_once __DIR__.'/db_config.php';
require_once __DIR__.'/backup_functions.php';
require_once __DIR__.'/logs/logs.php';

$backupDir  = __DIR__.'/BDD/backups';
$historyFile = $backupDir.'/backup_history.json';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

echo "[" . date('Y-m-d H:i:s') . "] Démarrage du cycle d'auto-backup...\n";

$conn_login = getDbConnection();
$conn_login->query("SET time_zone = '+01:00'");
// Récupérer les comptes à réactiver AVANT l'update (pour les logger)
$result = $conn_login->query("SELECT id, email FROM users WHERE is_active = 2 AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

$to_reactivate = [];
while ($row = $result->fetch_assoc()) {
    $to_reactivate[] = $row;
}
if (!empty($to_reactivate)) {
    $conn_login->query("UPDATE users
        SET is_active = 1,
            failed_login_attempts = 0,
            last_try_login = NULL,
            updated_at = NOW()
        WHERE is_active = 2 AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $reactivated = $conn_login->affected_rows;
    foreach ($to_reactivate as $u) {
        log_user_auto_unblocked($u['id'], $u['email']);
    }
    echo "[" . date('Y-m-d H:i:s') . "] $reactivated compte(s) réactivé(s) automatiquement.\n";
}
$conn_login->close();

try {
    runAutoBackupCycle($backupDir, $historyFile);
    echo "[" . date('Y-m-d H:i:s') . "] Cycle d'auto-backup terminé avec succès.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erreur lors du cycle d'auto-backup: " . $e->getMessage() . "\n";
    addHistoryEntry(
        $historyFile,
        'erreur-cron',
        'N/A',
        'Erreur cron: ' . $e->getMessage()
    );
}
?>