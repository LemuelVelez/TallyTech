<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0c7e43">
    <title>Sign in · TallyTech</title>
    <link rel="icon" type="image/png" href="<?= base_url('logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="login-page">
    <main class="login-card">
        <a class="login-logo" href="<?= site_url('scoreboard') ?>"><img src="<?= base_url('assets/img/logo.png') ?>" alt="TallyTech"><strong>TallyTech</strong></a>
        <h1>Welcome back</h1>
        <p>Sign in to manage the ISF scoring system.</p>
        <?php if (session()->getFlashdata('error')): ?><div class="alert error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
        <form method="post" action="<?= site_url('login') ?>">
            <?= csrf_field() ?>
            <label>Username<input name="username" value="<?= esc((string) session()->getFlashdata('login_username')) ?>" required autocomplete="username"></label>
            <label>Password
                <span class="password-field">
                    <input type="password" name="password" required autocomplete="current-password" data-password-input>
                    <button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <?= ui_icon('eye', 'password-icon password-icon-show') ?>
                        <?= ui_icon('eye-off', 'password-icon password-icon-hide') ?>
                    </button>
                </span>
            </label>
            <button class="btn primary full" type="submit"><?= ui_icon('log-in') ?><span>Sign in</span></button>
        </form>
        <a class="back-link" href="<?= site_url('scoreboard') ?>"><?= ui_icon('arrow-left') ?><span>Back to live scoreboard</span></a>
    </main>
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
