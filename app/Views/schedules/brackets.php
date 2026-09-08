<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$formatLabel = static fn(string $format): string => $format === 'double_elimination' ? 'Double Elimination' : 'Single Elimination';
$bracketFormat = (string) ($schedules[0]['tournament_format'] ?? 'single_elimination');
?>
<div class="page-head">
    <div><h1>Bracket Management</h1><p>Generate elimination structures and manage the progression path from the same schedule data used by scoring.</p></div>
    <button class="btn primary" data-modal="bracket-generator" <?= !$activeEvent || !$sports || !$locations ? 'disabled' : '' ?>><?= ui_icon('plus') ?><span>Generate Bracket</span></button>
</div>
<?php if ($activeEvent): ?><div class="event-banner"><b>Active event:</b> <?= esc($activeEvent['name']) ?></div><?php endif; ?>
<section class="panel bracket-filter-panel">
    <form method="get" action="<?= site_url('brackets') ?>" class="inline-filter">
        <label>Sport<select name="sport" onchange="this.form.submit()"><?php foreach ($sports as $sport): ?><option value="<?= (int) $sport['id'] ?>" <?= (int) $sport['id'] === (int) $selectedSportId ? 'selected' : '' ?>><?= esc($sport['name'].' · '.$sport['category']) ?></option><?php endforeach; ?></select></label>
        <noscript><button class="btn">View</button></noscript>
    </form>
</section>

<section class="panel bracket-management-panel">
    <div class="panel-head"><div><h2><?= esc($selectedSport['name'] ?? 'Tournament') ?> Bracket</h2><p><?= $schedules ? esc($formatLabel($bracketFormat)) : 'Generate a bracket to begin.' ?></p></div><a href="<?= site_url('schedules') ?>">Open Master Schedule</a></div>
    <?php if ($schedules): ?>
        <?= view('partials/bracket_tree', [
            'bracketSchedules' => $schedules,
            'bracketFormat' => $bracketFormat,
            'bracketVariant' => 'management',
            'bracketAriaLabel' => ($selectedSport['name'] ?? 'Tournament') . ' ' . ($selectedSport['category'] ?? '') . ' bracket',
        ]) ?>
    <?php else: ?><div class="empty">No bracket has been generated for this sport.</div><?php endif; ?>
</section>

<dialog id="bracket-generator">
    <form method="post" action="<?= site_url('brackets/generate') ?>" class="modal-card wide" data-confirm="Generate this bracket? Existing schedules for the selected sport will be replaced if they do not have submitted results.">
        <?= csrf_field() ?>
        <div class="modal-head"><h2>Generate Tournament Bracket</h2><button type="button" data-close aria-label="Close"><?= ui_icon('x') ?></button></div>
        <div class="form-grid">
            <label>Sport<select name="sport_id" required><option value="">Select sport</option><?php foreach ($sports as $sport): ?><option value="<?= (int) $sport['id'] ?>" <?= (int) $sport['id'] === (int) $selectedSportId ? 'selected' : '' ?>><?= esc($sport['name'].' · '.$sport['category'].' · '.ucfirst($sport['result_type'])) ?></option><?php endforeach; ?></select></label>
            <label>Tournament Format<select name="tournament_format" required><option value="single_elimination">Single Elimination</option><option value="double_elimination">Double Elimination</option></select></label>
            <label>Location<select name="location_id" required><option value="">Select location</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= esc($location['name']) ?></option><?php endforeach; ?></select></label>
            <label>Court<input name="court_label" maxlength="60" placeholder="Court 1"></label>
            <label>Start Date & Time<input type="datetime-local" name="start_time" required></label>
            <label>Match Interval (minutes)<input type="number" name="interval_minutes" min="15" max="360" step="5" value="60" required></label>
        </div>
        <span class="field-label">Participating Teams</span>
        <div class="sport-checks bracket-team-checks"><?php foreach ($teams as $team): ?><label class="check"><input type="checkbox" name="team_ids[]" value="<?= (int) $team['id'] ?>" checked> <?= esc($team['name']) ?></label><?php endforeach; ?></div>
        <p class="form-note">Single elimination supports 2, 4, 8, or 16 teams. Double elimination uses a four-team winner/loser bracket with an automatic conditional reset final.</p>
        <button class="btn primary full"><?= ui_icon('trophy') ?><span>Generate Bracket</span></button>
    </form>
</dialog>
<?= $this->endSection() ?>
