<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$manageMode = (string) ($manageMode ?? $roleType ?? '');
$isAdminManagement = $manageMode === 'admin';
$roleLabels = [
    'manager' => 'Sports Manager',
    'validator' => 'Validator',
    'facilitator' => 'Facilitator',
];
$roleOptions = $roleOptions ?? ($roleType ? [$roleType] : []);
$hasActiveEvent = ! empty($activeEvent);
$canCreate = $isAdminManagement || $hasActiveEvent;
$createAction = $isAdminManagement ? 'users' : 'facilitators';
?>

<div class="page-head">
    <div>
        <h1><?= esc($title) ?></h1>
        <p><?= $isAdminManagement ? 'Create and manage Sports Managers, Validators, and Facilitators.' : 'Create, update, deactivate, and remove facilitator accounts.' ?></p>
    </div>
    <button class="btn primary" data-modal="user-modal" <?= $canCreate ? '' : 'disabled' ?>>
        + Add <?= $isAdminManagement ? 'User' : 'Facilitator' ?>
    </button>
</div>

<?php if (! $hasActiveEvent): ?>
    <div class="alert error">
        No event is currently active. Facilitator accounts cannot be created or assigned sports until an event is activated.
        Existing facilitator profile/status changes remain available without clearing historical assignments.
    </div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Name</th>
                    <?php if ($isAdminManagement): ?><th>Role</th><?php endif; ?>
                    <th>Status</th>
                    <th>Assigned Sports</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $user): ?>
                <?php $userRole = (string) ($user['role'] ?? $roleType ?? ''); ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><b><?= esc($user['username']) ?></b></td>
                    <td><?= esc($user['display_name']) ?></td>
                    <?php if ($isAdminManagement): ?><td><span class="badge neutral"><?= esc($roleLabels[$userRole] ?? ucfirst($userRole)) ?></span></td><?php endif; ?>
                    <td><span class="badge <?= $user['status'] === 'active' ? 'official' : 'neutral' ?>"><?= strtoupper(esc($user['status'])) ?></span></td>
                    <td>
                        <?php if ($userRole === 'facilitator' && ! empty($user['sports'])): ?>
                            <?php foreach ($user['sports'] as $sport): ?><span class="chip"><?= esc($sport['name'] . ' ' . $sport['category']) ?></span><?php endforeach; ?>
                        <?php elseif ($userRole === 'facilitator'): ?>
                            <span class="muted"><?= $hasActiveEvent ? 'No active-event sports assigned' : 'No active event' ?></span>
                        <?php else: ?>
                            <span class="muted">Not required</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(date('Y-m-d', strtotime($user['created_at']))) ?></td>
                    <td>
                        <div class="row-actions">
                            <button class="btn tiny" data-modal="user-edit-<?= (int) $user['id'] ?>">Edit</button>
                            <form method="post"
                                  action="<?= site_url(($isAdminManagement ? 'users/' : 'facilitators/') . $user['id'] . '/delete') ?>"
                                  data-confirm="Delete <?= esc($user['display_name'], 'attr') ?>'s account? This cannot be undone, although supported audit references will remain.">
                                <?= csrf_field() ?>
                                <button class="btn tiny danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $users): ?>
                <tr><td colspan="<?= $isAdminManagement ? 8 : 7 ?>" class="empty">No accounts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($users as $user): ?>
    <?php
    $userRole = (string) ($user['role'] ?? $roleType ?? '');
    $assignedIds = array_map('intval', array_column($user['sports'] ?? [], 'id'));
    $updateAction = $isAdminManagement ? 'users/' . $user['id'] . '/update' : 'facilitators/' . $user['id'] . '/update';
    ?>
    <dialog id="user-edit-<?= (int) $user['id'] ?>">
        <form method="post"
              action="<?= site_url($updateAction) ?>"
              class="modal-card wide"
              data-role-managed-form
              data-original-status="<?= esc($user['status'], 'attr') ?>"
              data-confirm-inactive="Deactivate <?= esc($user['display_name'], 'attr') ?>? They will no longer be able to sign in or use authenticated features.">
            <?= csrf_field() ?>
            <div class="modal-head">
                <h2>Edit <?= esc($roleLabels[$userRole] ?? 'User') ?></h2>
                <button type="button" data-close aria-label="Close">×</button>
            </div>

            <div class="form-grid">
                <label>Username<input name="username" minlength="3" maxlength="80" required value="<?= esc($user['username']) ?>"></label>
                <label>Full Name<input name="display_name" maxlength="120" required value="<?= esc($user['display_name']) ?>"></label>

                <?php if ($isAdminManagement): ?>
                    <label>Role
                        <select name="role" required data-user-role>
                            <?php foreach ($roleOptions as $optionRole): ?>
                                <option value="<?= esc($optionRole) ?>"
                                        <?= $userRole === $optionRole ? 'selected' : '' ?>
                                        <?= $optionRole === 'facilitator' && ! $hasActiveEvent && $userRole !== 'facilitator' ? 'disabled' : '' ?>>
                                    <?= esc($roleLabels[$optionRole] ?? ucfirst($optionRole)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="role" value="facilitator">
                <?php endif; ?>

                <label>Status
                    <select name="status">
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </label>
                <label>New Password<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current"></label>
            </div>

            <div data-sport-assignment <?= $userRole === 'facilitator' ? '' : 'hidden' ?>>
                <span class="field-label">Assigned Sports</span>
                <?php if ($hasActiveEvent): ?>
                    <div class="sport-checks">
                        <?php foreach ($sports as $sport): ?>
                            <label class="check">
                                <input type="checkbox" name="sport_ids[]" value="<?= (int) $sport['id'] ?>" <?= in_array((int) $sport['id'], $assignedIds, true) ? 'checked' : '' ?>>
                                <?= esc($sport['name'] . ' · ' . $sport['category']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-note">Facilitators require at least one sport from the active event.</p>
                <?php else: ?>
                    <p class="form-note">No active event is available. Saving profile or status changes will preserve this facilitator's existing sport-assignment records.</p>
                <?php endif; ?>
            </div>

            <div class="password-rules">
                <b>Password Requirements</b>
                <span>At least 8 characters</span>
                <span>One uppercase and one lowercase letter</span>
                <span>One number</span>
                <span>One special character</span>
            </div>
            <button class="btn primary full" type="submit">Save Changes</button>
        </form>
    </dialog>
<?php endforeach; ?>

<?php if ($canCreate): ?>
    <dialog id="user-modal">
        <form method="post" action="<?= site_url($createAction) ?>" class="modal-card wide" data-role-managed-form>
            <?= csrf_field() ?>
            <div class="modal-head">
                <h2>Add <?= $isAdminManagement ? 'User' : 'Facilitator' ?></h2>
                <button type="button" data-close aria-label="Close">×</button>
            </div>

            <div class="form-grid">
                <label>Username<input name="username" minlength="3" maxlength="80" required autocomplete="off"></label>
                <label>Full Name<input name="display_name" maxlength="120" required></label>

                <?php if ($isAdminManagement): ?>
                    <label>Role
                        <select name="role" required data-user-role>
                            <?php foreach ($roleOptions as $optionRole): ?>
                                <option value="<?= esc($optionRole) ?>" <?= $optionRole === 'facilitator' && ! $hasActiveEvent ? 'disabled' : '' ?>>
                                    <?= esc($roleLabels[$optionRole] ?? ucfirst($optionRole)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="role" value="facilitator">
                <?php endif; ?>

                <label>Status
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
                <label>Password<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
            </div>

            <div class="password-rules">
                <b>Password Requirements</b>
                <span>At least 8 characters</span>
                <span>One uppercase and one lowercase letter</span>
                <span>One number</span>
                <span>One special character</span>
            </div>

            <div data-sport-assignment <?= $isAdminManagement ? 'hidden' : '' ?>>
                <span class="field-label">Assign Sports</span>
                <div class="sport-checks">
                    <?php foreach ($sports as $sport): ?>
                        <label class="check"><input type="checkbox" name="sport_ids[]" value="<?= (int) $sport['id'] ?>"> <?= esc($sport['name'] . ' · ' . $sport['category']) ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="form-note">Facilitators require at least one sport from the active event.</p>
            </div>

            <button class="btn primary full" type="submit">Create Account</button>
        </form>
    </dialog>
<?php endif; ?>

<?= $this->endSection() ?>
