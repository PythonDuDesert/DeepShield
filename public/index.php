<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DeepShield — Détection de deepfakes pour la vérification KYC</title>
<link href="assets/css/site.css" rel="stylesheet">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<?php include __DIR__ . '/../src/partials/navbar.php'; ?>

<!-- ══════ HERO ══════ -->
<section class="hero">
    <div class="hero-badge">
        <span class="hero-badge-dot"></span>
        Moteur d'analyse vidéo &amp; audio — usage KYC
    </div>

    <h1>
        Détectez les deepfakes
        <span class="line-2">avant qu'ils ne trompent votre vérification d'identité</span>
    </h1>

    <p class="hero-sub">
        DeepShield analyse les vidéos et audios soumis à un contrôle KYC, détecte les visages et
        voix synthétiques, et fournit à votre équipe de conformité un score de confiance explicable —
        sans jamais décider à sa place.
    </p>

    <div class="hero-buttons">
        <a href="login.php" class="btn-primary">Accéder à la plateforme</a>
        <a href="#fonctionnement" class="btn-secondary">Voir comment ça marche</a>
    </div>

    <div class="hero-visual">
        <div class="scan-frame">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
            <div class="face-icon">
                <svg viewBox="0 0 100 100" fill="none" stroke-width="2.2">
                    <circle cx="50" cy="40" r="22"/>
                    <path d="M14 92c4-20 18-32 36-32s32 12 36 32"/>
                </svg>
            </div>
            <div class="scan-line"></div>
        </div>
        <div class="verdict-cards">
            <div class="verdict-card"><span class="vdot vdot-ok"></span> Vidéo RÉELLE — 94%</div>
            <div class="verdict-card"><span class="vdot vdot-warn"></span> SUSPECT — 61%</div>
            <div class="verdict-card"><span class="vdot vdot-danger"></span> DEEPFAKE détecté — 12%</div>
        </div>
    </div>
</section>

<!-- ══════ STATS ══════ -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item reveal">
            <div class="stat-number">Vidéo + Audio</div>
            <div class="stat-label">Modalités analysées</div>
        </div>
        <div class="stat-item reveal">
            <div class="stat-number">0–100%</div>
            <div class="stat-label">Score de confiance</div>
        </div>
        <div class="stat-item reveal">
            <div class="stat-number">Explicable</div>
            <div class="stat-label">Frames les plus suspectes</div>
        </div>
        <div class="stat-item reveal">
            <div class="stat-number">&lt; 1 min</div>
            <div class="stat-label">Temps de traitement cible</div>
        </div>
    </div>
</div>

<!-- ══════ FONCTIONNALITÉS ══════ -->
<section id="fonctionnalites">
    <div class="section-label">Fonctionnalités</div>
    <h2 class="section-title">Une aide à la décision, pas un juge automatique</h2>
    <p class="section-sub">DeepShield outille votre équipe de conformité sans jamais remplacer le contrôle humain.</p>

    <div class="features-grid">
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">🎞️</div>
            <h3>Analyse vidéo</h3>
            <p>Échantillonnage de frames, détection et recadrage du visage, score réel/deepfake par frame via un modèle de classification dédié.</p>
        </div>
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">🎙️</div>
            <h3>Analyse audio</h3>
            <p>Extraction de caractéristiques spectrales (MFCC) pour détecter les voix clonées ou synthétiques. Moteur en cours de développement.</p>
        </div>
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">📊</div>
            <h3>Score de confiance</h3>
            <p>Un score global combinant les modalités disponibles, avec un verdict clair : RÉEL, SUSPECT ou DEEPFAKE.</p>
        </div>
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">🔍</div>
            <h3>Explicabilité</h3>
            <p>Les frames qui font le plus pencher le verdict sont mises en évidence, pour que la décision humaine reste éclairée.</p>
        </div>
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">🔌</div>
            <h3>API &amp; dashboard</h3>
            <p>Un point d'entrée unique pour soumettre un fichier, un rapport JSON exportable, et un tableau de bord pensé pour une équipe métier.</p>
        </div>
        <div class="feature-card reveal">
            <div class="feature-icon-wrap">🔒</div>
            <h3>Éthique des données</h3>
            <p>Les fichiers biométriques ne sont conservés que le temps de l'analyse, sauf consentement explicite au ré-entraînement.</p>
        </div>
    </div>
