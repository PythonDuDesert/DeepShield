<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
$navActive = 'profile';

ds_require_login($auth, $dbError);

$roleLabels = [0 => 'Administrateur', 1 => 'Utilisateur premium', 2 => 'Utilisateur'];

$infoError = null;
$passwordError = null;
$assistError = null;
$assistSuccess = false;
$subjectValue = '';
$messageValue = '';

if ($dbError === null) {
    $currentUser = $auth->currentUser();
    $userId = (int) $currentUser['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if (!ds_csrf_verify($_POST['csrf_token'] ?? null)) {
            ds_flash_set('error', "Requête invalide ou expirée, merci de réessayer.");
            header('Location: profile.php');
            exit;
        }

        // ── Mise à jour des informations personnelles ──
        if ($action === 'update_info') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $before = $stmt->fetch();

            if ($firstName === '' || $lastName === '') {
                $infoError = "Merci de renseigner votre prénom et votre nom.";
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $infoError = "Adresse e-mail invalide.";
            } elseif (strlen($email) > 50) {
                $infoError = "Adresse e-mail trop longue (50 caractères maximum).";
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
                $stmt->execute(['email' => $email, 'id' => $userId]);
                if ($stmt->fetch() !== false) {
                    $infoError = "Cette adresse e-mail est déjà utilisée par un autre compte.";
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, updated_at = NOW() WHERE id = :id'
                    );
                    $stmt->execute([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'id' => $userId,
                    ]);

                    $changes = [];
                    if ($before['email'] !== $email) {
                        $changes['email'] = ['old' => $before['email'], 'new' => $email];
                    }
                    if ($before['first_name'] !== $firstName) {
                        $changes['first_name'] = ['old' => $before['first_name'], 'new' => $firstName];
                    }
                    if ($before['last_name'] !== $lastName) {
                        $changes['last_name'] = ['old' => $before['last_name'], 'new' => $lastName];
                    }
                    if (!empty($changes)) {
                        log_profile_updated($userId, $email, $changes); // LOGS
                    }

                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;

                    ds_flash_set('success', "Vos informations ont été mises à jour.");
                    header('Location: profile.php#informations');
                    exit;
                }
            }
        }

        // ── Changement de mot de passe ──
        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            $stmt = $pdo->prepare('SELECT password_hash, email FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch();

            if ($row === false || !password_verify($current, $row['password_hash'])) {
                $passwordError = "Le mot de passe actuel est incorrect.";
            } elseif ($new !== $confirm) {
                $passwordError = "Les deux mots de passe ne correspondent pas.";
            } elseif (strlen($new) < 8 || strlen($new) > 72) {
                $passwordError = "Le mot de passe doit contenir entre 8 et 72 caractères.";
            } elseif (!preg_match('/[a-z]/', $new) || !preg_match('/[A-Z]/', $new)
                || !preg_match('/[0-9]/', $new) || !preg_match('/[^a-zA-Z0-9]/', $new)) {
                $passwordError = "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.";
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
                $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]);
                log_password_changed($userId, $row['email']); // LOGS

                ds_flash_set('success', "Votre mot de passe a été modifié.");
                header('Location: profile.php#securite');
                exit;
            }
        }

        // ── Nouvelle demande d'assistance ──
        if ($action === 'submit_assistance') {
            $subjectValue = trim($_POST['subject'] ?? '');
            $messageValue = trim($_POST['message'] ?? '');

            if ($subjectValue === '' || $messageValue === '') {
                $assistError = "Merci de renseigner un sujet et un message.";
            } elseif (mb_strlen($subjectValue) > 255) {
                $assistError = "Le sujet est trop long (255 caractères maximum).";
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO assistance (user_id, first_name, last_name, email, role, subject, message, date_submission, status)
                     VALUES (:user_id, :first_name, :last_name, :email, :role, :subject, :message, NOW(), :status)'
                );
                $stmt->execute([
                    'user_id' => $currentUser['id'],
                    'first_name' => $currentUser['first_name'],
                    'last_name' => $currentUser['last_name'],
                    'email' => $currentUser['email'],
                    'role' => $currentUser['role'],
                    'subject' => $subjectValue,
                    'message' => $messageValue,
                    'status' => 'nouveau',
                ]);
                $assistSuccess = true;
                $subjectValue = '';
                $messageValue = '';
            }
        }
    }
}

$flash = ds_flash_get();
$csrfToken = ds_csrf_token();
$profileUser = null;
$myTickets = [];
if ($dbError === null) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $profileUser = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM assistance WHERE user_id = :user_id ORDER BY date_submission DESC LIMIT 20');
    $stmt->execute(['user_id' => $userId]);
    $myTickets = $stmt->fetchAll();
}

