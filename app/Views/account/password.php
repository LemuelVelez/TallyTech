<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="page-head"><div><h1>Change Password</h1><p>Update your account password.</p></div></div>
<section class="panel"><form method="post" action="<?= site_url('account/password') ?>" class="modal-card account-password-form"><?= csrf_field() ?>
<label>Current Password
<span class="password-field">
<input type="password" name="current_password" required autocomplete="current-password" data-password-input>
<button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
<?= ui_icon('eye', 'password-icon password-icon-show') ?>
<?= ui_icon('eye-off', 'password-icon password-icon-hide') ?>
</button>
</span>
</label>
<label>New Password
<span class="password-field">
<input type="password" name="new_password" required autocomplete="new-password" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-password-input>
<button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
<?= ui_icon('eye', 'password-icon password-icon-show') ?>
<?= ui_icon('eye-off', 'password-icon password-icon-hide') ?>
</button>
</span>
</label>
<div class="password-rules">Use 8+ characters with uppercase, lowercase, number, and special character.</div>
<label>Confirm New Password
<span class="password-field">
<input type="password" name="confirm_password" required autocomplete="new-password" data-password-input>
<button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
<?= ui_icon('eye', 'password-icon password-icon-show') ?>
<?= ui_icon('eye-off', 'password-icon password-icon-hide') ?>
</button>
</span>
</label>
<button class="btn primary" type="submit"><?= ui_icon('save') ?><span>Change Password</span></button></form></section>
<?= $this->endSection() ?>
