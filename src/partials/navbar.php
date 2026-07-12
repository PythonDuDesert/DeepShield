<?php
/**
 * @var string $navActive 'home' ou 'login' (état actuel, pour styliser au besoin)
 */
$navActive = $navActive ?? '';
?>
<nav class="navbar" id="navbar">
    <a href="index.php" class="logo">
        <span class="logo-mark">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="15" y="3" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="3" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <rect x="15" y="15" width="6" height="6" rx="1.2" stroke="#05070d" stroke-width="1.8"/>
                <line x1="3" y1="12" x2="21" y2="12" stroke="#05070d" stroke-width="1.8"/>
            </svg>
        </span>
        <span>DeepShield</span>
    </a>
    <div class="nav-links">
        <a href="index.php#fonctionnalites">Fonctionnalités</a>
        <a href="index.php#fonctionnement">Fonctionnement</a>
        <a href="index.php#equipe">Équipe</a>
        <a href="faq.php">FAQ</a>
        <a href="login.php" class="btn-nav" id="btn-login">Connexion</a>
        <a href="login.php?tab=register" class="btn-nav" id="btn-demo">S'inscrire →</a>
    </div>
</nav>
