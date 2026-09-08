<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$stageValue = static function (array $schedule): string {
    $phase = (string) ($schedule['phase'] ?? '');
    $side = (string) ($schedule['bracket_side'] ?? '');
    if ($phase === 'lower_r1') return 'lower_r1';
    if ($phase === 'quarter') return 'quarter';
    if ($phase === 'semi') return 'semi';
    if ($phase === 'playoff') return 'playoff';
    if ($phase === 'tiebreaker') return 'tiebreaker';
    if ($phase === 'final' && $side === 'upper') return 'upper_final';
    if ($phase === 'final' && $side === 'lower') return 'lower_final';
    if ($phase === 'final' && $side === 'grand' && str_contains(strtolower((string) ($schedule['round'] ?? '')), 'grand')) return 'grand_final';
    return 'final';
};
$stageOptions = [
    'playoff' => 'Playoff / Round of 16',
    'quarter' => 'Quarter Final',
    'semi' => 'Semi Final',
    'final' => 'Final',
    'lower_r1' => 'Lower Round 1',
    'upper_final' => 'Upper Final',
    'lower_final' => 'Lower Final',
    'grand_final' => 'Grand Final',
    'tiebreaker' => 'Bracket Reset Final',
];
?>
<div class="page-head">
    <div><h1>Tournament Schedules</h1><p>Official master schedule for match times, courts, rounds, and team movement.</p></div>
    <div class="page-actions">
        <a class="btn" href="<?= site_url('brackets') ?>"><?= ui_icon('trophy') ?><span>Bracket Management</span></a>
        <button class="btn primary" data-modal="schedule-modal" <?= !$activeEvent || !$locations ? 'disabled' : '' ?>><?= ui_icon('plus') ?><span>Add Schedule</span></button>
    </div>
</div>
<?php if ($activeEvent): ?><div class="event-banner"><b>Active event:</b> <?= esc($activeEvent['name']) ?></div><?php endif; ?>
<?php if ($activeEvent && !$locations): ?><div class="alert error">Add or enable a location before creating schedules.</div><?php endif; ?>
<section class="panel">
    <div class="panel-head"><div><h2>Master Schedule</h2><p>Players and facilitators can use this operational view to confirm where and when each match is played.</p></div></div>
    <div class="table-wrap"><table class="schedule-table"><thead><tr><th>Match ID</th><th>Time</th><th>Sport</th><th>Round</th><th>Team A</th><th>Team B</th><th>Court</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($schedules as $schedule): ?>
        <?php $isJudged = ($schedule['result_type'] ?? '') === 'judged'; ?>
        <tr>
            <td><span class="badge"><?= esc($schedule['match_code'] ?? '—') ?></span></td>
            <td><b><?= esc(date('g:i A', strtotime($schedule['match_date']))) ?></b><small class="muted"><?= esc(date('M j, Y', strtotime($schedule['match_date']))) ?></small></td>
            <td><b><?= esc($schedule['sport_name']) ?></b><small class="muted"><?= esc($schedule['category']) ?></small></td>
            <td><?= esc($schedule['round']) ?><small class="muted"><?= esc(ucwords(str_replace('_', ' ', $schedule['tournament_format'] ?? 'single_elimination'))) ?></small></td>
            <td><?= $isJudged ? '—' : esc($schedule['slot_a_label'] ?? 'TBD') ?></td>
            <td><?= $isJudged ? '—' : esc($schedule['slot_b_label'] ?? 'TBD') ?></td>
            <td><b><?= esc($schedule['court_label'] ?: ($schedule['location_name'] ?? '—')) ?></b><?php if (!empty($schedule['court_label']) && !empty($schedule['location_name'])): ?><small class="muted"><?= esc($schedule['location_name']) ?></small><?php endif; ?></td>
            <td><span class="badge <?= $schedule['status'] === 'played' ? 'official' : ($schedule['status'] === 'cancelled' ? 'unofficial' : 'neutral') ?>"><?= strtoupper(esc($schedule['status'])) ?></span></td>
            <td><div class="row-actions"><button class="btn tiny" data-modal="schedule-edit-<?= (int) $schedule['id'] ?>"><?= ui_icon('pencil') ?><span>Edit</span></button><form method="post" action="<?= site_url('schedules/'.$schedule['id'].'/delete') ?>" data-confirm="Delete this schedule? Schedules with submitted results cannot be removed."><?= csrf_field() ?><button class="btn tiny danger"><?= ui_icon('trash') ?><span>Delete</span></button></form></div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$schedules): ?><tr><td colspan="9" class="empty">No schedules for the active event.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<?php foreach ($schedules as $schedule): ?>
