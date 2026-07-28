<?php
// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);

$currentUser = ($auth !== null) ? $auth->currentUser() : null;
?>

<!-- Menu vertical -->
<div class="sidebar">
    <nav class="nav-menu">
        <div class="header-logo"><img src="assets/images/bouclier.png" alt="logo" style="width:30px; height:30px; object-fit:contain;">DeepShield</div>

        <a href="dashboard.php" class="nav-item <?php echo (strpos($current_page, 'dashboard') !== false) ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/graphiques.png" alt="Dashboard" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Dashboard</span>
        </a>

        <a href="analyser.php" class="nav-item <?php echo ($current_page == 'analyser.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/rechercher.png" alt="Analyser" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Analyser</span>
        </a>

        <a href="historique.php" class="nav-item <?php echo ($current_page == 'historique.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/fiche-devaluation.png" alt="Mon Historique" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Mon Historique</span>
        </a>

        <a href="resultats.php" class="nav-item <?php echo ($current_page == 'resultats.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/fluctuation.png" alt="Résultats Globaux" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Résultats Globaux</span>
        </a>        
        <!-- Menu réservé aux admins -->
        <a href="gestion_users.php" class="nav-item <?php echo ($current_page == 'gestion_users.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/user_logo.png" alt="Gestion Utilisateurs" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Gestion Utilisateurs</span>
        </a>
    
        <a href="faq.php" class="nav-item <?php echo ($current_page == 'faq.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/point-dinterrogation.png" alt="FAQ" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>FAQ</span>
        </a>
        <a href="backup.php" class="nav-item <?php echo ($current_page == 'backup.php') ? 'active' : ''; ?>">
            <div class="nav-icon">
                <img src="assets/images/database.png" alt="Backup" style="width:25px; height:25px; object-fit:contain;">
            </div>
            <span>Backup</span>
        </a>
    </nav>

    <div class="app-sidebar-footer">
        <div class="user-pill">
            <span class="user-avatar"><?= e(strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1))) ?></span>
            <div class="user-pill-info">
                <span class="user-name"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></span>
                <span class="user-email"><?= e($currentUser['email']) ?></span>
            </div>
        </div>
        <a href="logout.php" class="nav-item" style="margin-bottom:-25px;">
            <span class="app-nav-icon"><img src="assets/images/logout.png" alt="Logout" style="width:25px; height:25px; object-fit:contain;"></span>
            <span>Se déconnecter</span>
        </a>
        <a href="assistance.php" class="nav-item nav-assistance-btn" title="Signaler un problème ou demander de l'aide">
            <div class="nav-icon">
                <span class="assistance-icon"><img src="assets/images/speech-bubble.png" alt="Speech-bubble" style="width:25px; height:25px; object-fit:contain;"></span>
            </div>
            <span>Signaler</span>
        </a>
    </div>
</div>