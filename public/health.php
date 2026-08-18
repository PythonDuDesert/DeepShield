<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pythonOk = false;
$pythonVersion = null;
$out = [];
$returnCode = 1;
exec(escapeshellarg($config['python_bin']) . ' --version 2>&1', $out, $returnCode);
if ($returnCode === 0 && !empty($out)) {
    $pythonOk = true;
    $pythonVersion = trim($out[0]);
}

$bridgeOk = is_file($config['bridge_script']);

$dbOk = false;
if ($dbError === null && $pdo !== null) {
    try {
        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        $dbOk = false;
    }
}

$procOpenOk = function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);

echo json_encode([
    'status' => ($pythonOk && $bridgeOk && $dbOk && $procOpenOk) ? 'ok' : 'degraded',
    'time' => gmdate('c'),
    'python' => ['reachable' => $pythonOk, 'version' => $pythonVersion],
    'bridge_script_found' => $bridgeOk,
    'proc_open_available' => $procOpenOk,
    'database' => ['reachable' => $dbOk, 'error' => $dbError],
], JSON_PRETTY_PRINT);
