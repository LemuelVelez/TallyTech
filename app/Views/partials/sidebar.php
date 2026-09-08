<?php
$role = (string) session()->get('role');
$path = trim(uri_string(), '/');
$menus = [
    'admin' => [
        ['dashboard', 'Dashboard', 'dashboard'],
        ['notifications', 'Notifications', 'bell'],
        ['team-ranking', 'Team Ranking', 'trophy'],
        ['teams', 'Teams', 'users'],
        ['events', 'Events', 'calendar'],
        ['sports', 'Sports', 'dumbbell'],
        ['sport-categories', 'Sport Categories', 'sliders'],
        ['locations', 'Locations', 'target'],
        ['reports', 'Reports', 'chart-bar'],
        ['users', 'User Management', 'user-cog'],
        ['settings', 'Settings', 'settings'],
    ],
    'manager' => [
        ['dashboard', 'Dashboard', 'dashboard'],
        ['notifications', 'Notifications', 'bell'],
        ['team-ranking', 'Team Ranking', 'trophy'],
        ['teams', 'Teams', 'users'],
        ['sports', 'Sports', 'dumbbell'],
        ['sport-scores', 'Sport Scores', 'clipboard-score'],
        ['schedules', 'Schedules', 'calendar-clock'],
        ['match-results', 'Match Results', 'clipboard-score'],
        ['brackets', 'Bracket Management', 'trophy'],
        ['draft-generator', 'Draft Generator', 'sliders'],
        ['reports', 'Reports', 'chart-bar'],
        ['settings', 'Settings', 'settings'],
    ],
    'validator' => [
        ['dashboard', 'Dashboard', 'dashboard'], ['notifications', 'Notifications', 'bell'], ['team-ranking', 'Team Ranking', 'trophy'], ['weighted-points', 'Weighted Points', 'sliders'],
        ['match-results', 'Match Results', 'clipboard-score'], ['judged-results', 'Judged Results', 'clipboard-check'], ['settings', 'Settings', 'settings'],
    ],
    'facilitator' => [
        ['dashboard', 'Dashboard', 'dashboard'], ['notifications', 'Notifications', 'bell'], ['team-ranking', 'Team Ranking', 'trophy'],
        ['match-results', 'Match Results', 'clipboard-score'], ['judged-results', 'Judged Results', 'clipboard-check'], ['settings', 'Settings', 'settings'],
    ],
];?>
<aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
    <div class="sidebar-head">
        <span class="nav-label">NAVIGATION</span>
        <button type="button" class="sidebar-compact-toggle" data-sidebar-compact-toggle aria-label="Collapse navigation" aria-controls="app-sidebar" aria-expanded="true" title="Collapse navigation">
            <span class="sidebar-collapse-icon" aria-hidden="true"><?= ui_icon('chevron-left') ?></span>
            <span class="sidebar-expand-icon" aria-hidden="true"><?= ui_icon('chevron-right') ?></span>
        </button>
        <button type="button" class="sidebar-close" data-nav-close aria-label="Close navigation"><?= ui_icon('x') ?></button>
    </div>
    <nav>
        <?php foreach ($menus[$role] ?? [] as [$url, $label, $icon]): ?>
            <a class="nav-item <?= $path === $url ? 'active' : '' ?>" href="<?= site_url($url) ?>" title="<?= esc($label) ?>" <?= $path === $url ? 'aria-current="page"' : '' ?>><span class="nav-icon"><?= ui_icon($icon) ?></span><span class="nav-text"><?= esc($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form class="logout-form" method="post" action="<?= site_url('logout') ?>" data-confirm="Log out of TallyTech now? Any unsaved changes on the current page will be lost.">
        <?= csrf_field() ?>
        <button class="nav-item logout" type="submit" title="Logout"><span class="nav-icon"><?= ui_icon('log-out') ?></span><span class="nav-text">Logout</span></button>
    </form>
</aside>
