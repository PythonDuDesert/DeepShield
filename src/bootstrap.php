<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AnalysisRunner.php';
require_once __DIR__ . '/ReportStore.php';
require_once __DIR__ . '/VideoRepository.php';

$config = require __DIR__ . '/config.php';

date_default_timezone_set('Europe/Paris');

/**
 * La connexion DB peut échouer (base non démarrée, mauvais identifiants).
 * On ne casse jamais toute la page pour autant (exigence 5.3) : $dbError
 * est renseigné et les pages qui ont besoin de la base l'affichent
 * proprement plutôt que de planter avec une erreur PHP brute.
 */
$pdo = null;
$auth = null;
$dbError = null;
try {
    $pdo = Database::connect($config);
    $auth = new Auth($pdo);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

/** Redirige vers la page de connexion si l'utilisateur n'est pas authentifié. */
function ds_require_login(?Auth $auth, ?string $dbError): void
{
    if ($dbError !== null) {
        return; // on laisse la page afficher l'erreur DB plutôt que boucler vers login
    }
    if ($auth === null || !$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function ds_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
