<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

ds_require_login($auth, $dbError);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: analyser.php');
    exit;
}

if ($dbError !== null) {
    ds_flash_set('error', "Impossible de lancer une analyse : base de données injoignable.");
    header('Location: analyser.php');
    exit;
}

/**
 * Valide et déplace un fichier uploadé. Retourne le chemin absolu final,
 * ou null si aucun fichier n'a été fourni. Lève une exception explicite en
 * cas de fichier invalide (jamais d'échec silencieux, exigence 5.3).
 */
function ds_handle_upload(string $fieldName, array $allowedExt, int $maxBytes, string $uploadDir): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur lors de l'envoi du fichier ($fieldName), code {$file['error']}.");
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException("Fichier trop volumineux ($fieldName) : " . ds_format_bytes($file['size']) .
            " (maximum " . ds_format_bytes($maxBytes) . ").");
    }
    if (!ds_has_allowed_extension($file['name'], $allowedExt)) {
        throw new RuntimeException(
            "Extension non autorisée pour $fieldName. Formats acceptés : " . implode(', ', $allowedExt) . "."
        );
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("Envoi de fichier invalide ($fieldName).");
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $destination = $uploadDir . '/' . ds_uuid() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Impossible d'enregistrer le fichier ($fieldName) sur le serveur.");
    }

    return $destination;
}

$store = new ReportStore($config['reports_dir']);
$videos = new VideoRepository($pdo);
$user = $auth->currentUser();
$limits = ds_user_limits((int) $user['role']);

try {
    $videoPath = ds_handle_upload('video_file', $config['video_extensions'], $limits['max_upload_bytes'], $config['upload_dir']);
    $audioPath = ds_handle_upload('audio_file', $config['audio_extensions'], $limits['max_upload_bytes'], $config['upload_dir']);

    if ($videoPath === null && $audioPath === null) {
        throw new RuntimeException("Veuillez fournir au moins un fichier vidéo ou audio.");
    }

    $originalVideoSize = $videoPath !== null ? (int) ($_FILES['video_file']['size'] ?? 0) : 0;

    $maxFrames = max(5, min(120, (int) ($_POST['max_frames'] ?? $config['max_frames'])));
    $threshold = max(1.0, min(99.0, (float) ($_POST['threshold'] ?? $config['threshold'])));
    $keepForRetraining = ($_POST['keep_for_retraining'] ?? '') === '1';

    $runnerConfig = $config;
    $runnerConfig['max_frames'] = $maxFrames;
    $runnerConfig['threshold'] = $threshold;

    $runner = new AnalysisRunner($runnerConfig);
    $report = $runner->run($videoPath, $audioPath);

    if (!empty($report['video']) && !empty($_FILES['video_file']['name'])) {
        $report['video']['filename'] = $_FILES['video_file']['name'];
    }
    if (!empty($report['audio']) && !empty($_FILES['audio_file']['name'])) {
        $report['audio']['filename'] = $_FILES['audio_file']['name'];
    }
    $report['user_id'] = $user['id'];
    $report['uploaded_files'] = [
        'video_original_name' => $_FILES['video_file']['name'] ?? null,
        'audio_original_name' => $_FILES['audio_file']['name'] ?? null,
        'kept_for_retraining' => $keepForRetraining,
    ];

    $isSuccess = ($report['status'] ?? 'error') === 'ok';

    // Persistance en base : uniquement pour la modalité vidéo (table `videos`
    // du schéma fourni). Une analyse audio seule reste consultable via son
    // rapport JSON mais n'apparaît pas dans l'historique lié à la base.
    if ($isSuccess && !empty($report['video'])) {
        $verdict = $report['video']['verdict'];
        $scoreReal = (int) round((float) $report['video']['avg_real']);
        $nSuspect = count(array_filter($report['video']['frames'] ?? [], fn($f) => !empty($f['suspect'])));
        $explinations = sprintf(
            '%s — score réel %.1f%%, %d frame(s) suspecte(s) sur %d analysée(s)',
            $verdict,
            (float) $report['video']['avg_real'],
            $nSuspect,
            (int) $report['video']['n_frames_analyzed']
        );

        $videoId = $videos->insert(
            (int) $user['id'],
            $report['video']['filename'],
            $originalVideoSize,
            $user['email'],
            $scoreReal,
            $explinations
        );

        $id = $store->save($report, (string) $videoId);
    } else {
        // Échec, ou audio seul : rapport horodaté sans ligne `videos` associée.
        $id = $store->save($report);
    }

    // Exigence 5.2 : suppression des fichiers biométriques après analyse,
    // sauf consentement explicite au ré-entraînement.
    if ($config['auto_delete_uploads'] && !$keepForRetraining) {
        foreach ([$videoPath, $audioPath] as $p) {
            if ($p !== null && is_file($p)) {
                @unlink($p);
            }
        }
    }

    header('Location: report.php?id=' . urlencode($id));
    exit;
} catch (Throwable $e) {
    ds_flash_set('error', $e->getMessage());
    header('Location: analyser.php');
    exit;
}
