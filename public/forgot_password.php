<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
require_once __DIR__ . '/email_helper.php';
$navActive = 'login';

if ($dbError === null && $auth !== null && $auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbError === null) {
    if (ds_csrf_verify($_POST['csrf_token'] ?? null)) {
        $email = trim($_POST['email'] ?? '');
        $reset = $auth->requestPasswordReset($email);
        if ($reset !== null) {
            sendResetPasswordEmail($email, $reset['first_name'], $reset['token']);
            log_password_forgot($reset['user_id'], $email);
        }
    }
    // Message générique dans tous les cas : on ne révèle jamais si le compte existe.
    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié — DeepShield</title>
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
        <h1>Mot de passe oublié</h1>
        <p>Recevez un lien de réinitialisation par email.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Base de données injoignable pour le moment. <?= e($dbError) ?></span>
      </div>
    <?php elseif ($submitted): ?>
      <div class="auth-notice" style="border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);margin-top:0;">
        <span class="icon">✉️</span>
        <span>Si un compte existe avec cette adresse, un email de réinitialisation vient d'être envoyé.</span>
      </div>
    <?php else: ?>
      <form class="auth-panel active" method="post" action="forgot_password.php">
          <input type="hidden" name="csrf_token" value="<?= e(ds_csrf_token()) ?>">
          <div class="auth-field">
              <label for="fp-email">Adresse e-mail</label>
              <input type="email" id="fp-email" name="email" placeholder="prenom.nom@organisme.fr" autocomplete="email" required>
          </div>
          <button type="submit" class="auth-submit">Envoyer le lien de réinitialisation</button>
      </form>
    <?php endif; ?>

    <div class="auth-back"><a href="login.php">← Retour à la connexion</a></div>
  </div>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
