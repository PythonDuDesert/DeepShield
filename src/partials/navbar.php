<?php
/**
 * @var string $navActive 'home' ou 'login' (état actuel, pour styliser au besoin)
 */
$navActive = $navActive ?? '';
?>
<nav class="navbar" id="navbar">
    <a href="index.php" class="logo">
        <img src="assets/images/bouclier.png" alt="logo" style="width:26px;height:26px;">
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
