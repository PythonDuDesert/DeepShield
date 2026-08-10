<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'login';

$verified = false;
if ($dbError === null) {
    $token = $_GET['token'] ?? '';
    $verified = $auth->verifyEmail((string) $token);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmation d'email — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<?php include __DIR__ . '/../src/partials/navbar.php'; ?>

<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-head">
        <span class="logo-mark" style="width:40px;height:40px;display:flex;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;">
                <rect x="3" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="15" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="3" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="15" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <line x1="3" y1="12" x2="21" y2="12" stroke="#05070d" stroke-width="1.8"/>
            </svg>
        </span>
        <h1>Confirmation d'email</h1>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Base de données injoignable pour le moment. <?= e($dbError) ?></span>
      </div>
    <?php elseif ($verified): ?>
      <div class="auth-notice" style="border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);margin-top:0;">
        <span class="icon">✅</span>
        <span>Votre adresse email est confirmée. Vous pouvez maintenant vous connecter.</span>
      </div>
    <?php else: ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Ce lien de confirmation est invalide ou a expiré. Vous pouvez en demander un nouveau depuis la page de connexion.</span>
      </div>
    <?php endif; ?>

    <div class="auth-back"><a href="login.php">→ Aller à la page de connexion</a></div>
  </div>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
