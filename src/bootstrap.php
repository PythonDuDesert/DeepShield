<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/AnalysisRunner.php';
require_once __DIR__ . '/ReportStore.php';

$config = require __DIR__ . '/config.php';

date_default_timezone_set('Europe/Paris');
