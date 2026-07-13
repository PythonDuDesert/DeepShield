<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'dashboard';

ds_require_login($auth, $dbError);

$flash = ds_flash_get();
$stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'score_moyen' => 0.0];
$recent = [];
$user = null;

if ($dbError === null) {
    $user = $auth->currentUser();
    $videos = new VideoRepository($pdo);
    $stats = $videos->statsByUser((int) $user['id']);
    $recent = $videos->listByUser((int) $user['id'], 6);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — DeepShield</title>
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
      <h1>Tableau de bord</h1>
      <p>Vue d'ensemble de vos analyses<?= $user ? ', ' . e($user['first_name']) : '' ?>.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice" style="margin-bottom:22px;"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php elseif ($flash): ?>
      <div class="notice" style="margin-bottom:22px;"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-logo"><img src="assets/images/fiche-devaluation.png" alt="fiche-devaluation.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['total'] ?></div>
          <div class="stat-caption">Analyses effectuées</div>
        </div>
      </div>
      <div class="stat-card stat-ok">
        <div class="stat-logo"><img src="assets/images/coche.png" alt="coche.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['reel'] ?></div>
          <div class="stat-caption">Vidéos jugées réelles</div>
        </div>
      </div>
      <div class="stat-card stat-warn">
        <div class="stat-logo"><img src="assets/images/avertissement.png" alt="avertissement.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['suspect'] ?></div>
          <div class="stat-caption">Verdicts suspects</div>
        </div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-logo"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['deepfake'] ?></div>
          <div class="stat-caption">Vidéos jugées Deepfakes</div>
        </div>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head">
        <h2>Lancer une analyse</h2>
        <a href="analyser.php" class="btn-primary">Nouvelle analyse</a>
      </div>
      <div class="panel">
        <p class="muted">
          Déposez une vidéo et/ou un audio depuis la page <a href="analyser.php" style="color:var(--cyan-bright)">Nouvelle analyse</a>.
          Score de confiance « réel » moyen sur vos analyses&nbsp;: <strong style="color:var(--text-main)"><?= $stats['total'] > 0 ? number_format((float) $stats['score_moyen'], 1) . '%' : '—' ?></strong>.
        </p>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head">
        <h2>Activité récente</h2>
        <a href="historique.php" class="btn-ghost">Voir tout l'historique →</a>
      </div>
      <div class="panel">
        <?php if (empty($recent)): ?>
          <div class="empty-state">
            <p>Aucune analyse pour le moment.</p>
            <a href="analyser.php" class="btn-primary" style="margin-top:14px;">Lancer votre première analyse</a>
          </div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Date</th><th>Fichier</th><th>Verdict</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recent as $r): $verdict = VideoRepository::verdictFromExplinations($r['explinations']); ?>
                <tr>
                  <td><?= e(substr((string) $r['uploaded_at'], 0, 16)) ?></td>
                  <td><?= e($r['video_name']) ?></td>
                  <td><span class="badge <?= ds_verdict_class($verdict) ?>"><?= e($verdict) ?></span></td>
                  <td><a class="btn-ghost" href="report.php?id=<?= (int) $r['id'] ?>">Voir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <p class="disclaimer">⚠ Rappel : le score DeepShield est une aide à la décision destinée à une équipe de conformité. Il ne remplace jamais un contrôle humain.</p>
  </main>
</div>

<script src="assets/js/site.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
