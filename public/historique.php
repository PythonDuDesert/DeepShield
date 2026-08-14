<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'historique';

ds_require_login($auth, $dbError);

$flash = ds_flash_get();
$filter = $_GET['verdict'] ?? '';
$all = [];
if ($dbError === null) {
    $user = $auth->currentUser();
    $videos = new VideoRepository($pdo);
    $all = $videos->listByUser((int) $user['id'], 200, $filter);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historique — DeepShield</title>
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
      <h1>Historique des analyses</h1>
      <p>Toutes les analyses réalisées sur votre compte.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <?php if ($flash): ?>
      <div class="notice" style="margin-bottom:18px;border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> <?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="panel" style="margin-bottom:18px;">
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="historique.php" class="btn-ghost"><?= $filter === '' ? '● ' : '' ?>Tous</a>
        <a href="historique.php?verdict=R%C3%89EL" class="btn-ghost"><?= $filter === 'RÉEL' ? '● ' : '' ?>RÉEL</a>
        <a href="historique.php?verdict=SUSPECT" class="btn-ghost"><?= $filter === 'SUSPECT' ? '● ' : '' ?>SUSPECT</a>
        <a href="historique.php?verdict=DEEPFAKE" class="btn-ghost"><?= $filter === 'DEEPFAKE' ? '● ' : '' ?>DEEPFAKE</a>
      </div>
    </div>

    <div class="panel">
      <?php if (empty($all)): ?>
        <div class="empty-state">
          <img src="assets/images/rechercher.png" alt="Analyser" style="width:25px; height:25px; object-fit:contain;">
          <p>Aucune analyse ne correspond à ce filtre.</p>
          <a href="analyser.php" class="btn-primary" style="margin-top:14px;">Lancer une analyse</a>
        </div>
      <?php else: ?>
        <table class="table" style="font-size: medium;">
          <thead><tr><th>Date</th><th>Fichier</th><th>Taille</th><th>Verdict</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($all as $r): $verdict = VideoRepository::verdictFromExplinations($r['explinations']); ?>
              <tr>
                <td><?= e((string) $r['uploaded_at']) ?></td>
                <td><?= e($r['video_name']) ?></td>
                <td><?= e(ds_format_bytes((int) $r['file_size'])) ?></td>
                <td><span class="badge <?= ds_verdict_class($verdict) ?>"><?= e($verdict) ?></span></td>
                <td><a class="btn-ghost" href="report.php?id=<?= (int) $r['id'] ?>">Voir le rapport</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      </div>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
