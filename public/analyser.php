<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'analyser';
ds_require_login($auth, $dbError);
$flash = ds_flash_get();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouvelle analyse — DeepShield</title>
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
      <h1>Nouvelle analyse</h1>
      <p>Déposez une vidéo et/ou un audio à faire vérifier par le moteur de détection.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice" style="margin-bottom:22px;"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php elseif ($flash): ?>
      <div class="notice" style="margin-bottom:22px;border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> <?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="dash-grid">
      <section class="panel">
        <h2>Fichiers à analyser</h2>
        <p class="muted">
          Le service fonctionne avec une seule modalité si nécessaire — le score global s'adapte
          en conséquence.
        </p>
        <form method="post" action="analyze.php" enctype="multipart/form-data" id="analyze-form">
          <div class="dropzone" id="dropzone-video">
            <label for="video_file"><strong>Vidéo</strong> (.mp4, .mov) — optionnelle</label>
            <input type="file" id="video_file" name="video_file" accept=".mp4,.mov">
          </div>
          <div class="dropzone" id="dropzone-audio">
            <label for="audio_file"><strong>Audio</strong> (.wav, .mp3) — optionnel, moteur en développement</label>
            <input type="file" id="audio_file" name="audio_file" accept=".wav,.mp3">
          </div>

          <div class="field-row">
            <div class="field">
              <label for="max_frames">Frames à analyser</label>
              <input type="number" id="max_frames" name="max_frames" min="5" max="120" value="<?= (int) $config['max_frames'] ?>">
            </div>
            <div class="field">
              <label for="threshold">Seuil de décision (% réel)</label>
              <input type="number" id="threshold" name="threshold" min="1" max="99" value="<?= (float) $config['threshold'] ?>">
            </div>
          </div>

          <label class="checkbox-row">
            <input type="checkbox" name="keep_for_retraining" value="1">
            Je consens à la conservation de ce fichier pour ré-entraînement du modèle
            (sinon, suppression automatique après analyse)
          </label>

          <p class="dash-error" id="client-error" hidden></p>
          <button type="submit" class="btn-primary" id="submit-btn" style="margin-top:18px;width:100%;justify-content:center;">Lancer l'analyse</button>
        </form>
      </section>

      <section class="panel">
        <h2>Ce que fait le moteur</h2>
        <ol style="margin:16px 0 0 18px; color:var(--text-muted); font-size:0.88em; line-height:1.9;">
          <li>Échantillonnage des frames de la vidéo</li>
          <li>Détection et recadrage du visage sur chaque frame</li>
          <li>Score réel/deepfake par frame via le modèle de classification</li>
          <li>Score global + repérage des frames les plus suspectes</li>
        </ol>
        <hr class="sep">
        <h2 style="font-size:0.95em;">Formats acceptés</h2>
        <p class="muted">Vidéo : <code>.mp4</code>, <code>.mov</code> — Audio : <code>.wav</code>, <code>.mp3</code> (moteur audio en développement).</p>
        <hr class="sep">
        <p class="muted">
          ⚠ Le score produit est une aide à la décision. Il ne remplace jamais un contrôle humain
          et ne doit pas déclencher de refus automatique.
        </p>
      </section>
    </div>
  </main>
</div>

<script src="assets/js/site.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
