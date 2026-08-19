<?php
$role = (string) session()->get('role');
$path = trim(uri_string(), '/');
$menus = [
    'admin' => [
        ['dashboard', 'Dashboard', 'dashboard'], ['notifications', 'Notifications', 'bell'], ['team-ranking', 'Team Ranking', 'trophy'],
        ['teams', 'Teams', 'users'], ['events', 'Events', 'calendar'], ['sports', 'Sports', 'dumbbell'], ['schedules', 'Schedules', 'calendar-clock'],
        ['users', 'User Management', 'user-cog'], ['reports', 'Reports', 'chart-bar'], ['settings', 'Settings', 'settings'],
    ],
    'manager' => [
        ['dashboard', 'Dashboard', 'dashboard'], ['notifications', 'Notifications', 'bell'], ['team-ranking', 'Team Ranking', 'trophy'],
        ['weighted-points', 'Weighted Points', 'sliders'], ['match-results', 'Match Results', 'clipboard-score'], ['judged-results', 'Judged Results', 'clipboard-check'],
        ['facilitators', 'Facilitators', 'users'], ['reports', 'Reports', 'chart-bar'], ['settings', 'Settings', 'settings'],
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
    <div class="sidebar-head"><span class="nav-label">NAVIGATION</span><button type="button" class="sidebar-close" data-nav-close aria-label="Close navigation"><?= ui_icon('x') ?></button></div>
    <nav>
        <?php foreach ($menus[$role] ?? [] as [$url, $label, $icon]): ?>
            <a class="nav-item <?= $path === $url ? 'active' : '' ?>" href="<?= site_url($url) ?>" title="<?= esc($label) ?>" <?= $path === $url ? 'aria-current="page"' : '' ?>><span class="nav-icon"><?= ui_icon($icon) ?></span><span class="nav-text"><?= esc($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form class="logout-form" method="post" action="<?= site_url('logout') ?>" data-confirm="Log out of TallyTech now? Any unsaved changes on the current page will be lost.">
        <?= csrf_field() ?>
        <button class="nav-item logout" type="submit"><span class="nav-icon"><?= ui_icon('log-out') ?></span><span class="nav-text">Logout</span></button>
    </form>
</aside>
