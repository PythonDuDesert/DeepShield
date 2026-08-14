<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
$navActive = 'login';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenValid = false;
$resetError = null;
$resetSuccess = false;

if ($dbError === null) {
    $tokenValid = $auth->isResetTokenValid($token);

    if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!ds_csrf_verify($_POST['csrf_token'] ?? null)) {
            $resetError = "Requête invalide ou expirée, merci de réessayer.";
        } else {
            $newPassword = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['password_confirm'] ?? '');
            if ($newPassword !== $confirmPassword) {
                $resetError = "Les deux mots de passe ne correspondent pas.";
            } else {
                $result = $auth->resetPassword($token, $newPassword);
                if ($result['success']) {
                    log_password_changed($result['user_id'], $result['email']);
                    $resetSuccess = true;
                } else {
                    $resetError = $result['error'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Réinitialiser le mot de passe — DeepShield</title>
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
        <h1>Nouveau mot de passe</h1>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Base de données injoignable pour le moment. <?= e($dbError) ?></span>
      </div>
    <?php elseif ($resetSuccess): ?>
      <div class="auth-notice" style="border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);margin-top:0;">
        <span class="icon">✅</span>
        <span>Votre mot de passe a été mis à jour. Vous pouvez maintenant vous connecter.</span>
      </div>
    <?php elseif (!$tokenValid): ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Ce lien de réinitialisation est invalide ou a expiré.</span>
      </div>
      <div class="auth-back"><a href="forgot_password.php">→ Demander un nouveau lien</a></div>
    <?php else: ?>
      <form class="auth-panel active" method="post" action="reset_password.php">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(ds_csrf_token()) ?>">
          <?php if ($resetError): ?><p class="error" style="margin-bottom:10px;"><?= e($resetError) ?></p><?php endif; ?>
          <div class="auth-field">
              <label for="rp-password">Nouveau mot de passe</label>
              <input type="password" id="rp-password" name="password" placeholder="••••••••" autocomplete="new-password" minlength="8" maxlength="72"
                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,72}"
                title="8 à 72 caractères, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial." required>
          </div>
          <div class="auth-field">
              <label for="rp-password-confirm">Confirmer le mot de passe</label>
              <input type="password" id="rp-password-confirm" name="password_confirm" placeholder="••••••••" autocomplete="new-password" required>
          </div>
          <button type="submit" class="auth-submit">Réinitialiser mon mot de passe</button>
      </form>
    <?php endif; ?>

    <?php if (!$resetSuccess): ?><div class="auth-back"><a href="login.php">← Retour à la connexion</a></div><?php endif; ?>
    <?php if ($resetSuccess): ?><div class="auth-back"><a href="login.php">→ Aller à la page de connexion</a></div><?php endif; ?>
  </div>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
