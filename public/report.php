<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'historique';

ds_require_login($auth, $dbError);

if ($dbError !== null) {
    ds_flash_set('error', "Base de données injoignable : $dbError");
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? '';
$store = new ReportStore($config['reports_dir']);
$report = $store->load($id);

if ($report === null) {
    ds_flash_set('error', "Rapport introuvable ou identifiant invalide.");
    header('Location: historique.php');
    exit;
}

$currentUser = $auth->currentUser();
if (isset($report['user_id']) && (int) $report['user_id'] !== (int) $currentUser['id']) {
    ds_flash_set('error', "Ce rapport appartient à un autre compte.");
    header('Location: historique.php');
    exit;
}

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="deepshield_report_' . e($id) . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isError = ($report['status'] ?? 'error') !== 'ok';
$video = $report['video'] ?? null;
$audio = $report['audio'] ?? null;
$global = $report['global'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
  <div class="app-page-head">
      <h1>Rapport d'analyse</h1>
      <p><a class="btn-ghost" href="historique.php">← Retour à l'historique</a></p>
  </div>

  <div class="dash-grid">
    <?php if ($isError): ?>
      <section class="panel" style="grid-column: 1 / -1;">
        <h2>Échec de l'analyse</h2>
        <p class="error"><?= e($report['error'] ?? 'Erreur inconnue.') ?></p>
        <p class="muted" style="margin-top:10px;">
          Conformément à l'exigence « jamais d'échec silencieux », l'erreur exacte du moteur
          d'analyse est affichée ci-dessus plutôt que masquée.
        </p>
        <a class="btn-primary" style="margin-top:18px;" href="dashboard.php">Réessayer</a>
      </section>
    <?php else: ?>
      <section class="panel" style="grid-column: 1 / -1;">
        <div class="verdict-header">
          <div>
            <h2>Verdict global</h2>
            <p class="muted"><?= e($global['modalities_used'] ? implode(' + ', array_map('ucfirst', $global['modalities_used'])) : '—') ?> analysée(s)</p>
          </div>
          <span class="badge badge-large <?= ds_verdict_class($global['verdict'] ?? '') ?>">
            <?= e(ds_verdict_label($global['verdict'] ?? '')) ?>
          </span>
        </div>

        <?php if (isset($global['confidence_real_percent']) && $global['confidence_real_percent'] !== null): ?>
          <div class="gauge">
            <div class="gauge-bar"><div class="gauge-fill" style="width: <?= (float) $global['confidence_real_percent'] ?>%"></div></div>
            <p class="gauge-label"><?= number_format((float) $global['confidence_real_percent'], 1) ?>% de confiance « réel »</p>
          </div>
        <?php endif; ?>

        <dl class="meta-list">
          <div><dt>Généré le</dt><dd><?= e(str_replace('T', ' ', substr($report['generated_at'] ?? '', 0, 19))) ?> UTC</dd></div>
          <div><dt>Temps de traitement</dt><dd><?= e((string) ($report['elapsed_seconds'] ?? '—')) ?> s</dd></div>
          <div><dt>Moteur</dt><dd><?= e($report['params']['engine'] ?? '—') ?><?= ($report['params']['engine'] ?? '') === 'mock' ? ' (démonstration)' : '' ?></dd></div>
          <div><dt>Seuil de décision</dt><dd><?= e((string) ($report['params']['threshold'] ?? '—')) ?>%</dd></div>
        </dl>

        <div class="actions">
          <a class="btn-primary" href="report.php?id=<?= e($id) ?>&format=json">Exporter le rapport (JSON)</a>
          <button class="btn-secondary" onclick="window.print()">Imprimer / PDF</button>
        </div>
      </section>

      <?php if ($video): ?>
      <section class="panel">
        <h2>Modalité vidéo — <?= e($video['filename']) ?></h2>
        <dl class="meta-list">
          <div><dt>Verdict vidéo</dt><dd><span class="badge <?= ds_verdict_class($video['verdict']) ?>"><?= e($video['verdict']) ?></span></dd></div>
          <div><dt>Score « réel »</dt><dd><?= number_format((float) $video['avg_real'], 2) ?>%</dd></div>
          <div><dt>Score « deepfake »</dt><dd><?= number_format((float) $video['avg_fake'], 2) ?>%</dd></div>
          <div><dt>Frames analysées</dt><dd><?= (int) $video['n_frames_analyzed'] ?> (<?= (int) $video['n_frames_skipped'] ?> ignorée(s))</dd></div>
        </dl>

        <h2 style="font-size:0.95em;margin-top:20px;">Explicabilité — frames les plus suspectes</h2>
        <p class="muted">Les frames au score « réel » le plus bas, celles qui font le plus pencher le verdict.</p>
        <table class="table">
          <thead><tr><th>Frame</th><th>Réel</th><th>Deepfake</th><th></th></tr></thead>
          <tbody>
            <?php foreach (($video['frames_sorted_by_suspicion'] ?? []) as $f): ?>
              <tr>
                <td><?= e($f['file']) ?></td>
                <td><?= number_format((float) $f['score_real'], 1) ?>%</td>
                <td><?= number_format((float) $f['score_deepfake'], 1) ?>%</td>
                <td class="bar-cell"><div class="mini-bar"><div class="mini-bar-fill" style="width: <?= (float) $f['score_real'] ?>%"></div></div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <details class="all-frames">
          <summary>Voir le détail des <?= (int) $video['n_frames_analyzed'] ?> frames analysées</summary>
          <table class="table">
            <thead><tr><th>#</th><th>Fichier</th><th>Réel</th><th>Suspecte&nbsp;?</th></tr></thead>
            <tbody>
              <?php foreach (($video['frames'] ?? []) as $f): ?>
                <tr class="<?= !empty($f['suspect']) ? 'row-suspect' : '' ?>">
                  <td><?= (int) $f['index'] ?></td>
                  <td><?= e($f['file']) ?></td>
                  <td><?= number_format((float) $f['score_real'], 1) ?>%</td>
                  <td><?= !empty($f['suspect']) ? 'Oui' : 'Non' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>
      </section>
      <?php endif; ?>

      <?php if ($audio): ?>
      <section class="panel">
        <h2>Modalité audio — <?= e($audio['filename']) ?></h2>
        <?php if (($audio['status'] ?? '') === 'non_implemente'): ?>
          <p class="notice">🔧 <?= e($audio['message']) ?></p>
        <?php else: ?>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
            <span class="badge <?= ds_verdict_class($audio['verdict']) ?>" style="font-size:1em;"><?= e($audio['verdict']) ?></span>
            <span class="muted">Score « réel » : <strong style="color:var(--text-main)"><?= number_format((float) $audio['avg_real'], 1) ?>%</strong></span>
          </div>
          <div class="mini-bar" style="height:9px;margin-bottom:14px;">
            <div class="mini-bar-fill" style="width: <?= (float) $audio['avg_real'] ?>%"></div>
          </div>
          <?php if (!empty($audio['features'])): ?>
            <table class="table">
              <thead><tr><th>Descripteur</th><th>Valeur</th></tr></thead>
              <tbody>
                <tr><td>Variance MFCC</td><td><?= e((string) $audio['features']['mfcc_variance']) ?></td></tr>
                <tr><td>Platitude spectrale</td><td><?= e((string) $audio['features']['spectral_flatness']) ?></td></tr>
                <tr><td>Taux de passage par zéro</td><td><?= e((string) $audio['features']['zero_crossing_rate']) ?></td></tr>
              </tbody>
            </table>
          <?php endif; ?>
          <?php if (!empty($audio['engine_note'])): ?>
            <p class="disclaimer">ℹ <?= e($audio['engine_note']) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
