<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'historique';

$store = new ReportStore($config['reports_dir']);
$all = $store->listRecent(200);

$filter = $_GET['verdict'] ?? '';
if ($filter !== '') {
    $all = array_values(array_filter($all, fn($r) => $r['verdict'] === $filter));
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
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>Historique des analyses</h1>
      <p>Toutes les analyses réalisées en mode démonstration sur cet environnement.</p>
    </div>

    <div class="panel" style="margin-bottom:18px;">
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="historique.php" class="btn-ghost <?= $filter === '' ? 'active' : '' ?>">Tous</a>
        <a href="historique.php?verdict=RÉEL" class="btn-ghost">RÉEL</a>
        <a href="historique.php?verdict=SUSPECT" class="btn-ghost">SUSPECT</a>
        <a href="historique.php?verdict=DEEPFAKE" class="btn-ghost">DEEPFAKE</a>
      </div>
    </div>

    <section class="panel">
      <?php if (empty($all)): ?>
        <div class="empty-state">
          <div class="emoji">🗂️</div>
          <p>Aucune analyse ne correspond à ce filtre.</p>
          <a href="analyser.php" class="btn-primary" style="margin-top:14px;">Lancer une analyse</a>
        </div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Date</th><th>Fichier</th><th>Verdict</th><th>Statut</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($all as $r): ?>
              <tr>
                <td><?= e(str_replace('T', ' ', substr($r['generated_at'], 0, 19))) ?></td>
                <td><?= e($r['filename']) ?></td>
                <td><span class="badge <?= ds_verdict_class($r['verdict']) ?>"><?= e($r['verdict']) ?></span></td>
                <td><?= $r['status'] === 'ok' ? 'OK' : '<span style="color:var(--danger)">Erreur</span>' ?></td>
                <td><a class="btn-ghost" href="report.php?id=<?= e($r['id']) ?>">Voir le rapport</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
