<?php

require_once 'integrity_check.php';

// Configuration - le fichier logs.json est dans le même dossier que logs.php
define('LOG_DIR', __DIR__);
if (!defined('LOG_FILE')) {
    define('LOG_FILE', get_log_file());
}

// Retourne le chemin du fichier du jour
function get_log_file(string $day = ''): string {
    $d = $day ?: date('d-m-Y');
    return LOG_DIR . '/logs-' . $d . '.json';
}

// =============================================================================
// INTÉGRITÉ — CHAÎNE DE HACHAGE
// =============================================================================

/**
 * Retourne le hash de la dernière entrée enregistrée.
 * Utilisé pour chaîner chaque nouvel enregistrement au précédent.
 */
function get_last_log_hash(string $day = ''): string {
    $file = get_log_file($day);
    if (!file_exists($file)) return '';
    // Lecture efficace : uniquement la dernière ligne non vide
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return '';
    $last = json_decode(end($lines), true);
    return $last['hash'] ?? '';
}
 
/**
 * Vérifie l'intégrité de toute la chaîne de logs.
 *
 * @return array [
 *   'ok'      => bool,
 *   'checked' => int,   // nombre d'entrées vérifiées
 *   'error'   => string|null
 * ]
 */
function verify_logs_integrity(string $day = ''): array {
    $file = get_log_file($day);
    if (!file_exists($file)) {
        return ['ok' => true, 'checked' => 0, 'error' => null];
    }
 
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $prev_hash = '';
    $checked = 0;
 
    foreach ($lines as $i => $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) continue;
 
        $stored_hash  = $entry['hash'] ?? '';
        $stored_phash = $entry['prev_hash'] ?? '';
 
        // 1. Le prev_hash doit correspondre au hash de l'entrée précédente
        if ($stored_phash !== $prev_hash) {
            return [
                'ok' => false,
                'checked' => $checked,
                'error' => "Chaîne brisée à la ligne " . ($i + 1) . " (id: " . ($entry['id'] ?? '?') . ") — " . "une entrée a peut-être été supprimée ou insérée.",
            ];
        }
 
        // 2. Recalculer le hash de cette entrée sans le champ 'hash'
        $copy = $entry;
        unset($copy['hash']);
        $expected = hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE));
 
        if ($stored_hash !== $expected) {
            return [
                'ok' => false,
                'checked' => $checked,
                'error' => "Hash invalide à la ligne " . ($i + 1) . " (id: " . ($entry['id'] ?? '?') . ") — " .  "le contenu de cette entrée a été modifié.",
            ];
        }
 
        $prev_hash = $stored_hash;
        $checked++;
    }
 
    return ['ok' => true, 'checked' => $checked, 'error' => null];
}


// =============================================================================
// ÉCRITURE
// =============================================================================

/**
 * Fonction principale pour écrire un log
 * 
 * @param string $type Type de log (info, important_info, warning, error, BDD)
 * @param string $action Action effectuée (login, logout, delete, etc.)
 * @param string $message Message descriptif
 * @param int|null $user_id ID de l'utilisateur (optionnel)
 * @param string|null $email Email de l'utilisateur (optionnel)
 * @param array $extra Données supplémentaires (optionnel)
 */
