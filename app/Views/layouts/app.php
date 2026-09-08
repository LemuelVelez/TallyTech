<?php
$role = (string) session()->get('role');
$roleLabel = ['admin' => 'Admin Panel', 'manager' => 'Tournament Manager', 'validator' => 'Validator', 'facilitator' => 'Facilitator'][$role] ?? 'TallyTech';
$accountRoleLabel = ['admin' => 'Administrator', 'manager' => 'Tournament Manager', 'validator' => 'Validator', 'facilitator' => 'Facilitator'][$role] ?? ucfirst($role);
$compactSidebar = (bool) session()->get('compact_sidebar');
$resultDensity = (string) (session()->get('result_density') ?: 'comfortable');
$displayName = (string) session()->get('display_name');
$assetVersion = static function (string $relativePath): string {
    $path = defined('FCPATH') ? FCPATH . ltrim($relativePath, '/\\') : '';
    return $path !== '' && is_file($path) ? (string) filemtime($path) : '20260908-2';
};
$cssVersion = $assetVersion('assets/css/app.css');
$jsVersion = $assetVersion('assets/js/app.js');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0c7e43">
    <title><?= esc($title ?? 'TallyTech') ?> · TallyTech</title>
    <link rel="icon" type="image/webp" href="<?= base_url('favicon.webp') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css') . '?v=' . rawurlencode($cssVersion), 'attr') ?>">
</head>
<body class="app role-<?= esc($role) ?> <?= $compactSidebar ? 'sidebar-compact' : '' ?> density-<?= esc($resultDensity) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="topbar">
    <button class="menu-toggle" type="button" data-nav-toggle aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false"><?= ui_icon('menu') ?></button>
    <a class="brand" href="<?= site_url('dashboard') ?>"><img src="<?= base_url('assets/img/logo.webp') ?>" alt="TallyTech"><span><?= esc($roleLabel) ?></span></a>

    <div class="account-menu" data-account-menu>
        <button class="topbar-user" type="button" data-account-toggle aria-label="Open user menu for <?= esc($displayName, 'attr') ?>" aria-expanded="false" aria-controls="account-dropdown" aria-haspopup="menu">
            <img class="user-avatar" src="<?= base_url('assets/img/logo.webp') ?>" alt="">
            <span class="user-identity">
                <span class="user-name"><?= esc($displayName) ?></span>
                <small><?= esc($accountRoleLabel) ?></small>
            </span>
            <span class="user-chevron" aria-hidden="true"><?= ui_icon('chevron-down') ?></span>
        </button>
        <div class="account-dropdown" id="account-dropdown" data-account-dropdown role="menu" hidden>
            <div class="account-dropdown-head">
                <b><?= esc($displayName) ?></b>
                <span><?= esc($accountRoleLabel) ?></span>
            </div>
            <a href="<?= site_url('dashboard') ?>" role="menuitem"><?= ui_icon('dashboard') ?><span>Dashboard</span></a>
            <a href="<?= site_url('settings') ?>" role="menuitem"><?= ui_icon('settings') ?><span>Settings</span></a>
            <form method="post" action="<?= site_url('logout') ?>" data-confirm="Log out of TallyTech now? Any unsaved changes on the current page will be lost.">
                <?= csrf_field() ?>
                <button type="submit" role="menuitem"><?= ui_icon('log-out') ?><span>Logout</span></button>
            </form>
        </div>
    </div>
</header>
<div class="shell">
    <?= view('partials/sidebar') ?>
    <button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation"></button>
    <main class="content" id="main-content">
        <?php if (session()->getFlashdata('success')): ?><div class="alert success" role="status" data-flash-alert data-dismiss-after="5000"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?><div class="alert error" role="alert" data-flash-alert data-dismiss-after="5000"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
        <?= $this->renderSection('content') ?>
        <footer>© 2026 TallyTech · Intramural Sports Festival Management System</footer>
    </main>
</div>

<dialog class="confirmation-dialog" data-confirm-dialog data-confirm-tone="default" role="alertdialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-message">
    <div class="confirmation-card">
        <div class="confirmation-main">
            <div class="confirmation-icon" aria-hidden="true"><?= ui_icon('alert-triangle') ?></div>
            <div class="confirmation-copy">
                <h2 id="confirmation-title" data-confirm-title>Confirm action</h2>
                <p id="confirmation-message" data-confirm-message>Are you sure you want to continue?</p>
            </div>
        </div>
        <div class="confirmation-actions">
            <button class="btn confirmation-cancel" type="button" data-confirm-cancel>Cancel</button>
            <button class="btn confirmation-proceed" type="button" data-confirm-proceed>Confirm</button>
        </div>
    </div>
</dialog>

<script src="<?= esc(base_url('assets/js/app.js') . '?v=' . rawurlencode($jsVersion), 'attr') ?>"></script>
</body>
</html>
