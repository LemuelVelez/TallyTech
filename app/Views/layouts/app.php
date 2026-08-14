<?php $role=(string)session()->get('role'); $roleLabel=['admin'=>'Admin Panel','manager'=>'Tournament Manager','validator'=>'Validator','facilitator'=>'Facilitator'][$role]??'TallyTech'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($title??'TallyTech') ?> · TallyTech</title><link rel="icon" href="<?= base_url('assets/img/logo.png') ?>"><link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>"></head>
<body class="app role-<?= esc($role) ?>">
<header class="topbar"><a class="brand" href="<?= site_url('dashboard') ?>"><img src="<?= base_url('assets/img/logo.png') ?>" alt="TallyTech"><span><?= esc($roleLabel) ?></span></a><div class="topbar-user">⚑ <?= esc(session()->get('display_name')) ?></div></header>
<div class="shell"><?= view('partials/sidebar') ?><main class="content">
<?php if(session()->getFlashdata('success')): ?><div class="alert success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
<?php if(session()->getFlashdata('error')): ?><div class="alert error"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
<?= $this->renderSection('content') ?>
<footer>© 2026 TallyTech · Intramural Sports Festival Management System</footer>
</main></div><script src="<?= base_url('assets/js/app.js') ?>"></script></body></html>
