<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
require_once __DIR__ . '/email_helper.php';
$navActive = 'profile';

ds_require_login($auth, $dbError);

if ($dbError === null) {
    $currentUser = $auth->currentUser();

    if ((int) $currentUser['role'] !== 2) {
        header('Location: profile.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!ds_csrf_verify($_POST['csrf_token'] ?? null)) {
            ds_flash_set('error', "Requête invalide ou expirée, merci de réessayer.");
            header('Location: upgrade_premium.php');
            exit;
        }

        $result = $auth->upgradeToPremium((int) $currentUser['id']);
        if ($result['success']) {
            $_SESSION['role'] = 1;
            log_user_role_changed((int) $currentUser['id'], $result['email'], (int) $currentUser['id'], $result['email'], 2, 1);
            sendPremiumUpgradeEmail($result['email'], $result['first_name']);
            ds_flash_set('success', "Votre compte est maintenant Premium !");
        } else {
            ds_flash_set('error', $result['error']);
        }

        header('Location: profile.php');
        exit;
    }

    $limits = ds_user_limits(1);
    $csrfToken = ds_csrf_token();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passer Premium — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/bouclier.ico" type="image/x-icon">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>⭐ Passer au compte Premium</h1>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice">Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <section class="panel" style="max-width:520px;">
      <div class="notice" style="margin-bottom:18px;">
        ℹ Mode démonstration — aucun paiement réel n'est effectué. Cette page simule le résultat d'un paiement réussi.
      </div>

      <h2>Avantages inclus</h2>
      <dl class="meta-list" style="font-size:1.1em;margin-top:16px;margin-bottom:20px;">
        <div><dt>Historique conservé</dt><dd>Jusqu'à <?= (int) $limits['history_limit'] ?> analyses</dd></div>
        <div><dt>Taille max. par fichier</dt><dd><?= e(ds_format_bytes($limits['max_upload_bytes'])) ?></dd></div>
        <div><dt>Export CSV de l'historique</dt><dd>Disponible</dd></div>
      </dl>

      <form method="post" action="upgrade_premium.php">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Confirmer le passage Premium</button>
      </form>
      <div class="auth-back"><a href="profile.php">← Retour au profil</a></div>
    </section>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
