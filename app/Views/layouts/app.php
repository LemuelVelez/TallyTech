<?php
$role = (string) session()->get('role');
$roleLabel = ['admin' => 'Admin Panel', 'manager' => 'Tournament Manager', 'validator' => 'Validator', 'facilitator' => 'Facilitator'][$role] ?? 'TallyTech';
$accountRoleLabel = ['admin' => 'Administrator', 'manager' => 'Sports Manager', 'validator' => 'Validator', 'facilitator' => 'Facilitator'][$role] ?? ucfirst($role);
$compactSidebar = (bool) session()->get('compact_sidebar');
$resultDensity = (string) (session()->get('result_density') ?: 'comfortable');
$displayName = (string) session()->get('display_name');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0c7e43">
    <title><?= esc($title ?? 'TallyTech') ?> · TallyTech</title>
    <link rel="icon" type="image/png" href="<?= base_url('logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="app role-<?= esc($role) ?> <?= $compactSidebar ? 'sidebar-compact' : '' ?> density-<?= esc($resultDensity) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="topbar">
    <button class="menu-toggle" type="button" data-nav-toggle aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">☰</button>
    <a class="brand" href="<?= site_url('dashboard') ?>"><img src="<?= base_url('assets/img/logo.png') ?>" alt="TallyTech"><span><?= esc($roleLabel) ?></span></a>

    <div class="account-menu" data-account-menu>
        <button class="topbar-user" type="button" data-account-toggle aria-label="Open user menu for <?= esc($displayName, 'attr') ?>" aria-expanded="false" aria-controls="account-dropdown" aria-haspopup="menu">
            <img class="user-avatar" src="<?= base_url('assets/img/logo.png') ?>" alt="">
            <span class="user-identity">
                <span class="user-name"><?= esc($displayName) ?></span>
                <small><?= esc($accountRoleLabel) ?></small>
            </span>
            <span class="user-chevron" aria-hidden="true">⌄</span>
        </button>
        <div class="account-dropdown" id="account-dropdown" data-account-dropdown role="menu" hidden>
            <div class="account-dropdown-head">
                <b><?= esc($displayName) ?></b>
                <span><?= esc($accountRoleLabel) ?></span>
            </div>
            <a href="<?= site_url('dashboard') ?>" role="menuitem">Dashboard</a>
            <a href="<?= site_url('settings') ?>" role="menuitem">Settings</a>
            <form method="post" action="<?= site_url('logout') ?>">
                <?= csrf_field() ?>
                <button type="submit" role="menuitem">Logout</button>
            </form>
        </div>
    </div>
</header>
<div class="shell">
    <?= view('partials/sidebar') ?>
    <button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation"></button>
    <main class="content" id="main-content">
        <?php if (session()->getFlashdata('success')): ?><div class="alert success" role="status"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?><div class="alert error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
        <?= $this->renderSection('content') ?>
        <footer>© 2026 TallyTech · Intramural Sports Festival Management System</footer>
    </main>
</div>

<dialog class="confirmation-dialog" data-confirm-dialog aria-labelledby="confirmation-title" aria-describedby="confirmation-message">
    <div class="confirmation-card">
        <div class="confirmation-icon" aria-hidden="true">!</div>
        <div>
            <h2 id="confirmation-title">Confirm action</h2>
            <p id="confirmation-message" data-confirm-message>Are you sure you want to continue?</p>
        </div>
        <div class="confirmation-actions">
            <button class="btn" type="button" data-confirm-cancel>Cancel</button>
            <button class="btn danger" type="button" data-confirm-proceed>Confirm</button>
        </div>
    </div>
</dialog>

<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
