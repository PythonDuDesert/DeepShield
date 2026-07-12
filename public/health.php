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

echo json_encode([
    'status' => ($pythonOk && $bridgeOk) ? 'ok' : 'degraded',
    'time' => gmdate('c'),
    'python' => ['reachable' => $pythonOk, 'version' => $pythonVersion],
    'bridge_script_found' => $bridgeOk,
    'mock_mode' => $config['mock_mode'],
], JSON_PRETTY_PRINT);