$assistStatusLabels = ['nouveau' => 'Nouveau', 'lu' => 'Lu', 'resolu' => 'Résolu', 'fermé' => 'Fermé'];
$assistStatusBadge = ['nouveau' => 'badge-warn', 'lu' => 'badge-neutral', 'resolu' => 'badge-ok', 'fermé' => 'badge-neutral'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon profil — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/bouclier.ico" type="image/x-icon">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <?php if ($dbError !== null): ?>
      <div class="app-page-head">
        <h1>Mon profil</h1>
      </div>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <div class="app-page-head">
      <div class="profile-head">
        <span class="profile-avatar-lg"><?= e(strtoupper(substr($profileUser['first_name'], 0, 1) . substr($profileUser['last_name'], 0, 1))) ?></span>
        <div>
          <h1><?= e($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?></h1>
          <p><?= e($profileUser['email']) ?></p>
          <span class="role-pill"><?= e($roleLabels[(int) $profileUser['role']] ?? 'Utilisateur') ?></span>
        </div>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="notice" style="margin-bottom:18px;<?= $flash['type'] === 'error' ? 'border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <nav class="page-tabs">
      <button type="button" class="page-tab active" data-tab="informations" onclick="switchPageTab('informations', this)">
        🧑 Informations
      </button>
      <button type="button" class="page-tab" data-tab="securite" onclick="switchPageTab('securite', this)">
        🔒 Sécurité
      </button>
      <button type="button" class="page-tab" data-tab="assistance" onclick="switchPageTab('assistance', this)">
        💬 Assistance <?php if (!empty($myTickets)): ?><span class="tab-count"><?= count($myTickets) ?></span><?php endif; ?>
      </button>
    </nav>

    <!-- ONGLET : Informations personnelles -->
    <div id="tab-informations" class="page-tab-panel active">
      <section class="panel">
        <h2>Informations personnelles</h2>
        <p class="muted">Ces informations sont utilisées pour vous identifier sur DeepShield.</p>

        <?php if ($infoError): ?>
          <p class="error" style="margin-top:16px;"><?= e($infoError) ?></p>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="action" value="update_info">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <div class="field-row">
            <div class="field">
              <label for="first_name">Prénom</label>
              <input type="text" id="first_name" name="first_name" required maxlength="50" value="<?= e($profileUser['first_name']) ?>">
            </div>
            <div class="field">
              <label for="last_name">Nom</label>
              <input type="text" id="last_name" name="last_name" required maxlength="50" value="<?= e($profileUser['last_name']) ?>">
            </div>
          </div>
          <div class="field">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required maxlength="50" value="<?= e($profileUser['email']) ?>">
          </div>
          <button type="submit" class="btn-primary" style="margin-top:16px;">Enregistrer les modifications</button>
        </form>
      </section>
    </div>

    <!-- ONGLET : Sécurité -->
    <div id="tab-securite" class="page-tab-panel">
      <section class="panel">
        <h2>Changer le mot de passe</h2>
        <p class="muted">Au moins 8 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</p>

        <?php if ($passwordError): ?>
          <p class="error" style="margin-top:16px;"><?= e($passwordError) ?></p>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="action" value="change_password">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <div class="field">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="field-row">
            <div class="field">
              <label for="new_password">Nouveau mot de passe</label>
              <input type="password" id="new_password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>
            <div class="field">
              <label for="confirm_password">Confirmer le mot de passe</label>
              <input type="password" id="confirm_password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>
          </div>
          <button type="submit" class="btn-primary" style="margin-top:16px;">Mettre à jour le mot de passe</button>
        </form>
      </section>
    </div>

    <!-- ONGLET : Assistance -->
    <div id="tab-assistance" class="page-tab-panel">
      <div class="dash-grid">
        <section class="panel">
          <h2>Nouvelle demande</h2>
          <p class="muted">Réponse habituelle sous 48h ouvrées. Pour la documentation générale, consultez d'abord la <a href="faq.php" style="color:var(--cyan-bright)">FAQ</a>.</p>

          <?php if ($assistSuccess): ?>
            <div class="notice" style="margin-top:16px;border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);">✅ Votre demande a bien été envoyée. Vous pouvez suivre son statut ci-contre.</div>
          <?php endif; ?>
          <?php if ($assistError): ?>
            <p class="error" style="margin-top:16px;"><?= e($assistError) ?></p>
          <?php endif; ?>

          <form method="post">
            <input type="hidden" name="action" value="submit_assistance">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="field">
              <label for="subject">Sujet</label>
              <input type="text" id="subject" name="subject" required maxlength="255"
                placeholder="Ex : erreur lors de l'analyse d'une vidéo"
                value="<?= e($subjectValue) ?>">
            </div>
            <div class="field">
              <label for="message">Message</label>
              <textarea id="message" name="message" required rows="6"
                placeholder="Décrivez le problème rencontré ou votre question, avec le plus de détails possible."><?= e($messageValue) ?></textarea>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:16px;">Envoyer la demande</button>
          </form>
        </section>

        <section class="panel">
          <h2>Vos demandes récentes</h2>
          <?php if (empty($myTickets)): ?>
            <div class="empty-state"><p>Aucune demande envoyée pour le moment.</p></div>
          <?php else: ?>
            <?php foreach ($myTickets as $t): ?>
              <div class="assist-card status-<?= e($t['status']) ?>" style="padding:14px 16px;">
                <div class="assist-card-head">
                  <div>
                    <h3 style="font-size:0.9em;"><?= e($t['subject']) ?></h3>
                    <div class="assist-meta"><span><?= e(substr((string) $t['date_submission'], 0, 16)) ?></span></div>
                  </div>
                  <span class="badge <?= $assistStatusBadge[$t['status']] ?? 'badge-neutral' ?>"><?= e($assistStatusLabels[$t['status']] ?? $t['status']) ?></span>
                </div>
                <?php if (!empty($t['admin_notes'])): ?>
                  <div class="assist-admin-notes"><strong>Réponse de l'équipe</strong><?= nl2br(e($t['admin_notes'])) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>
    </div>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
<script src="assets/js/tabs.js"></script>
</body>
</html>
