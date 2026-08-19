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

$limits = ds_user_limits((int) $user['role']);

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

/**
 * Supprime récursivement tout le contenu d'un dossier temporaire.
 */
function ds_delete_temp_contents(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = scandir($directory);
    
    if ($items === false) {
        return;
    }
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            ds_delete_temp_contents($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
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

    // ==========================================================
    // PERSISTANCE EN BASE
    // ==========================================================
    if (!$isSuccess) {
        // Analyse échouée : rapport JSON uniquement.
        $id = $store->save($report);

    } else {
        $videoId = null;
        $audioId = null;

        // ------------------------------------------------------
        // VIDÉO
        // ------------------------------------------------------
        if (!empty($report['video'])) {
            $verdict = (string) ($report['video']['verdict'] ?? 'SUSPECT');
            $avgReal = (float) ($report['video']['avg_real'] ?? 0);
            $scoreReal = (int) round($avgReal);
            $nSuspect = count(
                array_filter(
                    $report['video']['frames'] ?? [],
                    fn($f) => !empty($f['suspect'])
                )
            );

            $nFrames = (int) (
                $report['video']['n_frames_analyzed'] ?? 0
            );

            $explinations = sprintf(
                '%s — score réel %.1f%%, %d frame(s) suspecte(s) sur %d analysée(s)',
                $verdict,
                $avgReal,
                $nSuspect,
                $nFrames
            );

            $videoId = $videos->insert(
                (int) $user['id'],
                $report['video']['filename'],
                $originalVideoSize,
                $user['email'],
                $scoreReal,
                $explinations
            );

            // Le rapport vidéo est associé à videos.id.
            $id = $store->save($report, 'video_' . $videoId);
        }

        // ------------------------------------------------------
        // AUDIO
        // ------------------------------------------------------
        if (!empty($report['audio'])) {
            $audio = $report['audio'];
            $verdict = (string) (
                $audio['verdict'] ?? 'SUSPECT'
            );

            if (isset($audio['avg_real'])) {
                $scoreReal = (float) $audio['avg_real'];

            } elseif (isset($audio['real_probability'])) {
                $scoreReal = (float) $audio['real_probability'];

                /*
                * Si le moteur renvoie 0.0 -> 1.0,
                * conversion en pourcentage.
                */
                if ($scoreReal <= 1) {
                    $scoreReal *= 100;
                }

            } elseif (isset($audio['score'])) {
                $scoreReal = (float) $audio['score'];

            } else {
                $scoreReal = 0;
            }

            $scoreReal = max(
                0,
                min(100, $scoreReal)
            );

            $scoreRealDb = (int) round($scoreReal);
            $explinations = sprintf(
                '%s — score réel %.1f%%',
                $verdict,
                $scoreReal
            );

            $originalAudioSize = (int) (
                $_FILES['audio_file']['size'] ?? 0
            );

            /*
            * Insertion audio.
            */
            $stmt = $pdo->prepare(
                'INSERT INTO audios
                (
                    user_id,
                    sender_email,
                    audio_name,
                    file_size,
                    score,
                    explinations
                )
                VALUES
                (
                    :user_id,
                    :sender_email,
                    :audio_name,
                    :file_size,
                    :score,
                    :explinations
                )'
            );

            $stmt->execute([
                ':user_id'      => (int) $user['id'],
                ':sender_email' => $user['email'],
                ':audio_name'   => $audio['filename']
                    ?? ($_FILES['audio_file']['name'] ?? 'audio'),
                ':file_size'    => $originalAudioSize,
                ':score'        => $scoreRealDb,
                ':explinations' => $explinations,
            ]);

            $audioId = (int) $pdo->lastInsertId();

            /*
            * Si l'analyse est audio seule, le rapport doit
            * également être sauvegardé.
            *
            * Pour une analyse vidéo + audio, on ne remplace
            * PAS le rapport vidéo déjà créé.
            */
            if ($videoId === null) {
                $id = $store->save($report, 'audio_' . $audioId);
            }
        }

        /*
        * Sécurité : une analyse réussie doit toujours avoir
        * un identifiant de rapport.
        */
        if ($id === null) {
            $id = $store->save($report);
        }
    }

    // ==========================================================
    // NETTOYAGE DES FICHIERS UPLOADÉS
    // ==========================================================
    // Le fichier source est conservé uniquement si l'utilisateur
    // a explicitement donné son consentement.
    // ==========================================================

    if ($config['auto_delete_uploads'] && !$keepForRetraining) {

        foreach ([$videoPath, $audioPath] as $path) {

            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    // ==========================================================
    // NETTOYAGE DES DONNÉES TEMPORAIRES
    // ==========================================================
    // storage/temp est TOUJOURS nettoyé, quel que soit le
    // consentement de l'utilisateur.
    // Le dossier storage/temp lui-même est conservé.
    // ==========================================================
    ds_delete_temp_contents($config['temp_dir']);

    header('Location: report.php?id=' . urlencode($id));
    exit;
} catch (Throwable $e) {
    ds_flash_set('error', $e->getMessage());
    header('Location: analyser.php');
    exit;
}
