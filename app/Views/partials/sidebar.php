<?php
$role = (string) session()->get('role');
$path = trim(uri_string(), '/');
$menus = [
    'admin' => [
        ['dashboard', 'Dashboard', '▦'], ['notifications', 'Notifications', '◉'], ['team-ranking', 'Team Ranking', '♜'],
        ['teams', 'Teams', '♟'], ['events', 'Events', '◫'], ['sports', 'Sports', '◆'], ['schedules', 'Schedules', '▤'],
        ['sports-managers', 'Sports Managers', '♙'], ['reports', 'Reports', '▥'], ['settings', 'Settings', '⚙'],
    ],
    'manager' => [
        ['dashboard', 'Dashboard', '▦'], ['notifications', 'Notifications', '◉'], ['team-ranking', 'Team Ranking', '♜'],
        ['weighted-points', 'Weighted Points', '◈'], ['match-results', 'Match Results', '▤'], ['judged-results', 'Judged Results', '◇'],
        ['facilitators', 'Facilitators', '♙'], ['reports', 'Reports', '▥'], ['settings', 'Settings', '⚙'],
    ],
    'validator' => [
        ['dashboard', 'Dashboard', '▦'], ['notifications', 'Notifications', '◉'], ['team-ranking', 'Team Ranking', '♜'], ['weighted-points', 'Weighted Points', '◈'],
        ['match-results', 'Match Results', '▤'], ['judged-results', 'Judged Results', '◇'], ['settings', 'Settings', '⚙'],
    ],
    'facilitator' => [
        ['dashboard', 'Dashboard', '▦'], ['notifications', 'Notifications', '◉'], ['team-ranking', 'Team Ranking', '♜'],
        ['match-results', 'Match Results', '▤'], ['judged-results', 'Judged Results', '◇'], ['settings', 'Settings', '⚙'],
    ],
];
?>
<aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
    <div class="sidebar-head"><span class="nav-label">NAVIGATION</span><button type="button" class="sidebar-close" data-nav-close aria-label="Close navigation">×</button></div>
    <nav>
        <?php foreach ($menus[$role] ?? [] as [$url, $label, $icon]): ?>
            <a class="nav-item <?= $path === $url ? 'active' : '' ?>" href="<?= site_url($url) ?>" title="<?= esc($label) ?>" <?= $path === $url ? 'aria-current="page"' : '' ?>><span class="nav-icon"><?= $icon ?></span><span class="nav-text"><?= esc($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <a class="nav-item logout" href="<?= site_url('logout') ?>"><span class="nav-icon">↪</span><span class="nav-text">Logout</span></a>
</aside>