function write_log($type, $action, $message, $user_id = null, $email = null, $extra = []) {
    // Créer l'enregistrement
    $log_entry = [
        'id' => uniqid('log_', true),
        'date' => date('d/m/Y'),
        'time' => date('H:i:s'),
        'type' => $type,
        'action' => $action,
        'message' => $message,
        'user_id' => $user_id,
        'email' => $email,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'extra' => $extra,
        'prev_hash' => get_last_log_hash(),
    ];
    
    // Nettoyer les valeurs null et tableaux vides
    $log_entry = array_filter($log_entry, fn($v) => $v !== null && $v !== []);
 
    // Hash calculé AVANT d'ajouter le champ 'hash' lui-même
    $log_entry['hash'] = hash('sha256', json_encode($log_entry, JSON_UNESCAPED_UNICODE));
 
    file_put_contents(get_log_file(), json_encode($log_entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

    check_integrity_on_write();
    
    return $log_entry;
}

// =============================================================================
// FONCTIONS PRÉ-ÉCRITES
// =============================================================================

/**
 * Log de connexion réussie
 * 
 * @param int $user_id ID de l'utilisateur (colonne id)
 * @param string $email Email de l'utilisateur (colonne email)
 * @param int $role Rôle de l'utilisateur (colonne role)
 */
function log_login($user_id, $email, $role = null) {
    $role_name = get_role_name($role);
    
    return write_log(
        'info',
        'login',
        "Connexion réussie de $email ($role_name)",
        $user_id,
        $email,
        ['role' => $role, 'role_name' => $role_name]
    );
}

/**
 * Log d'échec de connexion
 * 
 * @param string $email Email utilisé pour la tentative
 */
function log_login_failed($email) {
    return write_log(
        'info',
        'login_failed',
        "Tentative de connexion échouée pour $email",
        null,
        $email
    );
}

/**
 * Log de déconnexion
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 */
function log_logout($user_id, $email) {
    return write_log(
        'info',
        'logout',
        "Déconnexion normale de l'utilisateur $email",
        $user_id,
        $email
    );
}

/**
 * Log de timeout
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 */
function log_logout_timeout($user_id, $email) {
    return write_log(
        'info',
        'logout',
        "Déconnexion TIMEOUT de l'utilisateur $email",
        $user_id,
        $email
    );
}

/**
 * Log de unauthorized
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param string $requested_page Page demandée par l'utilisateur
 * @param string $ip IP de l'utilisateur
 */
function log_logout_unauthorized($user_id, $email, $requested_page, $ip) {
    return write_log(
        'warning',
        'logout',
        "Déconnexion forcée (UNAUTHORIZED) de l'utilisateur $email, ip: $ip, page: $requested_page",
        $user_id,
        $email,
        $requested_page,
        $ip
    );
}

/**
 * Log de création d'utilisateur
 * 
 * @param int $new_user_id ID du nouvel utilisateur
 * @param string $new_user_email Email du nouvel utilisateur
 * @param string $first_name
 * @param string $last_name
 */
function log_profile_created($new_user_id, $new_user_email, $first_name = null, $last_name = null) {
    $full_name = trim("$first_name $last_name");
    
    return write_log(
        'info',
        'user_created',
        "Inscription de l'utilisateur $new_user_email" . ($full_name ? " ($full_name)" : ""),
        [
            'new_user_id' => $new_user_id,
            'new_user_email' => $new_user_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]
    );
}

/**
 * Log de suppression d'un profile utilisateur
 *
 * @param int $user_id ID de l'utilisateur supprimé
 * @param string $email Email de l'utilisateur supprimé
 * @param array $reasons Raison de la suppression
 */
function log_profile_deleted($user_id, $email, $reasons = []) { 
   
    return write_log(
        'warning',
        'profile_deleted',
        "L'utilisateur $user_id ($email) a supprimé son profile: $reasons",
    );
}

/**
 * Log de modification d'un profile utilisateur
 *
 * @param int $user_id ID de l'utilisateur modifié
 * @param string $updated_email Email de l'utilisateur modifié
 * @param array $changes Tableau des changements [old => new]
 */
function log_profile_updated($user_id, $updated_email, $changes = []) {
    $change_summary = [];
    
    if (isset($changes['email'])) {
        $change_summary[] = "email: {$changes['email']['old']} → {$changes['email']['new']}";
    }
    if (isset($changes['first_name'])) {
        $change_summary[] = "prénom: {$changes['first_name']['old']} → {$changes['first_name']['new']}";
    }
    if (isset($changes['last_name'])) {
        $change_summary[] = "nom: {$changes['last_name']['old']} → {$changes['last_name']['new']}";
    }
    
    $summary = !empty($change_summary) ? " (" . implode(", ", $change_summary) . ")" : "";
    
    return write_log(
        'info',
        'user_updated',
        "L'utilisateur $user_id ($updated_email) a modifié son profile: $summary",
    );
}

/**
 * Log de changement de mot de passe
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 */
function log_password_changed($user_id, $email) {
    
    return write_log(
        'info',
        'password_changed',
        "$email a changé son mot de passe",
        $user_id,
        $email
    );
}

/**
 * Log de mot de passe oublié
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 */
function log_password_forgot($user_id, $email) {
    
    return write_log(
        'info',
        'password_changed',
        "$email a oublié son mot de passe, lien de réinitialisation envoyé",
        $user_id,
        $email
    );
}

/**
 * Log de suppression d'utilisateur
 * 
 * @param int $admin_id ID de l'admin qui supprime
 * @param string $admin_email Email de l'admin
 * @param int $deleted_user_id ID de l'utilisateur supprimé
 * @param string $deleted_email Email de l'utilisateur supprimé
 * @param string $first_name Prénom (optionnel)
 * @param string $last_name Nom (optionnel)
 */
function log_user_deleted($admin_id, $admin_email, $deleted_user_id, $deleted_email, $first_name = null, $last_name = null) {
    $full_name = trim("$first_name $last_name");
    
    return write_log(
        'warning',
        'user_deleted',
        "$admin_email a supprimé l'utilisateur $deleted_email" . ($full_name ? " ($full_name)" : ""),
        $admin_id,
        $admin_email,
        [
            'deleted_user_id' => $deleted_user_id,
            'deleted_email' => $deleted_email,
            'deleted_first_name' => $first_name,
            'deleted_last_name' => $last_name
        ]
    );
}

/**
 * Log d'activation/désactivation d'utilisateur
 * 
 * @param int $admin_id ID de l'admin
 * @param string $admin_email Email de l'admin
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param bool $is_active Nouveau statut (1=actif, 0=inactif)
 */
function log_user_status_changed($admin_id, $admin_email, $user_id, $email, $is_active) {
    $status = $is_active ? 'activé' : 'désactivé';
    
    return write_log(
        'important_info',
        'user_status_changed',
        "$admin_email a $status l'utilisateur $email",
        $admin_id,
        $admin_email,
        [
            'affected_user_id' => $user_id,
            'affected_user_email' => $email,
            'new_status' => $is_active
        ]
    );
}

/**
 * Log de déblocage temporaire d'utilisateur
 * 
 * @param int $admin_id ID de l'admin
 * @param string $admin_email Email de l'admin
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 */
function log_user_unblocked($admin_id, $admin_email, $user_id, $email) {
    
    return write_log(
        'important_info',
        'user_status_changed',
        "$admin_email a débloqué l'utilisateur $email",
        $admin_id,
        $admin_email,
        [
            'affected_user_id' => $user_id,
            'affected_user_email' => $email,
        ]
    );
}

/**
 * Log de changement de rôle
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param int $old_role Ancien rôle
 * @param int $new_role Nouveau rôle
 */
function log_user_role_changed($user_id, $email, $old_role, $new_role) {
    $old_role_name = get_role_name($old_role);
    $new_role_name = get_role_name($new_role);
    
    return write_log(
        'important_info',
        'role_changed',
        "$email a changé de rôle de '$old_role_name' à '$new_role_name'",
        [
            'affected_user_id' => $user_id,
            'affected_user_email' => $email,
            'old_role' => $old_role,
            'new_role' => $new_role,
            'old_role_name' => $old_role_name,
            'new_role_name' => $new_role_name
        ]
    );
}

/**
 * Log de blocage temporaire d'une IP (trop de tentatives)
 *
 * @param string $email Email utilisé lors du blocage
 * @param string $ip IP bloquée (optionnel, récupérée automatiquement sinon)
 */
function log_ip_blocked($email, $ip = null) {
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return write_log(
        'warning',
        'ip_blocked',
        "IP $ip bloquée temporairement suite à trop de tentatives échouées (dernier email utilisé : $email)",
        null,
        $email,
        ['blocked_ip' => $ip]
    );
}

/**
 * Log de blocage temporaire d'un compte (trop de mauvais mots de passe)
 *
 * @param int $user_id ID de l'utilisateur bloqué
 * @param string $email Email de l'utilisateur bloqué
 */
function log_user_blocked($user_id, $email) {
    return write_log(
        'warning',
        'user_blocked',
        "Compte $email bloqué temporairement suite à trop de tentatives échouées",
        $user_id,
        $email
    );
}

/**
 * Log de réactivation automatique d'un compte bloqué (par le cron)
 *
 * @param int $user_id ID de l'utilisateur réactivé
 * @param string $email Email de l'utilisateur réactivé
 */
function log_user_auto_unblocked($user_id, $email) {
    return write_log(
        'warning',
        'user_auto_unblocked',
        "Réactivation automatique du compte $email après expiration du blocage temporaire",
        $user_id,
        $email
    );
}

/**
 * Log d'analyse d'une vidéo
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param int $email_id ID de l'email
 * @param string $score Score de phishing de l'email
 */
function log_video_analysed($user_id, $email, $email_id, $score) {

    return write_log(
        'info',
        'video_analysed',
        "$email a analysé une video (id: $email_id)(score: $score%)",
        [
            'affected_user_id' => $user_id,
            'affected_user_email' => $email,
            'affected_video_id' => $email_id,
            'affected_video_score' => $score,
        ]
    );
}

/**
 * Log de suppression d'un email
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $email Email de l'utilisateur
 * @param int $email_id ID de l'email
 * @param string $score Score de phishing de l'email
 */
function log_email_deleted($user_id, $email, $email_id) {

    return write_log(
        'info',
        'email_deleted',
        "$email a supprimé un email (id: $email_id)",
        [
            'affected_user_id' => $user_id,
            'affected_user_email' => $email,
            'affected_email_id' => $email_id,
        ]
    );
}

/**
 * Log d'une action backup BDD (création, suppression, téléchargement, restauration)
 *
 * @param int $user_id ID de l'admin
 * @param string $email Email de l'admin
 * @param string $action Action effectuée (ex: "création manuelle", "suppression")
 * @param string $file Nom du fichier backup concerné
 * @param string $details Détails supplémentaires
 */
function log_BDD_backup($user_id, $email, $action, $file, $details = '') {

    $is_destructive = in_array($action, ['suppression', 'restauration', 'téléchargement']);

    return write_log(
        $is_destructive ? 'warning' : 'BDD',
        'backup_' . str_replace(' ', '_', strtolower($action)),
        "$email — $action : $file" . ($details ? " ($details)" : ""),
        $user_id,
        $email,
        ['file' => $file, 'details' => $details]
    );
}

/**
 * Log d'une erreur backup
 *
 * @param int $user_id  ID de l'admin
 * @param string $email Email de l'admin
 * @param string $file Nom du fichier concerné
 * @param string $error Message d'erreur
 */
function log_BDD_backup_error($user_id, $email, $file, $error) {

    return write_log(
        'error',
        'backup_error',
        "$email — Erreur backup sur $file : $error",
        $user_id,
        $email,
        ['file' => $file, 'error' => $error]
    );
}

/**
 * Log d'erreur générique
 */
function log_error($message, $error_code = null, $user_id = null, $email = null) {
    return write_log(
        'error',
        'error',
        $message,
        $user_id,
        $email,
        $error_code ? ['error_code' => $error_code] : []
    );
}

/**
 * Log d'erreur de base de données
 */
function log_database_error($operation, $error_message) {
    return write_log(
        'error',
        'database_error',
        "Erreur BDD lors de $operation: $error_message",
        null,
        null,
        ['operation' => $operation]
    );
}


// =============================================================================
// FONCTIONS UTILITAIRES
// =============================================================================

/**
 * Convertir le numéro de rôle en nom lisible
 * 
 * @param int|null $role
 * @return string
 */
function get_role_name($role) {
    if ($role === null) {
        return 'inconnu';
    }
    
    $roles = [
        0 => 'admin',
        1 => 'premium user',
        2 => 'user',
    ];
    
    return $roles[$role] ?? "rôle $role";
}

// =============================================================================
// FONCTIONS DE LECTURE DES LOGS
// =============================================================================

/**
 * Lire les logs d'un jour donné (ordre anti-chronologique).
 *
 * @param string $day Date au format d-m-Y (ex: '26-03-2026'). Si vide, utilise le jour courant.
 * @param int $limit Nombre maximum d'entrées (0 = tout).
 * @return array Entrées les plus récentes en premier. Tableau vide si le fichier n'existe pas.
 */
function read_logs_by_day(string $day = '', int $limit = 50): array {
    $file = get_log_file($day ?: date('d-m-Y'));
    if (!file_exists($file)) return [];

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];

    $logs = [];
    foreach (array_reverse($lines) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $logs[] = $entry;
        }
    }

    return $limit > 0 ? array_slice($logs, 0, $limit) : $logs;
}

