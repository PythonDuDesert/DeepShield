<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'resultats';

ds_require_admin($auth, $dbError);

$flash = ds_flash_get();
$filter = $_GET['verdict'] ?? '';
$stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'score_moyen' => 0.0, 'contributeurs' => 0];
$recent = [];
$top = [];

if ($dbError === null) {
    $videos = new VideoRepository($pdo);
    $stats = $videos->statsGlobal();
    $recent = $videos->listAllWithUser(100, $filter);
    $top = $videos->topContributors(5);
}

function ds_pct(int $part, int $total): float
{
    return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Résultats globaux — DeepShield</title>
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
      <h1>Résultats globaux</h1>
      <p>Vue d'ensemble des analyses réalisées par l'ensemble des comptes DeepShield.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <?php if ($flash): ?>
      <div class="notice" style="margin-bottom:18px;"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-logo"><img src="assets/images/fiche-devaluation.png" alt="fiche-devaluation.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['total'] ?></div>
          <div class="stat-caption">Analyses (toute la plateforme)</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-logo"><img src="assets/images/user_logo.png" alt="user_logo.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['contributeurs'] ?></div>
          <div class="stat-caption">Comptes ayant analysé au moins une vidéo</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-logo"><img src="assets/images/graphiques.png" alt="graphiques.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= $stats['total'] > 0 ? number_format((float) $stats['score_moyen'], 1) . '%' : '—' ?></div>
          <div class="stat-caption">Score « réel » moyen</div>
        </div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-logo"><img src="assets/images/avertissement.png" alt="avertissement.png" width="30px"></div>
        <div>
          <div class="stat-value"><?= (int) $stats['deepfake'] ?></div>
          <div class="stat-caption">Deepfakes détectés</div>
        </div>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head">
        <h2>Répartition des verdicts</h2>
      </div>
      <div class="panel">
        <?php foreach ([['RÉEL', 'reel', 'ok'], ['SUSPECT', 'suspect', 'warn'], ['DEEPFAKE', 'deepfake', 'danger']] as [$label, $key, $tone]): ?>
          <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-size:0.85em;margin-bottom:6px;">
              <span class="badge <?= ds_verdict_class($label) ?>"><?= e($label) ?></span>
              <span class="muted"><?= (int) $stats[$key] ?> · <?= ds_pct((int) $stats[$key], (int) $stats['total']) ?>%</span>
            </div>
            <div class="mini-bar" style="height:9px;">
              <div class="mini-bar-fill" style="width: <?= ds_pct((int) $stats[$key], (int) $stats['total']) ?>%; background: var(--<?= $tone ?>);"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="section-block">
      <div class="section-block-head">
        <h2>Activité récente — toute la plateforme</h2>
      </div>
      <div class="panel" style="margin-bottom:18px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="resultats.php" class="btn-ghost"><?= $filter === '' ? '● ' : '' ?>Tous</a>
          <a href="resultats.php?verdict=R%C3%89EL" class="btn-ghost"><?= $filter === 'RÉEL' ? '● ' : '' ?>RÉEL</a>
          <a href="resultats.php?verdict=SUSPECT" class="btn-ghost"><?= $filter === 'SUSPECT' ? '● ' : '' ?>SUSPECT</a>
          <a href="resultats.php?verdict=DEEPFAKE" class="btn-ghost"><?= $filter === 'DEEPFAKE' ? '● ' : '' ?>DEEPFAKE</a>
        </div>
      </div>
      <div class="panel">
        <?php if (empty($recent)): ?>
          <div class="empty-state"><p>Aucune analyse ne correspond à ce filtre.</p></div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Date</th><th>Compte</th><th>Fichier</th><th>Verdict</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $r): $verdict = VideoRepository::verdictFromExplinations($r['explinations']); ?>
                <tr>
                  <td><?= e(substr((string) $r['uploaded_at'], 0, 16)) ?></td>
                  <td><?= e($r['u_first_name'] . ' ' . $r['u_last_name']) ?></td>
                  <td><?= e($r['video_name']) ?></td>
                  <td><span class="badge <?= ds_verdict_class($verdict) ?>"><?= e($verdict) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
