<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'login';
$initialTab = ($_GET['tab'] ?? '') === 'register' ? 'register' : 'login';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — DeepShield</title>
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
        <h1>Bienvenue sur DeepShield</h1>
        <p>Espace de conformité KYC — accès anti-usurpation</p>
    </div>

    <div class="auth-tabs">
        <div class="auth-tab" id="tab-login" onclick="switchTab('login')">Connexion</div>
        <div class="auth-tab" id="tab-register" onclick="switchTab('register')">Inscription</div>
    </div>

    <!-- Connexion -->
    <form class="auth-panel" id="panel-login" onsubmit="return handleAuthSubmit(event)">
        <div class="auth-field">
            <label for="login-email">Adresse e-mail professionnelle</label>
            <input type="email" id="login-email" placeholder="prenom.nom@organisme.fr" autocomplete="email">
        </div>
        <div class="auth-field">
            <label for="login-password">Mot de passe</label>
            <input type="password" id="login-password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <button type="submit" class="auth-submit">Se connecter</button>

        <div class="auth-notice">
            <span class="icon">🔧</span>
            <span>Les comptes ne sont pas encore activés (voir planning, phases API &amp; dashboard). Utilisez l'accès démo ci-dessous pour visiter la plateforme.</span>
        </div>
    </form>

    <!-- Inscription -->
    <form class="auth-panel" id="panel-register" onsubmit="return handleAuthSubmit(event)">
        <div class="auth-field">
            <label for="reg-name">Nom complet</label>
            <input type="text" id="reg-name" placeholder="Jean Dupont" autocomplete="name">
        </div>
        <div class="auth-field">
            <label for="reg-org">Organisme</label>
            <input type="text" id="reg-org" placeholder="Nom de votre organisme" autocomplete="organization">
        </div>
        <div class="auth-field">
            <label for="reg-email">Adresse e-mail professionnelle</label>
            <input type="email" id="reg-email" placeholder="prenom.nom@organisme.fr" autocomplete="email">
        </div>
        <div class="auth-field">
            <label for="reg-password">Mot de passe</label>
            <input type="password" id="reg-password" placeholder="••••••••" autocomplete="new-password">
        </div>
        <button type="submit" class="auth-submit">Créer mon compte</button>

        <div class="auth-notice">
            <span class="icon">🔧</span>
            <span>L'inscription n'est pas encore ouverte. Utilisez l'accès démo ci-dessous pour visiter la plateforme sans créer de compte.</span>
        </div>
    </form>

    <div class="auth-divider">ou</div>
    <a href="dashboard.php" class="demo-btn">🔎 Essayer la démo (accès libre)</a>

    <div class="auth-back"><a href="index.php">← Retour à l'accueil</a></div>
  </div>
</div>

<div class="toast" id="toast"></div>

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

function showToast(message) {
    var toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 3200);
}

function handleAuthSubmit(evt) {
    evt.preventDefault();
    showToast('Authentification bientôt disponible — essayez l\'accès démo ci-dessous ⤵');
    return false;
}
</script>
</body>
</html>
