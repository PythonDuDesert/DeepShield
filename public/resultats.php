<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'resultats';
ds_require_admin($auth, $dbError);
$flash = ds_flash_get();
$filter = $_GET['verdict'] ?? '';

$stats = [
    'total'        => 0,
    'reel'         => 0,
    'suspect'      => 0,
    'deepfake'     => 0,
    'score_moyen'  => 0.0,
    'contributeurs'=> 0,
];

$recent = [];
$top = [];

if ($dbError === null) {

    /*
     * ==========================================================
     * RÉCUPÉRATION DES VIDÉOS
     * ==========================================================
     */
    $stmtVideos = $pdo->query(
        'SELECT
            v.id,
            v.user_id,
            v.video_name AS file_name,
            v.file_size,
            v.uploaded_at,
            v.score,
            v.explinations,
            u.first_name AS u_first_name,
            u.last_name AS u_last_name,
            "VIDÉO" AS media_type
         FROM videos v
         LEFT JOIN users u ON u.id = v.user_id
         ORDER BY v.uploaded_at DESC'
    );

    $videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

    /*
     * ==========================================================
     * RÉCUPÉRATION DES AUDIOS
     * ==========================================================
     */
    $stmtAudios = $pdo->query(
        'SELECT
            a.id,
            a.user_id,
            a.audio_name AS file_name,
            a.file_size,
            a.uploaded_at,
            a.score,
            a.explinations,
            u.first_name AS u_first_name,
            u.last_name AS u_last_name,
            "AUDIO" AS media_type
         FROM audios a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.uploaded_at DESC'
    );

    $audios = $stmtAudios->fetchAll(PDO::FETCH_ASSOC);

    /*
     * ==========================================================
     * FUSION VIDÉOS + AUDIOS
     * ==========================================================
     */
    $allAnalyses = array_merge($videos, $audios);

    /*
     * Tri global par date.
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
     * CALCUL DES STATISTIQUES
     * ==========================================================
     */

    $scoreTotal = 0.0;
    $contributors = [];
    foreach ($allAnalyses as $row) {

        $verdict = VideoRepository::verdictFromExplinations(
            (string) $row['explinations']
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

        $stats['total']++;
        $scoreTotal += (float) ($row['score'] ?? 0);

        if (!empty($row['user_id'])) {
            $contributors[(int) $row['user_id']] = true;
        }
    }

    /*
     * Score réel moyen.
     */
    $stats['score_moyen'] = $stats['total'] > 0
        ? round($scoreTotal / $stats['total'], 1)
        : 0.0;

    /*
     * Nombre de comptes ayant effectué au moins une analyse.
     */
    $stats['contributeurs'] = count($contributors);

    /*
     * ==========================================================
     * FILTRE VERDICT
     * ==========================================================
     */
    $recent = $allAnalyses;
    if ($filter !== '') {
        $recent = array_values(
            array_filter(
                $recent,
                static function (array $row) use ($filter): bool {
                    $verdict = VideoRepository::verdictFromExplinations(
                        (string) $row['explinations']
                    );
                    return $verdict === $filter;
                }
            )
        );
    }

    /*
     * 100 analyses les plus récentes.
     */
    $recent = array_slice($recent, 0, 100);

    /*
     * ==========================================================
     * TOP CONTRIBUTEURS
     * ==========================================================
     */
    $contributorStats = [];
    foreach ($allAnalyses as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        if (!isset($contributorStats[$userId])) {
            $contributorStats[$userId] = [
                'user_id'    => $userId,
                'first_name' => $row['u_first_name'] ?? '',
                'last_name'  => $row['u_last_name'] ?? '',
                'count'      => 0,
            ];
        }
        $contributorStats[$userId]['count']++;
    }

    usort($contributorStats, static function (array $a, array $b): int { return $b['count'] <=> $a['count'];});
    $top = array_slice($contributorStats, 0, 5);
}


/*
 * ==========================================================
 * POURCENTAGE
 * ==========================================================
 */
function ds_pct(int $part, int $total): float
{
    return $total > 0
        ? round(($part / $total) * 100, 1)
        : 0.0;
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
          <table class="table" style="font-size: medium;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Compte</th>
                    <th>Type</th>
                    <th>Fichier</th>
                    <th>Verdict</th>
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
                      <?= e(
                          trim(
                              ($r['u_first_name'] ?? '') . ' ' .
                              ($r['u_last_name'] ?? '')
                          )
                      ) ?>
                  </td>
                  <td>
                      <span class="badge <?= ds_media_type_class($r['media_type']) ?>">
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
