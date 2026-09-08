<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$formatLabel = static fn(string $format): string => $format === 'double_elimination' ? 'Double Elimination' : 'Single Elimination';
$grouped = ['upper' => [], 'lower' => [], 'grand' => []];
foreach ($schedules as $schedule) {
    $side = (string) ($schedule['bracket_side'] ?? 'upper');
    if (! isset($grouped[$side])) $side = 'upper';
    $grouped[$side][$schedule['round']][] = $schedule;
}
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

<section class="panel">
    <div class="panel-head"><div><h2><?= esc($selectedSport['name'] ?? 'Tournament') ?> Bracket</h2><p><?= $schedules ? esc($formatLabel((string) ($schedules[0]['tournament_format'] ?? 'single_elimination'))) : 'Generate a bracket to begin.' ?></p></div><a href="<?= site_url('schedules') ?>">Open Master Schedule</a></div>
    <?php if ($schedules): ?>
        <div class="management-bracket">
            <?php foreach (['upper' => 'Winner Bracket', 'lower' => 'Loser Bracket', 'grand' => 'Championship'] as $side => $label): ?>
                <?php if (!empty($grouped[$side])): ?>
                    <div class="bracket-lane">
                        <h3><?= esc($label) ?></h3>
                        <div class="bracket-rounds">
                            <?php foreach ($grouped[$side] as $round => $matches): ?>
                                <div class="bracket-column">
                                    <b class="bracket-round-title"><?= esc($round) ?></b>
                                    <?php foreach ($matches as $match): ?>
                                        <article class="tournament-match <?= !empty($match['is_conditional']) ? 'conditional' : '' ?>">
                                            <div class="match-meta"><span><?= esc($match['match_code'] ?? '—') ?></span><span><?= strtoupper(esc($match['status'])) ?></span></div>
                                            <?php if (($match['result_type'] ?? '') === 'judged'): ?>
                                                <strong>All participating teams</strong>
                                            <?php else: ?>
                                                <div class="bracket-team"><span><?= esc($match['slot_a_label'] ?? 'TBD') ?></span></div>
                                                <div class="bracket-team"><span><?= esc($match['slot_b_label'] ?? 'TBD') ?></span></div>
                                            <?php endif; ?>
                                            <small><?= esc(date('M j · g:i A', strtotime($match['match_date']))) ?> · <?= esc($match['court_label'] ?: ($match['location_name'] ?? '—')) ?></small>
                                            <?php if (!empty($match['scheduling_note'])): ?><em><?= esc($match['scheduling_note']) ?></em><?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
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
