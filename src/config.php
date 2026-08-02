<?php
/**
 * Configuration centralisée du front-end DeepShield.
 *
 * Toutes les valeurs viennent du fichier .env à la racine du projet.
 * Aucun chemin de fichier n'est codé en dur ici (exigence 5.3 du cahier
 * des charges) : tout est paramétrable pour permettre l'exécution sur
 * n'importe quel poste ou serveur (exigence 4.1).
 */

declare(strict_types=1);

function deepshield_load_env(string $path): array
{
    $env = [];
    if (!is_file($path)) {
        return $env;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

$rootDir = dirname(__DIR__);
$envPath = $rootDir . '/.env';
$env = deepshield_load_env($envPath);

/**
 * Petit accesseur avec valeur par défaut, pour ne jamais planter
 * silencieusement si une variable manque (exigence 5.3).
 */
function ds_env(string $key, $default = null)
{
    global $env;
    return $env[$key] ?? $default;
}

return [
    'root_dir'          => $rootDir,
    'api_key'           => ds_env('DEEPSHIELD_API_KEY', 'demo-key-change-me'),
    'python_bin'        => ds_env('DEEPSHIELD_PYTHON_BIN', 'python3'),
    'mock_mode'         => ds_env('DEEPSHIELD_MOCK_MODE', '1') === '1',
    'max_frames'        => (int) ds_env('DEEPSHIELD_MAX_FRAMES', 30),
    'threshold'         => (float) ds_env('DEEPSHIELD_THRESHOLD', 50),
    'model_cache_dir'   => $rootDir . '/' . ltrim(ds_env('DEEPSHIELD_MODEL_CACHE_DIR', './storage/models'), './'),
    'temp_dir'          => $rootDir . '/' . ltrim(ds_env('DEEPSHIELD_TEMP_DIR', './storage/tmp'), './'),
    'upload_dir'        => $rootDir . '/storage/uploads',
    'reports_dir'       => $rootDir . '/storage/reports',
    'bridge_script'     => $rootDir . '/bridge/analyze_bridge.py',
    'max_upload_bytes'  => (int) ds_env('DEEPSHIELD_MAX_UPLOAD_BYTES', 209715200),
    'video_extensions'  => array_map('trim', explode(',', ds_env('DEEPSHIELD_VIDEO_EXT', 'mp4,mov'))),
    'audio_extensions'  => array_map('trim', explode(',', ds_env('DEEPSHIELD_AUDIO_EXT', 'wav,mp3'))),
    'auto_delete_uploads' => ds_env('DEEPSHIELD_AUTO_DELETE_UPLOADS', '1') === '1',
    'session_idle_timeout' => (int) ds_env('SESSION_IDLE_TIMEOUT', 900),
    'db_host' => ds_env('DB_HOST', '127.0.0.1'),
    'db_port' => (int) ds_env('DB_PORT', 3306),
    'db_name' => ds_env('DB_NAME', 'deepshield'),
    'db_user' => ds_env('DB_USER', 'root'),
    'db_pass' => ds_env('DB_PASS', ''),
];