</section>

<!-- ══════ FONCTIONNEMENT ══════ -->
<section id="fonctionnement">
    <div class="section-label">Fonctionnement</div>
    <h2 class="section-title">De l'upload au verdict, en quatre étapes</h2>
    <p class="section-sub">Conçu pour une équipe de conformité, pas pour des ingénieurs.</p>

    <div class="steps">
        <div class="step reveal">
            <div class="step-number">01</div>
            <h3>Connexion</h3>
            <p>Accédez à votre espace via votre compte (bientôt disponible — démo ouverte pour l'instant).</p>
        </div>
        <div class="step reveal">
            <div class="step-number">02</div>
            <h3>Dépôt du fichier</h3>
            <p>Envoyez une vidéo (.mp4/.mov) et/ou un audio (.wav/.mp3) depuis le dashboard.</p>
        </div>
        <div class="step reveal">
            <div class="step-number">03</div>
            <h3>Analyse</h3>
            <p>Le moteur extrait les frames, détecte les visages, calcule un score par frame puis un score global.</p>
        </div>
        <div class="step reveal">
            <div class="step-number">04</div>
            <h3>Verdict &amp; export</h3>
            <p>Consultez le rapport détaillé, les frames suspectes, et exportez le résultat en JSON.</p>
        </div>
    </div>

    <p class="limits-note reveal">
        <strong>À noter&nbsp;:</strong> le score DeepShield est une aide à la décision destinée à une équipe
        de conformité KYC. Il ne déclenche jamais de refus automatique et ne remplace pas un contrôle humain.
    </p>
</section>

<!-- ══════ CTA ══════ -->
<section class="cta">
    <div class="cta-card reveal">
        <h2>Explorez la plateforme dès maintenant</h2>
        <p>Les comptes ne sont pas encore activés — un accès de démonstration libre est disponible pour visiter le dashboard.</p>
        <a href="login.php" class="btn-primary">Accéder à la plateforme</a>
    </div>
</section>

<!-- ══════ ÉQUIPE / FOOTER ══════ -->
<footer class="footer" id="equipe">
    <div class="footer-content">
        <div class="footer-section footer-brand">
            <a href="index.php" class="logo" style="margin-bottom:0">
                <span class="logo-mark" style="width:26px;height:26px;">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                        <rect x="15" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                        <rect x="3" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                        <rect x="15" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                        <line x1="3" y1="12" x2="21" y2="12" stroke="#05070d" stroke-width="1.8"/>
                    </svg>
                </span>
                <span style="font-size:1.3em;">DeepShield</span>
            </a>
            <p>Service anti-usurpation pour la vérification d'identité à distance (KYC).</p>
        </div>
        <div class="footer-section">
            <h4>Plateforme</h4>
            <a href="index.php#fonctionnalites">Fonctionnalités</a>
            <a href="index.php#fonctionnement">Fonctionnement</a>
            <a href="login.php">Connexion</a>
            <a href="login.php?tab=register">Inscription</a>
        </div>
        <div class="footer-section">
            <h4>Ressources</h4>
            <a href="dashboard.php">Démo du dashboard</a>
            <a href="historique.php">Historique des analyses</a>
            <a href="faq.php">FAQ</a>
        </div>
        <div class="footer-section">
            <h4>Équipe</h4>
            <a href="#">Olivier Yamine</a>
            <a href="#">Omar Tanaradje</a>
            <a href="#">Lucas Duhoo</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© 2026 DeepShield — Projet pédagogique</span>
        <div class="footer-status">
            <div class="hero-badge-dot"></div>
            Moteur d'analyse opérationnel (mode démonstration)
        </div>
    </div>
</footer>

<script src="assets/js/site.js"></script>
</body>
</html>
