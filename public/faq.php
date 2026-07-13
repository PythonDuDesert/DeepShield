<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'faq';

/**
 * Contenu de la FAQ, structuré par catégorie. Garder les réponses ici
 * (plutôt qu'en dur dans le HTML) facilite l'ajout de nouvelles questions.
 */
$faqCategories = [
    [
        'title' => 'Utilisation de base',
        'items' => [
            [
                'q' => "Comment lancer une analyse ?",
                'a' => "Depuis la page « Nouvelle analyse », déposez une vidéo (.mp4/.mov) et/ou un audio (.wav/.mp3), 
                        puis cliquez sur « Lancer l'analyse ». Le rapport s'affiche automatiquement une fois le traitement terminé.",
            ],
            [
                'q' => "Quels formats de fichiers sont acceptés ?",
                'a' => "Vidéo : .mp4 et .mov. Audio : .wav et .mp3. La taille maximale par fichier est définie dans la 
                        configuration du serveur (200 Mo par défaut).",
            ],
            [
                'q' => "Dois-je fournir à la fois une vidéo et un audio ?",
                'a' => "Non. Le moteur fonctionne avec une seule modalité si nécessaire : le score global se recalcule 
                        automatiquement sur les seules modalités disponibles.",
            ],
            [
                'q' => "Combien de temps prend une analyse ?",
                'a' => "L'objectif est de rester compatible avec un usage interactif (cible indicative : moins d'une 
                        minute sur un poste standard). Le temps réel dépend du nombre de frames analysées et de la 
                        puissance de la machine.",
            ],
        ],
    ],
    [
        'title' => 'Comprendre les résultats',
        'items' => [
            [
                'q' => "Que signifient les verdicts RÉEL, SUSPECT et DEEPFAKE ?",
                'a' => "Ce sont les trois niveaux de confiance calculés à partir du score « réel » moyen : RÉEL quand
                        le score est nettement au-dessus du seuil de décision, DEEPFAKE quand il est nettement 
                        en-dessous, et SUSPECT dans la zone grise autour de ce seuil.",
            ],
            [
                'q' => "Qu'est-ce que le seuil de décision ?",
                'a' => "C'est le pourcentage de score « réel » à partir duquel une frame ou une vidéo bascule vers un 
                        verdict plutôt qu'un autre. Il est réglable dans le formulaire d'analyse et sera, à terme, 
                        calibré à partir de l'évaluation sur les jeux de données de référence (FaceForensics++, 
                        ASVspoof, DFDC).",
            ],
            [
                'q' => "Qu'est-ce que « l'explicabilité » dans le rapport ?",
                'a' => "Chaque rapport vidéo liste les frames dont le score « réel » est le plus bas — celles qui font 
                        le plus pencher le verdict — pour que la personne qui prend la décision comprenne pourquoi 
                        un score a été attribué, plutôt que de recevoir un simple chiffre.",
            ],
            [
                'q' => "Puis-je exporter un rapport ?",
                'a' => "Oui, chaque rapport propose un bouton « Exporter le rapport (JSON) » qui télécharge l'intégralité 
                        des données (score, verdict, détail par frame, temps de traitement).",
            ],
        ],
    ],
    [
        'title' => 'Confidentialité des données',
        'items' => [
            [
                'q' => "Mes fichiers vidéo/audio sont-ils conservés ?",
                'a' => "Non, par défaut. Les vidéos et audios analysés (données biométriques) sont supprimés du serveur 
                        automatiquement juste après l'analyse, sauf si vous cochez explicitement « conserver pour 
                        ré-entraînement » dans le formulaire.",
            ],
            [
                'q' => "Le score peut-il déclencher un refus automatique ?",
                'a' => "Non, jamais. Le score DeepShield est une aide à la décision destinée à une équipe de conformité. 
                        La décision finale reste humaine, quel que soit le résultat affiché.",
            ],
            [
                'q' => "Le système peut-il présenter des biais ?",
                'a' => "Oui, c'est une limite connue et documentée : la performance du modèle peut varier selon le 
                        teint de peau, l'accent ou la qualité du matériel d'enregistrement. Cette limite doit être 
                        gardée à l'esprit lors de l'interprétation d'un score.",
            ],
        ],
    ],
    [
        'title' => 'Compte et accès',
        'items' => [
            [
                'q' => "Pourquoi la connexion ne fonctionne pas encore ?",
                'a' => "L'authentification (comptes, rôles, sessions) n'est pas encore développée. En attendant, un 
                        accès de démonstration libre permet de visiter l'ensemble de la plateforme, upload compris.",
            ],
            [
                'q' => "Mes analyses en mode démo sont-elles liées à un compte ?",
                'a' => "Non. En mode démonstration, l'historique est partagé par tous les visiteurs de cet 
                        environnement, sans notion de compte individuel pour le moment.",
            ],
            [
                'q' => "Comment serai-je informé de l'ouverture des comptes ?",
                'a' => "Cette FAQ et la page de connexion seront mises à jour dès que l'authentification sera 
                        disponible.",
            ],
        ],
    ],
    [
        'title' => 'Limites connues',
        'items' => [
            [
                'q' => "L'analyse audio est-elle disponible ?",
                'a' => "Pas encore : le moteur audio (extraction de caractéristiques spectrales + modèle entraîné sur 
                        ASVspoof) est en cours de développement. Le formulaire accepte déjà un fichier audio, mais le 
                        rapport indique explicitement que cette modalité n'est pas encore implémentée.",
            ],
            [
                'q' => "Le mode démonstration reflète-t-il de vrais scores ?",
                'a' => "Non : en mode démonstration, les scores sont simulés de façon déterministe (même fichier → 
                        même résultat) pour permettre de tester toute l'interface sans installer l'environnement de 
                        machine learning complet.",
            ],
            [
                'q' => "Un score élevé garantit-il l'authenticité d'un contenu ?",
                'a' => "Non. Un score de confiance élevé n'est pas une preuve d'authenticité et doit toujours être 
                        présenté avec cette réserve, en particulier face aux techniques de deepfake les plus 
                        récentes.",
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FAQ — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>Foire aux questions</h1>
      <p>Tout ce qu'il faut savoir sur l'utilisation de DeepShield et l'interprétation de ses résultats.</p>
    </div>

    <div class="faq-search">
      <input type="text" id="faqSearch" placeholder="Rechercher une question…" oninput="filterFAQ()">
    </div>

    <div id="faqList">
      <?php foreach ($faqCategories as $ci => $cat): ?>
        <div class="faq-category">
          <div class="faq-category-title"><?= e($cat['title']) ?></div>
          <?php foreach ($cat['items'] as $ii => $item): ?>
            <?php $itemId = 'faq-' . $ci . '-' . $ii; ?>
            <div class="faq-item" data-search="<?= e(strtolower($item['q'] . ' ' . $item['a'])) ?>">
              <div class="faq-question" onclick="toggleFaq('<?= $itemId ?>')">
                <h3><?= e($item['q']) ?></h3>
                <span class="faq-toggle" id="<?= $itemId ?>-toggle">+</span>
              </div>
              <div class="faq-answer" id="<?= $itemId ?>">
                <p><?= e(trim(preg_replace('/\s+/', ' ', $item['a']))) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="faq-empty" id="faqEmpty">
        <p>Aucune question ne correspond à votre recherche.</p>
      </div>
    </div>

    <div class="faq-contact">
      <h2>Une question sans réponse ici ?</h2>
      <p>L'équipe DeepShield reste joignable pour toute question sur le projet ou son cahier des charges.</p>
      <a href="index.php#equipe" class="btn-secondary">Voir l'équipe du projet</a>
    </div>
  </main>
</div>

<script src="assets/js/site.js"></script>
<script>
function toggleFaq(id) {
    var item = document.getElementById(id).closest('.faq-item');
    item.classList.toggle('active');
}

function filterFAQ() {
    var query = document.getElementById('faqSearch').value.trim().toLowerCase();
    var items = document.querySelectorAll('.faq-item');
    var visibleCount = 0;

    items.forEach(function (item) {
        var haystack = item.getAttribute('data-search') || '';
        var match = query === '' || haystack.indexOf(query) !== -1;
        item.classList.toggle('hidden', !match);
        if (match) visibleCount++;
    });

    document.querySelectorAll('.faq-category').forEach(function (cat) {
        var visibleInCat = cat.querySelectorAll('.faq-item:not(.hidden)').length;
        cat.style.display = visibleInCat === 0 ? 'none' : '';
    });

    document.getElementById('faqEmpty').classList.toggle('show', visibleCount === 0);
}
</script>
</body>
</html>
