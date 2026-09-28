<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$isAdminManagement = $manageMode === 'admin';
$isFacilitatorManagement = $manageMode === 'facilitator';
$createAction = $isAdminManagement ? 'users' : 'facilitators';
$heading = $isAdminManagement ? 'User Management' : 'Facilitators';
$buttonLabel = $isAdminManagement ? 'Add Tournament Manager' : 'Add Facilitator';
$singleManagerSport = $isFacilitatorManagement && count($sports) === 1;
$sportLabel = static fn(array $sport): string => trim((string) ($sport['name'] ?? '') . (! empty($sport['category']) ? ' · ' . $sport['category'] : ''));
?>
<div class="page-head"><div><h1><?= esc($heading) ?></h1><p><?= $isAdminManagement ? 'Administrators can create Tournament Managers and assign one or more sports.' : 'Assign facilitators to one or more sports from your own assigned sports.' ?></p></div><button class="btn primary" data-modal="user-modal" <?= ! $activeEvent || ! $sports ? 'disabled' : '' ?>><?= ui_icon('plus') ?><span><?= esc($buttonLabel) ?></span></button></div>
<?php if ($activeEvent): ?><div class="event-banner"><b>Active event:</b> <?= esc($activeEvent['name']) ?></div><?php endif; ?>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Sport</th><th>Status</th><th>Password</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $user): $role=(string)$user['role']; $canManage=($isAdminManagement&&$role==='manager')||($isFacilitatorManagement&&$role==='facilitator'); $password=$user['generated_password_plain']??null; $userSports=$user['sports']??[]; $userSportIds=array_map('intval',array_column($userSports,'id')); ?><tr>
<td><b><?= esc($user['display_name']) ?></b></td><td><?= esc($user['username']) ?></td><td><?= esc($role==='manager'?'Tournament Manager':ucfirst($role)) ?></td><td><?= $userSports ? esc(implode(', ', array_map($sportLabel, $userSports))) : '—' ?></td><td><span class="badge <?= $user['status']==='active'?'official':'neutral' ?>"><?= strtoupper(esc($user['status'])) ?></span></td>
<td><?php if($password):?><span class="generated-password" data-copy-text="<?= esc($password,'attr') ?>"><code><?= esc($password) ?></code> <button type="button" class="btn tiny" data-copy-value="<?= esc($password,'attr') ?>"><?= ui_icon('copy') ?><span>Copy</span></button></span><?php elseif(!empty($user['generated_password'])):?><span class="muted">Hidden</span><?php else:?><span class="muted">Changed by user</span><?php endif;?></td>
<td><?php if($canManage):?><div class="row-actions"><button class="btn tiny" data-modal="user-edit-<?= (int)$user['id'] ?>"><?= ui_icon('pencil') ?><span>Edit</span></button><form method="post" action="<?= site_url(($isAdminManagement?'users/':'facilitators/').$user['id'].'/reset-password') ?>" data-confirm="Generate a new password for this account?"><?= csrf_field() ?><button class="btn tiny"><?= ui_icon('key') ?><span>Reset password</span></button></form><form method="post" action="<?= site_url(($isAdminManagement?'users/':'facilitators/').$user['id'].'/delete') ?>" data-confirm="Delete this account?"><?= csrf_field() ?><button class="btn tiny danger"><?= ui_icon('trash') ?><span>Delete</span></button></form></div><?php else:?><span class="muted">Read only</span><?php endif;?></td></tr>
<?php endforeach;?><?php if(!$users):?><tr><td colspan="7" class="empty">No accounts found.</td></tr><?php endif;?></tbody></table></div></section>

<?php foreach($users as $user): $role=(string)$user['role']; $canManage=($isAdminManagement&&$role==='manager')||($isFacilitatorManagement&&$role==='facilitator'); if(!$canManage)continue; $userSportIds=array_map('intval',array_column($user['sports']??[],'id')); ?>
<dialog id="user-edit-<?= (int)$user['id'] ?>"><form method="post" action="<?= site_url(($isAdminManagement?'users/':'facilitators/').$user['id'].'/update') ?>" class="modal-card"><?= csrf_field() ?><div class="modal-head"><h2>Edit <?= esc($role==='manager'?'Tournament Manager':'Facilitator') ?></h2><button type="button" data-close><?= ui_icon('x') ?></button></div><label>Display Name<input name="display_name" required maxlength="120" value="<?= esc($user['display_name']) ?>"></label><label>Username<input name="username" required maxlength="80" value="<?= esc($user['username']) ?>"></label>
<span class="field-label">Assigned Sports</span><div class="sport-checks managed-sport-checks">
<?php foreach($sports as $sport): $sportId=(int)$sport['id']; $checked=$singleManagerSport || in_array($sportId,$userSportIds,true); ?>
<label class="check"><input type="checkbox" name="sport_ids[]" value="<?= $sportId ?>" <?= $checked?'checked':'' ?> <?= $singleManagerSport?'disabled aria-disabled="true"':'' ?>> <?= esc($sportLabel($sport)) ?></label><?php if($singleManagerSport):?><input type="hidden" name="sport_ids[]" value="<?= $sportId ?>"><?php endif;?>
<?php endforeach;?></div>
<p class="form-note"><?= $isAdminManagement ? 'Tournament Managers require at least one sport.' : ($singleManagerSport ? 'This sport is fixed because your Tournament Manager account has one assigned sport.' : 'Facilitators require at least one sport from your assigned sports.') ?></p>
<label>Status<select name="status"><option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactive</option></select></label><button class="btn primary full"><?= ui_icon('save') ?><span>Save Changes</span></button></form></dialog>
<?php endforeach;?>

<dialog id="user-modal"><form method="post" action="<?= site_url($createAction) ?>" class="modal-card"><?= csrf_field() ?><div class="modal-head"><h2><?= esc($buttonLabel) ?></h2><button type="button" data-close><?= ui_icon('x') ?></button></div><label>Display Name<input name="display_name" required maxlength="120"></label><label>Username<input name="username" required maxlength="80"></label><input type="hidden" name="role" value="<?= $isAdminManagement?'manager':'facilitator' ?>">
<span class="field-label">Assigned Sports</span><div class="sport-checks managed-sport-checks">
<?php foreach($sports as $sport): $sportId=(int)$sport['id']; ?>
<label class="check"><input type="checkbox" name="sport_ids[]" value="<?= $sportId ?>" <?= $singleManagerSport?'checked disabled aria-disabled="true"':'' ?>> <?= esc($sportLabel($sport)) ?></label><?php if($singleManagerSport):?><input type="hidden" name="sport_ids[]" value="<?= $sportId ?>"><?php endif;?>
<?php endforeach;?></div>
<p class="form-note"><?= $isAdminManagement ? 'Tournament Managers require at least one sport.' : ($singleManagerSport ? 'This sport is fixed because your Tournament Manager account has one assigned sport.' : 'Facilitators require at least one sport from your assigned sports.') ?></p>
<label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><p class="form-note">A secure password is generated automatically and shown after creation.</p><button class="btn primary full"><?= ui_icon('save') ?><span>Create Account</span></button></form></dialog>
<?= $this->endSection() ?>
