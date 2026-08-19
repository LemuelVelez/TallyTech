<?php
$role = (string) session()->get('role');
$roleLabel = ['admin' => 'Admin Panel', 'manager' => 'Tournament Manager', 'validator' => 'Validator', 'facilitator' => 'Facilitator'][$role] ?? 'TallyTech';
$compactSidebar = (bool) session()->get('compact_sidebar');
$resultDensity = (string) (session()->get('result_density') ?: 'comfortable');
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
    <div class="topbar-user"><span class="user-avatar"><?= esc(strtoupper(substr((string) session()->get('display_name'), 0, 1))) ?></span><span><?= esc(session()->get('display_name')) ?></span></div>
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
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
