<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if ($auth !== null) {
    $auth->logout();
}

header('Location: index.php');
exit;
