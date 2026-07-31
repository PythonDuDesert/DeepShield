<?php
/**
 * logs/integrity_check.php
 *
 * Vérifie l'intégrité de la dernière entrée écrite dans logs.json,
 * appelé automatiquement par write_log() à chaque écriture.
 *
 * En cas d'altération détectée :
 *   1. Archive le fichier du jour compromis
 *   2. Recrée un nouveau fichier de logs sain
 *   3. Envoie un email d'alerte à l'admin
 *
 * Aucun blocage de session — la détection est silencieuse.
 * Les admins sont alertés par email et peuvent investiguer.
 *
 */

require_once __DIR__ . '/../../public/email_helper.php';

// Email de l'admin à alerter — à adapter
define('INTEGRITY_ALERT_EMAIL1', 'olivier.yammine@gmail.com');
define('INTEGRITY_ALERT_EMAIL2', 'omar.tanaradje@gmail.com');
define('INTEGRITY_ALERT_EMAIL3', 'lucas.duhoo@gmail.com');

// =============================================================================
// POINT D'ENTRÉE — appelé depuis write_log()
// =============================================================================

/**
 * Vérifie uniquement les deux dernières lignes (léger, appelé à chaque écriture).
 * Si incohérence : verrouille + envoie l'email (une seule fois grâce au lock).
 */
function check_integrity_on_write(): void {

    $file = get_log_file();
    if (!file_exists($file)) return;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count = count($lines);

    // Pas assez d'entrées pour comparer
    if ($count < 2) return;

    $prev = json_decode($lines[$count - 2], true);
    $last = json_decode($lines[$count - 1], true);

    if (!is_array($prev) || !is_array($last)) return;

    // Recalculer le hash de la dernière entrée pour détecter une modification
    $copy = $last;
    unset($copy['hash']);
    $expected_hash = hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE));

    if (($last['hash'] ?? '') !== $expected_hash) {
        $error = "Hash invalide à la ligne $count " . "(id: " . ($last['id'] ?? '?') . ") — " . "le contenu de cette entrée a été modifié.";
        trigger_integrity_alert($error);
    }
}

// =============================================================================
// ACTIONS
// =============================================================================

/**
 * Déclenche les deux actions de protection et loggue dans error_log PHP.
 */
function trigger_integrity_alert(string $error): void {

    // 1. Archiver
    $archived_file = archive_and_reset_compromised_logs();
    if (!$archived_file) return;

    // 2. Extraire le nom
    $archive_name = basename($archived_file);

    // 3. Alerte email
    send_integrity_alert($error, $archive_name);

    // 4. Log serveur
    error_log('[SECUREMAIL] LOGS COMPROMISED — archived: ' . $archived_file);
}


/**
 * Archive le fichier de logs compromis et le remplace par un fichier vide.
 * Le prochain appel à write_log() écrira la nouvelle tête de chaîne avec prev_hash = ''
 *
 * Appelé dans deux contextes :
 *   - Automatique : depuis trigger_integrity_alert() lors d'une détection en écriture.
 *   - Manuel      : depuis clear_integrity_lock.php par le super admin, qui écrit lui-même l'entrée de log après cet appel.
 *
 * @return string Chemin vers le fichier archivé, ou '' en cas d'échec.
 */
function archive_and_reset_compromised_logs(): string {
    $archived_file = archive_compromised_logs();
    if (!$archived_file) {
        error_log("[SECUREMAIL] ARCHIVE FAILED — aborting");
        return '';
    }

    return $archived_file;
}


/**
 * Appelé par 'archive_and_reset_compromised_logs()' pour archiver le fichier.
 * Renomme le fichier .json du jour en *-compromised.json et génère un fichier .sha256 pour la preuve d'intégrité.
 */
function archive_compromised_logs(): string {
    $file = get_log_file();

    if (!file_exists($file)) return '';

    $date = date('d-m-Y_H-i-s');
    $archived = __DIR__ . "/logs-$date-compromised.json";

    if (!rename($file, $archived)) {
        error_log("ARCHIVE FAILED: $file");
        return '';
    }

    // Hash
    $hash = hash_file('sha256', $archived);
    file_put_contents($archived . '.sha256', $hash);

    return $archived;
}

/**
 * Envoie l'email d'alerte via sendSecureMailEmail() (email_helper.php).
 */
function send_integrity_alert(string $error, string $archive_name): void {

    $subject = 'ALERTE — Intégrité des logs SecureMail compromise';
    $body = "
        <h2 style='color:#e74c3c;'>Altération des logs détectée</h2>
        <p>Une incohérence a été détectée dans la chaîne de hachage des logs lors d'une écriture.</p>

        <table style='border-collapse:collapse; width:100%; margin:20px 0;'>
            <tr>
                <td style='padding:10px 14px; font-weight:bold; background:#f9f9f9; width:140px;'>Date</td>
                <td style='padding:10px 14px;'>" . date('d/m/Y à H:i:s') . "</td>
            </tr>
            <tr>
                <td style='padding:10px 14px; font-weight:bold; background:#f9f9f9;'>Erreur</td>
                <td style='padding:10px 14px; color:#e74c3c;'>" . htmlspecialchars($error) . "</td>
            </tr>
            <tr>
                <td style='padding:10px 14px; font-weight:bold; background:#f9f9f9;'>IP</td>
                <td style='padding:10px 14px;'>" . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "</td>
            </tr>
            <tr>
                <td style='padding:10px 14px; font-weight:bold; background:#f9f9f9;'>Page</td>
                <td style='padding:10px 14px;'>" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown') . "</td>
            </tr>
        </table>

        <p><strong>Actions automatiques effectuées :</strong></p>
        <ul style='line-height:2;'>
            <li>Archive du fichier compromis : <code>logs/" . htmlspecialchars($archive_name) . "</code></li>
            <li>Démarrage d'un nouveau fichier de logs sain</li>
        </ul>

        <div style='background:#fff3cd; border-left:4px solid #ffc107; padding:14px 18px; margin-top:20px; border-radius:0 8px 8px 0; font-size:13px;'>
            <strong>Investigation à effectuer :</strong><br><br>
            1. Inspectez le fichier archivé compromis<br>
            2. Conservez son hash <code>.sha256</code> pour preuve d'intégrité<br>
            3. Reprenez l'activité sur le nouveau fichier de logs automatiquement recréé
        </div>
    ";

    sendSecureMailEmail(INTEGRITY_ALERT_EMAIL1, $subject, $body);
    sendSecureMailEmail(INTEGRITY_ALERT_EMAIL2, $subject, $body);
    sendSecureMailEmail(INTEGRITY_ALERT_EMAIL3, $subject, $body);
}
