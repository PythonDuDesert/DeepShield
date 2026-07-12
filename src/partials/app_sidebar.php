<?php
/** @var string $navActive Page active : 'dashboard' | 'analyser' | 'historique' | 'faq' */
$navActive = $navActive ?? '';
$navItems = [
    'dashboard'  => ['href' => 'dashboard.php',  'label' => 'Dashboard',        'icon' => '📊'],
    'analyser'   => ['href' => 'analyser.php',   'label' => 'Nouvelle analyse', 'icon' => '🔍'],
    'historique' => ['href' => 'historique.php', 'label' => 'Historique',       'icon' => '🗂️'],
    'faq'        => ['href' => 'faq.php',        'label' => 'FAQ',              'icon' => '❓'],
];
?>
<aside class="app-sidebar">
    <a href="index.php" class="app-sidebar-logo">
        <span class="logo-mark" style="width:32px;height:32px;">
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

    <nav class="app-nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= e($item['href']) ?>" class="app-nav-item <?= $navActive === $key ? 'active' : '' ?>">
                <span class="app-nav-icon"><?= $item['icon'] ?></span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        <div class="demo-pill">
            <span class="hero-badge-dot"></span>
            Mode démonstration
        </div>
        <a href="index.php" class="app-nav-item app-nav-exit">
            <span class="app-nav-icon">←</span>
            <span>Retour à l'accueil</span>
        </a>
    </div>
</aside>
