<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'dashboard';

$flash = ds_flash_get();
$store = new ReportStore($config['reports_dir']);
$stats = $store->stats();
$recent = $store->listRecent(6);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>Tableau de bord</h1>
      <p>Vue d'ensemble de l'activité de détection — accès démonstration, sans authentification pour le moment.</p>
    </div>

    <?php if ($flash): ?>
      <div class="notice" style="margin-bottom:22px;"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-emoji">🗂️</div>
        <div>
          <div class="stat-value"><?= (int) $stats['total'] ?></div>
          <div class="stat-caption">Analyses effectuées</div>
        </div>
      </div>
      <div class="stat-card stat-ok">
        <div class="stat-emoji">✅</div>
        <div>
          <div class="stat-value"><?= (int) $stats['reel'] ?></div>
          <div class="stat-caption">Vidéos jugées réelles</div>
        </div>
      </div>
      <div class="stat-card stat-warn">
        <div class="stat-emoji">⚠️</div>
        <div>
          <div class="stat-value"><?= (int) $stats['suspect'] ?></div>
          <div class="stat-caption">Verdicts suspects</div>
        </div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-emoji">🚨</div>
        <div>
          <div class="stat-value"><?= (int) $stats['deepfake'] ?></div>
          <div class="stat-caption">Deepfakes détectés</div>
        </div>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head">
        <h2>Lancer une analyse</h2>
        <a href="analyser.php" class="btn-primary">🔍 Nouvelle analyse</a>
      </div>
      <div class="panel">
        <p class="muted">
          Déposez une vidéo et/ou un audio depuis la page <a href="analyser.php" style="color:var(--cyan-bright)">Nouvelle analyse</a>.
          Temps de traitement moyen observé&nbsp;: <strong style="color:var(--text-main)"><?= $stats['temps_moyen'] > 0 ? number_format($stats['temps_moyen'], 2) . ' s' : '—' ?></strong>.
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
            <div class="emoji">🕵️</div>
            <p>Aucune analyse pour le moment.</p>
            <a href="analyser.php" class="btn-primary" style="margin-top:14px;">Lancer votre première analyse</a>
          </div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Date</th><th>Fichier</th><th>Verdict</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td><?= e(substr($r['generated_at'], 0, 16)) ?></td>
                  <td><?= e($r['filename']) ?></td>
                  <td><span class="badge <?= ds_verdict_class($r['verdict']) ?>"><?= e($r['verdict']) ?></span></td>
                  <td><a class="btn-ghost" href="report.php?id=<?= e($r['id']) ?>">Voir</a></td>
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
