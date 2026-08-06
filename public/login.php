<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
$navActive = 'login';

// Si déjà connecté, direction le dashboard.
if ($dbError === null && $auth !== null && $auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$loginError = null;
$registerError = null;
$registerSuccess = false;
$sessionExpired = ($_GET['expired'] ?? '') === '1';
$initialTab = ($_GET['tab'] ?? '') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbError === null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $initialTab = 'login';
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = $auth->login($email, $password, ds_client_ip());
        if ($result['success']) {
            $user = $auth->currentUser(); // session déjà remplie par login()
            log_login($user['id'], $email, $user['role']);
            header('Location: dashboard.php');
            exit;
        }

        log_login_failed($email);
        $loginError = $result['error'];
    }

    if ($action === 'register') {
        $initialTab = 'register';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $result = $auth->register($email, (string) ($_POST['password'] ?? ''), $first_name, $last_name);
        if ($result['success']) {
            log_profile_created($result['user_id'], $email, $first_name, $last_name);
            $registerSuccess = true;
            $initialTab = 'login';
        } else {
            $registerError = $result['error'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/bouclier.ico" type="image/x-icon">
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
        <h1>Bienvenue sur DeepShield</h1>
        <p>Espace de conformité KYC — accès anti-usurpation</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="auth-notice" style="margin-top:0;">
        <span class="icon">🛑</span>
        <span>Base de données injoignable pour le moment. <?= e($dbError) ?></span>
      </div>
    <?php else: ?>

      <?php if ($sessionExpired): ?>
        <div class="auth-notice" style="margin-top:0;">
          <span class="icon">⏱️</span>
          <span>Session expirée pour inactivité. Merci de vous reconnecter.</span>
        </div>
      <?php endif; ?>

      <?php if ($registerSuccess): ?>
        <div class="auth-notice" style="border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);margin-top:0;">
          <span class="icon">✅</span>
          <span>Compte créé avec succès. Vous pouvez maintenant vous connecter ci-dessous.</span>
        </div>
      <?php endif; ?>

      <div class="auth-tabs">
          <div class="auth-tab" id="tab-login" onclick="switchTab('login')">Connexion</div>
          <div class="auth-tab" id="tab-register" onclick="switchTab('register')">Inscription</div>
      </div>

      <!-- Connexion -->
      <form class="auth-panel" id="panel-login" method="post" action="login.php">
          <input type="hidden" name="action" value="login">
          <?php if ($loginError): ?><p class="error" style="margin-bottom:10px;"><?= e($loginError) ?></p><?php endif; ?>
          <div class="auth-field">
              <label for="login-email">Adresse e-mail</label>
              <input type="email" id="login-email" name="email" placeholder="prenom.nom@organisme.fr" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="auth-field">
              <label for="login-password">Mot de passe</label>
              <input type="password" id="login-password" name="password" placeholder="••••••••" autocomplete="current-password" required>
          </div>
          <button type="submit" class="auth-submit">Se connecter</button>
      </form>

      <!-- Inscription -->
      <form class="auth-panel" id="panel-register" method="post" action="login.php">
          <input type="hidden" name="action" value="register">
          <?php if ($registerError): ?><p class="error" style="margin-bottom:10px;"><?= e($registerError) ?></p><?php endif; ?>
          <div class="auth-field">
              <label for="reg-first-name">Prénom</label>
              <input type="text" id="reg-first-name" name="first_name" placeholder="Jean" autocomplete="given-name" required value="<?= e($_POST['first_name'] ?? '') ?>">
          </div>
          <div class="auth-field">
              <label for="reg-last-name">Nom</label>
              <input type="text" id="reg-last-name" name="last_name" placeholder="Dupont" autocomplete="family-name" required value="<?= e($_POST['last_name'] ?? '') ?>">
          </div>
          <div class="auth-field">
              <label for="reg-email">Adresse e-mail</label>
              <input type="email" id="reg-email" name="email" placeholder="prenom.nom@organisme.fr" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="auth-field">
              <label for="reg-password">Mot de passe (min. 8 caractères, avec majuscule, minuscule, chiffre et caractère spécial)</label>
              <input type="password" id="reg-password" name="password" placeholder="••••••••" autocomplete="new-password" minlength="8" maxlength="72"
                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,72}"
                title="8 à 72 caractères, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial." required>
          </div>
          <button type="submit" class="auth-submit">Créer mon compte</button>
      </form>

    <?php endif; ?>

    <div class="auth-back"><a href="index.php">← Retour à l'accueil</a></div>
  </div>
</div>

<script src="assets/js/site.js"></script>
<script>
function switchTab(tab) {
    document.getElementById('tab-login').classList.toggle('active', tab === 'login');
    document.getElementById('tab-register').classList.toggle('active', tab === 'register');
    document.getElementById('panel-login').classList.toggle('active', tab === 'login');
    document.getElementById('panel-register').classList.toggle('active', tab === 'register');
    history.replaceState(null, '', tab === 'register' ? 'login.php?tab=register' : 'login.php');
}
switchTab('<?= $initialTab === 'register' ? 'register' : 'login' ?>');
</script>
</body>
</html>
