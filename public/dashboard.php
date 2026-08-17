<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/logs/logs.php';
$navActive = 'dashboard';

ds_require_login($auth, $dbError);

$role = $_SESSION['role'];
$flash = ds_flash_get();
$stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'score_moyen' => 0.0];
$recent = [];
$user = null;

if ($dbError === null) {
    $user = $auth->currentUser();

    /*
     * ==========================================================
     * VIDÉOS
     * ==========================================================
     */
    $stmtVideo = $pdo->prepare(
        'SELECT
            id,
            video_name AS file_name,
            file_size,
            uploaded_at,
            score,
            explinations,
            "VIDÉO" AS media_type
         FROM videos
         WHERE user_id = :user_id
         ORDER BY uploaded_at DESC'
    );

    $stmtVideo->execute([
        ':user_id' => (int) $user['id']
    ]);

    $videos = $stmtVideo->fetchAll(PDO::FETCH_ASSOC);

    /*
     * ==========================================================
     * AUDIOS
     * ==========================================================
     */
    $stmtAudio = $pdo->prepare(
        'SELECT
            id,
            audio_name AS file_name,
            file_size,
            uploaded_at,
            score,
            explinations,
            "AUDIO" AS media_type
         FROM audios
         WHERE user_id = :user_id
         ORDER BY uploaded_at DESC'
    );

    $stmtAudio->execute([
        ':user_id' => (int) $user['id']
    ]);

    $audios = $stmtAudio->fetchAll(PDO::FETCH_ASSOC);

    /*
     * ==========================================================
     * FUSION
     * ==========================================================
     */
    $allAnalyses = array_merge($videos, $audios);

    /*
     * Tri par date décroissante.
     */

    usort(
        $allAnalyses,
        static function (array $a, array $b): int {
            return strcmp(
                (string) $b['uploaded_at'],
                (string) $a['uploaded_at']
            );
        }
    );

    /*
     * ==========================================================
     * STATISTIQUES
     * ==========================================================
     */

    $stats = [
        'total'       => count($allAnalyses),
        'reel'        => 0,
        'suspect'     => 0,
        'deepfake'    => 0,
        'score_moyen' => 0.0,
    ];

    $scoreTotal = 0.0;

    foreach ($allAnalyses as $analysis) {

        $verdict = VideoRepository::verdictFromExplinations(
            (string) $analysis['explinations']
        );

        switch ($verdict) {
            case 'RÉEL':
                $stats['reel']++;
                break;
            case 'SUSPECT':
                $stats['suspect']++;
                break;
            case 'DEEPFAKE':
                $stats['deepfake']++;
                break;
        }

        $scoreTotal += (float) ($analysis['score'] ?? 0);
    }

    if ($stats['total'] > 0) {
        $stats['score_moyen'] = round(
            $scoreTotal / $stats['total'],
            1
        );
    }

    /*
     * ==========================================================
     * 6 ANALYSES LES PLUS RÉCENTES
     * ==========================================================
     */

    $recent = array_slice($allAnalyses, 0, 6);
}

// Récupérer le message
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';

// Supprimer le message après l'avoir récupéré
unset($_SESSION['message']);
unset($_SESSION['message_type']);

// Terminal
// Par défaut : jour courant.
$today = date('d-m-Y');
$requested_day = '';
if (!empty($_GET['log_day'])) {
    $candidate = trim($_GET['log_day']);
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $candidate)) {
        $requested_day = $candidate;
    }
}
$log_day = $requested_day ?: $today;
$log_filename = 'logs-' . $log_day . '.json';
 
// Lecture via la fonction dédiée de logs.php
$logs_for_terminal = function_exists('read_logs_by_day') ? read_logs_by_day($log_day, 50) : [];
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
<?php include 'flash_message.php'; ?>
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
          <div class="stat-caption">Analyses jugées réelles</div>
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
          <div class="stat-caption">Analyses jugées Deepfakes</div>
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
          <table class="table" style="font-size: medium;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Fichier</th>
                    <th>Verdict</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
              <?php
              $verdict = VideoRepository::verdictFromExplinations(
                  (string) $r['explinations']
              );
              ?>
              <tr>
                  <td>
                      <?= e(substr((string) $r['uploaded_at'], 0, 16)) ?>
                  </td>
                  <td>
                      <span class="badge">
                          <?= e($r['media_type']) ?>
                      </span>
                  </td>
                  <td>
                      <?= e($r['file_name']) ?>
                  </td>
                  <td>
                      <span class="badge <?= ds_verdict_class($verdict) ?>">
                          <?= e($verdict) ?>
                      </span>
                  </td>
                  <td>
                      <?php
                          $reportId = ($r['media_type'] === 'VIDÉO' ? 'video_' : 'audio_') . (int) $r['id'];
                      ?>
                      <a class="btn-ghost" href="report.php?id=<?= urlencode($reportId) ?>">Voir le rapport</a>
                  </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <p class="disclaimer">Rappel : le score DeepShield est une aide à la décision. Il ne remplace jamais un contrôle humain.</p>

    <br><br>
    <?php include 'logs_terminal.php'; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>