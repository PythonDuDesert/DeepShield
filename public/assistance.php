<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'assistance';

ds_require_login($auth, $dbError);

$formError = null;
$formSuccess = false;
$subjectValue = '';
$messageValue = '';

if ($dbError === null) {
    $currentUser = $auth->currentUser();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subjectValue = trim($_POST['subject'] ?? '');
        $messageValue = trim($_POST['message'] ?? '');

        if ($subjectValue === '' || $messageValue === '') {
            $formError = "Merci de renseigner un sujet et un message.";
        } elseif (mb_strlen($subjectValue) > 255) {
            $formError = "Le sujet est trop long (255 caractères maximum).";
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
            $formSuccess = true;
            $subjectValue = '';
            $messageValue = '';
        }
    }
}

$recentTickets = [];
if ($dbError === null) {
    $stmt = $pdo->prepare('SELECT subject, date_submission, status FROM assistance WHERE user_id = :user_id ORDER BY date_submission DESC LIMIT 10');
    $stmt->execute(['user_id' => $currentUser['id']]);
    $recentTickets = $stmt->fetchAll();
}

$statusLabels = ['nouveau' => 'Nouveau', 'lu' => 'Lu', 'resolu' => 'Résolu', 'fermé' => 'Fermé'];
$statusClass = ['nouveau' => 'badge-warn', 'lu' => 'badge-neutral', 'resolu' => 'badge-ok', 'fermé' => 'badge-neutral'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assistance — DeepShield</title>
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
      <h1>Assistance</h1>
      <p>Signalez un problème ou posez une question à l'équipe DeepShield.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <div class="dash-grid">
      <section class="panel">
        <h2>Nouvelle demande</h2>
        <p class="muted">Réponse habituelle sous 48h ouvrées. Pour la documentation générale, consultez d'abord la <a href="faq.php" style="color:var(--cyan-bright)">FAQ</a>.</p>

        <?php if ($formSuccess): ?>
          <div class="notice" style="margin-top:16px;border-color:var(--ok);background:rgba(52,211,153,0.08);color:var(--ok);">✅ Votre demande a bien été envoyée. Vous pouvez suivre son statut ci-contre.</div>
        <?php endif; ?>
        <?php if ($formError): ?>
          <p class="error" style="margin-top:16px;"><?= e($formError) ?></p>
        <?php endif; ?>

        <form method="post">
          <div class="field">
            <label for="subject">Sujet</label>
            <input type="text" id="subject" name="subject" required maxlength="255"
              placeholder="Ex : erreur lors de l'analyse d'une vidéo"
              value="<?= e($subjectValue) ?>"
              style="width:100%;padding:11px 13px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.09);color:var(--text-main);font-family:var(--font-body);font-size:0.93em;">
          </div>
          <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" required rows="6"
              placeholder="Décrivez le problème rencontré ou votre question, avec le plus de détails possible."
              style="width:100%;padding:11px 13px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.09);color:var(--text-main);font-family:var(--font-body);font-size:0.93em;resize:vertical;"><?= e($messageValue) ?></textarea>
          </div>
          <button type="submit" class="btn-primary" style="margin-top:16px;">Envoyer la demande</button>
        </form>
      </section>

      <section class="panel">
        <h2>Vos demandes récentes</h2>
        <?php if (empty($recentTickets)): ?>
          <div class="empty-state"><p>Aucune demande envoyée pour le moment.</p></div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Sujet</th><th>Statut</th></tr></thead>
            <tbody>
              <?php foreach ($recentTickets as $t): ?>
                <tr>
                  <td><?= e($t['subject']) ?><br><span class="muted"><?= e(substr((string) $t['date_submission'], 0, 16)) ?></span></td>
                  <td><span class="badge <?= $statusClass[$t['status']] ?? 'badge-neutral' ?>"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </div>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
