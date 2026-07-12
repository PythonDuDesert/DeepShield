<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

try {
    $videoPath = ds_handle_upload('video_file', $config['video_extensions'], $config['max_upload_bytes'], $config['upload_dir']);
    $audioPath = ds_handle_upload('audio_file', $config['audio_extensions'], $config['max_upload_bytes'], $config['upload_dir']);

    if ($videoPath === null && $audioPath === null) {
        throw new RuntimeException("Veuillez fournir au moins un fichier vidéo ou audio.");
    }

    $maxFrames = max(5, min(120, (int) ($_POST['max_frames'] ?? $config['max_frames'])));
    $threshold = max(1.0, min(99.0, (float) ($_POST['threshold'] ?? $config['threshold'])));
    $keepForRetraining = ($_POST['keep_for_retraining'] ?? '') === '1';

    $runnerConfig = $config;
    $runnerConfig['max_frames'] = $maxFrames;
    $runnerConfig['threshold'] = $threshold;

    $runner = new AnalysisRunner($runnerConfig);
    $report = $runner->run($videoPath, $audioPath);

    // Le bridge Python ne connaît que le nom de fichier temporaire (UUID) ;
    // on réaffiche le nom d'origine choisi par l'utilisateur dans le rapport.
    if (!empty($report['video']) && !empty($_FILES['video_file']['name'])) {
        $report['video']['filename'] = $_FILES['video_file']['name'];
    }
    if (!empty($report['audio']) && !empty($_FILES['audio_file']['name'])) {
        $report['audio']['filename'] = $_FILES['audio_file']['name'];
    }

    $report['uploaded_files'] = [
        'video_original_name' => $_FILES['video_file']['name'] ?? null,
        'audio_original_name' => $_FILES['audio_file']['name'] ?? null,
        'kept_for_retraining' => $keepForRetraining,
    ];

    // Exigence 5.2 : suppression des fichiers biométriques après analyse,
    // sauf consentement explicite au ré-entraînement.
    if ($config['auto_delete_uploads'] && !$keepForRetraining) {
        foreach ([$videoPath, $audioPath] as $p) {
            if ($p !== null && is_file($p)) {
                @unlink($p);
            }
        }
    }

    $id = $store->save($report);
    header('Location: report.php?id=' . urlencode($id));
    exit;
} catch (Throwable $e) {
    ds_flash_set('error', $e->getMessage());
    header('Location: analyser.php');
    exit;
}