<dialog id="schedule-edit-<?= (int) $schedule['id'] ?>">
    <form method="post" action="<?= site_url('schedules/'.$schedule['id'].'/update') ?>" class="modal-card wide" data-original-status="<?= esc($schedule['status'], 'attr') ?>" data-confirm-status-change="Change this schedule status? This can affect result-entry availability and the event workflow.">
        <?= csrf_field() ?>
        <input type="hidden" name="match_code" value="<?= esc($schedule['match_code'] ?? '') ?>">
        <input type="hidden" name="bracket_order" value="<?= (int) ($schedule['bracket_order'] ?? 0) ?>">
        <input type="hidden" name="feeds_from_a" value="<?= esc($schedule['feeds_from_a'] ?? '') ?>">
        <input type="hidden" name="feeds_from_a_type" value="<?= esc($schedule['feeds_from_a_type'] ?? '') ?>">
        <input type="hidden" name="feeds_from_b" value="<?= esc($schedule['feeds_from_b'] ?? '') ?>">
        <input type="hidden" name="feeds_from_b_type" value="<?= esc($schedule['feeds_from_b_type'] ?? '') ?>">
        <input type="hidden" name="is_conditional" value="<?= (int) ($schedule['is_conditional'] ?? 0) ?>">
        <div class="modal-head"><h2>Edit Team Schedule</h2><button type="button" data-close aria-label="Close"><?= ui_icon('x') ?></button></div>
        <label>Sport<select name="sport_id" required><?php foreach ($sports as $sport): ?><option value="<?= (int) $sport['id'] ?>" <?= (int) $schedule['sport_id'] === (int) $sport['id'] ? 'selected' : '' ?>><?= esc($sport['name'].' · '.$sport['category']) ?></option><?php endforeach; ?></select></label>
        <div class="form-grid">
            <label>Tournament Format<select name="tournament_format" required><option value="single_elimination" <?= ($schedule['tournament_format'] ?? 'single_elimination') === 'single_elimination' ? 'selected' : '' ?>>Single Elimination</option><option value="double_elimination" <?= ($schedule['tournament_format'] ?? '') === 'double_elimination' ? 'selected' : '' ?>>Double Elimination</option></select></label>
            <label>Round / Stage<select name="stage" required><?php $currentStage = $stageValue($schedule); foreach ($stageOptions as $value => $label): ?><option value="<?= esc($value) ?>" <?= $currentStage === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></label>
            <label>Location<select name="location_id" required><?php foreach ($allLocations as $location): ?><?php if ((int) $location['is_active'] === 1 || (int) $schedule['location_id'] === (int) $location['id']): ?><option value="<?= (int) $location['id'] ?>" <?= (int) $schedule['location_id'] === (int) $location['id'] ? 'selected' : '' ?>><?= esc($location['name']) ?><?= (int) $location['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endif; ?><?php endforeach; ?></select></label>
            <label>Court<input name="court_label" maxlength="60" value="<?= esc($schedule['court_label'] ?? '') ?>" placeholder="Court 1"></label>
            <label>Status<select name="status"><option value="scheduled" <?= $schedule['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option><option value="played" <?= $schedule['status'] === 'played' ? 'selected' : '' ?>>Played</option><option value="cancelled" <?= $schedule['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select></label>
            <label>Match Date & Time<input type="datetime-local" name="match_date" required value="<?= esc(date('Y-m-d\TH:i', strtotime($schedule['match_date']))) ?>"></label>
            <label>Team A<select name="team_a_id"><option value="">TBD / not applicable</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (int) $schedule['team_a_id'] === (int) $team['id'] ? 'selected' : '' ?>><?= esc($team['name']) ?></option><?php endforeach; ?></select></label>
            <label>Team B<select name="team_b_id"><option value="">TBD / not applicable</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (int) $schedule['team_b_id'] === (int) $team['id'] ? 'selected' : '' ?>><?= esc($team['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Scheduling Note<textarea name="scheduling_note" maxlength="255" rows="2" placeholder="Optional operations note"><?= esc($schedule['scheduling_note'] ?? '') ?></textarea></label>
        <button class="btn primary full"><?= ui_icon('save') ?><span>Save Changes</span></button>
    </form>
</dialog>
<?php endforeach; ?>

<dialog id="schedule-modal">
    <form method="post" action="<?= site_url('schedules') ?>" class="modal-card wide">
        <?= csrf_field() ?>
        <div class="modal-head"><h2>Add Team Schedule</h2><button type="button" data-close aria-label="Close"><?= ui_icon('x') ?></button></div>
        <label>Sport<select name="sport_id" required><option value="">Select sport</option><?php foreach ($sports as $sport): ?><option value="<?= (int) $sport['id'] ?>"><?= esc($sport['name'].' · '.$sport['category']) ?></option><?php endforeach; ?></select></label>
        <div class="form-grid">
            <label>Tournament Format<select name="tournament_format" required><option value="single_elimination">Single Elimination</option><option value="double_elimination">Double Elimination</option></select></label>
            <label>Round / Stage<select name="stage" required><?php foreach ($stageOptions as $value => $label): ?><option value="<?= esc($value) ?>" <?= $value === 'semi' ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></label>
            <label>Location<select name="location_id" required><option value="">Select location</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= esc($location['name']) ?></option><?php endforeach; ?></select></label>
            <label>Court<input name="court_label" maxlength="60" placeholder="Court 1"></label>
            <label>Status<select name="status"><option value="scheduled">Scheduled</option><option value="cancelled">Cancelled</option></select></label>
            <label>Match Date & Time<input type="datetime-local" name="match_date" required></label>
            <label>Team A<select name="team_a_id"><option value="">TBD / not applicable</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>"><?= esc($team['name']) ?></option><?php endforeach; ?></select></label>
            <label>Team B<select name="team_b_id"><option value="">TBD / not applicable</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>"><?= esc($team['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Scheduling Note<textarea name="scheduling_note" maxlength="255" rows="2" placeholder="Optional operations note"></textarea></label>
        <p class="form-note">Use Bracket Management to generate linked elimination matches automatically. Manual match schedules require both teams; judged schedules can leave team slots empty.</p>
        <button class="btn primary full"><?= ui_icon('save') ?><span>Save Schedule</span></button>
    </form>
</dialog>
<?= $this->endSection() ?>
