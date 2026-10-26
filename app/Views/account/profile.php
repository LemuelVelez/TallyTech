<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$photo = (string)($profile['photo_path'] ?? '');
$avatar = preg_match('~^uploads/user-avatars/[a-f0-9]{32}\.webp$~',$photo) ? base_url($photo) : base_url('assets/img/logo.webp');
$fields = [
 'address'=>'Address', 'phone'=>'Phone', 'facebook_url'=>'Facebook URL', 'email'=>'Email',
 'college_office'=>'College / Department / Office', 'position'=>'Position',
 'emergency_contact_name'=>'Emergency Contact Name', 'emergency_contact_phone'=>'Emergency Contact Phone',
];
?>
<div class="page-head"><div><h1><?= esc($editable ? 'Your Profile' : 'User Profile') ?></h1><p><?= $editable ? 'Keep your account information up to date.' : 'Profile information (read only).' ?></p></div></div>
<section class="panel profile-panel">
<?php if ($editable): ?><form action="<?= site_url('profile') ?>" method="post" enctype="multipart/form-data" class="profile-grid">
<?= csrf_field() ?>
<div class="profile-wide"><img class="profile-avatar-preview" src="<?= esc($avatar,'attr') ?>" alt="Profile picture" data-profile-preview><label>Profile Photo (JPG, PNG or WebP, up to 2 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-profile-photo></label><label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label></div>
<label>Full Name<input name="display_name" required maxlength="120" value="<?= esc(old('display_name',$profile['display_name']),'attr') ?>"></label>
<?php foreach ($fields as $field=>$label): ?><label><?= esc($label) ?><input name="<?= esc($field,'attr') ?>" maxlength="<?= in_array($field,['phone','emergency_contact_phone'],true) ? 16 : ($field==='email'?254:($field==='college_office'?180:($field==='position'?150:($field==='emergency_contact_name'?120:255)))) ?>" value="<?= esc(old($field,$profile[$field]??''),'attr') ?>"<?= $field==='email'?' type="email"':'' ?><?= $field==='facebook_url'?' type="url"':'' ?>></label><?php endforeach; ?>
<label class="profile-wide">Bio<textarea name="bio" rows="5" maxlength="3000"><?= esc(old('bio',$profile['bio']??'')) ?></textarea></label>
<div class="profile-wide"><button class="btn primary" type="submit"><?= ui_icon('save') ?><span>Save Profile</span></button></div>
</form>
<script>document.querySelector('[data-profile-photo]')?.addEventListener('change', function(){ const f=this.files?.[0]; if(!f)return; const u=URL.createObjectURL(f); const img=document.querySelector('[data-profile-preview]'); img.onload=()=>URL.revokeObjectURL(u); img.src=u; });</script>
<?php else: ?>
<div class="profile-grid"><div class="profile-wide"><img class="profile-avatar-preview" src="<?= esc($avatar,'attr') ?>" alt="Profile picture"><h2><?= esc($profile['display_name']) ?></h2><p><?= esc(['admin'=>'Admin - TSC','manager'=>'Sports Coordinator','facilitator'=>'Tournament Manager'][$profile['role']]??$profile['role']) ?></p></div>
<?php foreach($fields as $field=>$label): ?><div><strong><?= esc($label) ?></strong><p><?= esc($profile[$field]?:'—') ?></p></div><?php endforeach; ?>
<div class="profile-wide"><strong>Bio</strong><p><?= nl2br(esc($profile['bio']?:'—')) ?></p></div></div>
<?php endif; ?>
</section>
<?= $this->endSection() ?>
