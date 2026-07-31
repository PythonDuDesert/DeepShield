<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';

if ($auth !== null) {
    $user = $auth->currentUser();
    if ($user !== null) {
        log_logout($user['id'], $user['email']); // LOGS
    }
    $auth->logout();
}

header('Location: index.php');
exit;