/**
 * Lire tous les logs des dernières 24h (aujourd'hui + hier).
 */
function read_all_logs(): array {
    $cutoff = time() - 86400;
    $logs   = [];

    foreach ([date('d-m-Y'), date('d-m-Y', strtotime('yesterday'))] as $day) {
        $file = get_log_file($day);
        if (!file_exists($file)) continue;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            $parsable = preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', $entry['date'] ?? '');
            $ts       = strtotime("$parsable " . ($entry['time'] ?? '00:00:00')) ?: 0;
            if ($ts >= $cutoff) $logs[] = $entry;
        }
    }

    usort($logs, function($a, $b) {
        $ta = strtotime(preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', $a['date'] ?? '') . ' ' . ($a['time'] ?? ''));
        $tb = strtotime(preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', $b['date'] ?? '') . ' ' . ($b['time'] ?? ''));
        return $tb - $ta;
    });

    return $logs;
}

/**
 * Lire les logs avec filtres
 */
function read_logs($filters = []) {
    $all_logs = read_all_logs();
    
    if (empty($filters)) {
        return $all_logs;
    }
    
    return array_filter($all_logs, function($log) use ($filters) {
        // Filtre par type
        if (isset($filters['type']) && $log['type'] !== $filters['type']) {
            return false;
        }
        
        // Filtre par action
        if (isset($filters['action']) && $log['action'] !== $filters['action']) {
            return false;
        }
        
        // Filtre par user_id
        if (isset($filters['user_id']) && $log['user_id'] !== $filters['user_id']) {
            return false;
        }
        
        // Filtre par email
        if (isset($filters['email']) && $log['email'] !== $filters['email']) {
            return false;
        }
        
        // Filtre par date (après)
        if (isset($filters['date_from'])) {
            $log_timestamp = strtotime(str_replace('-', ' ', $log['date']));
            $filter_timestamp = strtotime($filters['date_from']);
            if ($log_timestamp < $filter_timestamp) {
                return false;
            }
        }
        
        // Filtre par date (avant)
        if (isset($filters['date_to'])) {
            $log_timestamp = strtotime(str_replace('-', ' ', $log['date']));
            $filter_timestamp = strtotime($filters['date_to']);
            if ($log_timestamp > $filter_timestamp) {
                return false;
            }
        }
        
        return true;
    });
}

/**
 * Compter les logs par type
 */
function count_logs_by_type() {
    $logs = read_all_logs();
    $counts = ['info' => 0, 'important_info' => 0, 'warning' => 0, 'error' => 0, 'BDD' => 0];
    
    foreach ($logs as $log) {
        if (isset($counts[$log['type']])) {
            $counts[$log['type']]++;
        }
    }
    
    return $counts;
}

/**
 * Obtenir les derniers logs
 */
function get_recent_logs($limit = 50) {
    $logs = read_all_logs();
    return array_slice($logs, 0, $limit);
}

/**
 * Chercher dans les logs
 */
function search_logs($keyword) {
    $logs = read_all_logs();
    
    return array_filter($logs, function($log) use ($keyword) {
        $keyword_lower = strtolower($keyword);
        
        // Chercher dans le message
        if (stripos($log['message'], $keyword) !== false) {
            return true;
        }
        
        // Chercher dans l'email
        if (isset($log['email']) && stripos($log['email'], $keyword) !== false) {
            return true;
        }
        
        // Chercher dans l'action
        if (stripos($log['action'], $keyword) !== false) {
            return true;
        }
        
        return false;
    });
}

?>
