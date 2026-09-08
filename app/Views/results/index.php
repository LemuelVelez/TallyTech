<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$role = (string) session()->get('role');
$userId = (int) session()->get('user_id');
$canSubmit = in_array($role, ['manager', 'facilitator'], true);
$usedScheduleIds = array_map('intval', array_column($results, 'schedule_id'));
$availableSchedules = array_values(array_filter($schedules, static fn(array $schedule): bool => ! in_array((int) $schedule['id'], $usedScheduleIds, true) && $schedule['status'] !== 'cancelled' && (($schedule['result_type'] ?? '') !== 'match' || (!empty($schedule['team_a_id']) && !empty($schedule['team_b_id'])))));
$maxAvailableSetCount = 1;
foreach ($availableSchedules as $schedule) {
    $maxAvailableSetCount = max($maxAvailableSetCount, max(1, min(9, (int) ($schedule['set_count'] ?? 1))));
}
$scoreFor = static function (array $match, ?int $teamId): string {
    if (!$teamId || !array_key_exists($teamId, $match['score_by_team'] ?? [])) return '';
    return rtrim(rtrim(number_format((float) $match['score_by_team'][$teamId], 2, '.', ''), '0'), '.');
};
$statusLabel = static function (array $match): string {
    if (($match['result_status'] ?? '') === 'validated') return 'FINAL';
    if (($match['status'] ?? '') === 'played') return 'AWAITING VALIDATION';
    return strtoupper((string) ($match['status'] ?? 'scheduled'));
};
$entrySetScores = static function (array $entry): array {
    $raw = $entry['set_scores'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return array_values($decoded);
    }
    return array_key_exists('raw_score', $entry) ? [$entry['raw_score']] : [];
};
$ordinal = static function (int $number): string {
    if ($number % 100 >= 11 && $number % 100 <= 13) return $number . 'th';
    return $number . match ($number % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
};
?>
<div class="page-head"><div><h1><?= esc($title) ?></h1><p><?= $resultType === 'match' ? 'Enter live match scores. Pending results can be corrected before validator acceptance.' : 'Enter judged scores; placements are calculated from submitted scores and become official only after validation.' ?></p></div><?php if ($canSubmit): ?><button class="btn primary" data-modal="result-modal" <?= !$availableSchedules ? 'disabled' : '' ?>><?= ui_icon('plus') ?><span>Encode Result</span></button><?php endif; ?></div>

<section class="panel">
    <div class="panel-head"><div><h2><?= $resultType === 'match' ? 'Match Bracket' : 'Judged Event Bracket' ?></h2><p><?= $resultType === 'match' ? 'Winner, loser, and championship lanes are horizontally scrollable.' : 'Each judged sport keeps one championship schedule card.' ?></p></div></div>
    <?php if ($resultType === 'match'): ?>
        <?php $sportBrackets = []; foreach ($schedules as $schedule) { $key = $schedule['sport_name'].'|'.$schedule['category']; $sportBrackets[$key]['sport_name'] = $schedule['sport_name']; $sportBrackets[$key]['category'] = $schedule['category']; $sportBrackets[$key]['format'] = $schedule['tournament_format'] ?? 'single_elimination'; $side = $schedule['bracket_side'] ?? 'upper'; if (!in_array($side, ['upper','lower','grand'], true)) $side = 'upper'; $sportBrackets[$key]['lanes'][$side][$schedule['round']][] = $schedule; } ?>
        <?php foreach ($sportBrackets as $sportBracket): ?>
            <div class="bracket-category-block">
                <div class="section-title bracket-category-title"><h3><?= esc($sportBracket['sport_name'].' · '.$sportBracket['category']) ?></h3><span><?= ($sportBracket['format'] ?? '') === 'double_elimination' ? 'Double Elimination' : 'Single Elimination' ?></span></div>
                <div class="bracket-board">
                    <?php foreach (['upper' => ($sportBracket['format'] ?? '') === 'double_elimination' ? 'Winner Bracket' : 'Championship Path', 'lower' => 'Loser Bracket', 'grand' => 'Championship'] as $side => $laneLabel): ?>
                        <?php if (!empty($sportBracket['lanes'][$side])): ?>
                            <div class="bracket-lane">
                                <h3><?= esc($laneLabel) ?></h3>
                                <div class="bracket-rounds">
                                    <?php foreach ($sportBracket['lanes'][$side] as $round => $matches): ?>
                                        <div class="bracket-column">
                                            <b class="bracket-round-title"><?= esc($round) ?></b>
                                            <?php foreach ($matches as $match): ?>
                                                <?php $winnerTeamId = (int) ($match['winner_team_id'] ?? 0); ?>
                                                <article class="tournament-match <?= !empty($match['is_conditional']) ? 'conditional' : '' ?>">
                                                    <div class="match-meta"><span class="badge neutral"><?= esc($match['match_code'] ?? '—') ?></span><span><?= esc($statusLabel($match)) ?></span></div>
                                                    <div class="bracket-team <?= $winnerTeamId > 0 && $winnerTeamId === (int) ($match['team_a_id'] ?? 0) ? 'winner' : '' ?>"><span><?= esc($match['slot_a_label'] ?? 'TBD') ?></span><b><?= esc($scoreFor($match, !empty($match['team_a_id']) ? (int) $match['team_a_id'] : null)) ?></b></div>
                                                    <div class="bracket-team <?= $winnerTeamId > 0 && $winnerTeamId === (int) ($match['team_b_id'] ?? 0) ? 'winner' : '' ?>"><span><?= esc($match['slot_b_label'] ?? 'TBD') ?></span><b><?= esc($scoreFor($match, !empty($match['team_b_id']) ? (int) $match['team_b_id'] : null)) ?></b></div>
                                                    <small><?= esc($match['court_label'] ?: ($match['location_name'] ?? '—')) ?> · <?= esc(date('M j, Y · g:i A', strtotime($match['match_date']))) ?></small>
                                                    <?php if (!empty($match['is_conditional']) && ($match['status'] ?? '') === 'cancelled'): ?><span class="badge neutral conditional-badge">IF NECESSARY</span><?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$sportBrackets): ?><div class="empty">No schedules available.</div><?php endif; ?>
    <?php else: ?>
        <?php $judgedBySport = []; foreach ($schedules as $schedule) { $key = $schedule['sport_name'].'|'.$schedule['category']; if (!isset($judgedBySport[$key])) $judgedBySport[$key] = $schedule; } ?>
        <div class="bracket-board">
            <?php foreach ($judgedBySport as $schedule): ?>
                <article class="tournament-match judged-championship-card">
                    <div class="match-meta"><span><?= esc($schedule['match_code'] ?? '—') ?></span><span><?= esc($statusLabel($schedule)) ?></span></div>
                    <strong><?= esc($schedule['sport_name'].' · '.$schedule['category']) ?></strong>
                    <div class="bracket-team"><span>Championship</span><b><?= esc($schedule['round']) ?></b></div>
                    <small>All participating teams</small>
                    <small><?= esc($schedule['court_label'] ?: ($schedule['location_name'] ?? '—')) ?> · <?= esc(date('M j, Y · g:i A', strtotime($schedule['match_date']))) ?></small>
                </article>
            <?php endforeach; ?>
            <?php if (!$judgedBySport): ?><div class="empty">No schedules available.</div><?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel"><div class="panel-head"><div><h2><?= $resultType === 'match' ? 'Match Schedules' : 'Judged Schedules' ?></h2><p>Detailed active-event schedule list.</p></div></div><div class="table-wrap"><table><thead><tr><th>Sport</th><th>Round</th><th>Location</th><th>Teams</th><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($schedules as $schedule): ?><tr><td><b><?= esc($schedule['sport_name']) ?></b><small class="muted"><?= esc($schedule['category']) ?></small></td><td><?= esc($schedule['round']) ?></td><td><?= esc($schedule['court_label'] ?: ($schedule['location_name'] ?? '—')) ?></td><td><?= $resultType === 'match' ? esc(($schedule['slot_a_label'] ?? 'TBD').' vs '.($schedule['slot_b_label'] ?? 'TBD')) : 'All participating teams' ?></td><td><?= esc(date('M j, g:i A', strtotime($schedule['match_date']))) ?></td><td><span class="badge neutral"><?= strtoupper(esc($schedule['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$schedules): ?><tr><td colspan="6" class="empty">No schedules available.</td></tr><?php endif; ?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><h2><?= esc($title) ?></h2><p>Official and unofficial results are clearly separated by status.</p></div></div><div class="result-list">
<?php if (!$results): ?><div class="empty">No results have been submitted yet.</div><?php endif; ?>
<?php foreach ($results as $result): ?>
<?php
$canEditResult = $canSubmit && $result['status'] === 'pending' && ($role === 'manager' || (int) $result['submitted_by'] === $userId);
$entryByTeam = [];
foreach ($result['entries'] as $entry) { $entryByTeam[(int) $entry['team_id']] = $entry; }
?>
<article class="result-card"><div class="result-top"><div><b><?= esc($result['sport_name'].' · '.$result['category']) ?></b><span><?= esc($result['round']) ?> · <?= esc($result['location_name'] ?? '') ?></span></div><span class="badge <?= $result['status'] === 'validated' ? 'official' : 'unofficial' ?>"><?= $result['status'] === 'validated' ? 'OFFICIAL' : 'UNOFFICIAL' ?></span></div><div class="score-entries"><?php foreach ($result['entries'] as $entry): ?><div><span><?= esc($entry['team_name']) ?></span><strong><?= number_format((float) $entry['raw_score'], 2) ?></strong><?php if ($entry['placement']): ?><small>#<?= (int) $entry['placement'] ?> · <?= esc(format_points($entry['allocated_points'])) ?> pts</small><?php endif; ?></div><?php endforeach; ?></div><?php if (trim((string) $result['notes']) !== ''): ?><p class="result-notes"><?= esc($result['notes']) ?></p><?php endif; ?><div class="result-foot"><small>Submitted by <?= esc($result['submitted_by_name'] ?? 'System') ?> · <?= esc(date('M j, g:i A', strtotime($result['submitted_at']))) ?></small><div class="result-actions"><?php if ($role === 'validator' && $result['status'] === 'pending'): ?><form method="post" action="<?= site_url('results/'.$result['id'].'/validate') ?>" class="validate-form" data-confirm="Validate this result as official? After validation it cannot be edited or deleted."><?= csrf_field() ?><label class="check compact-check"><input type="checkbox" name="confirmed_sheet" value="1" required> Compared with official score sheet/form</label><button class="btn tiny primary"><?= ui_icon('check-circle') ?><span>Validate as Official</span></button></form><?php elseif ($result['status'] === 'validated'): ?><small>Validated by <?= esc($result['validated_by_name'] ?? 'Validator') ?></small><?php elseif ($canEditResult): ?><button class="btn tiny" data-modal="result-edit-<?= (int) $result['id'] ?>"><?= ui_icon('pencil') ?><span>Edit</span></button><form method="post" action="<?= site_url('results/'.$result['id'].'/delete') ?>" data-confirm="Delete this unofficial result? The schedule will return to scheduled status."><?= csrf_field() ?><button class="btn tiny danger"><?= ui_icon('trash') ?><span>Delete</span></button></form><?php endif; ?></div></div></article>
<?php if ($canEditResult): ?><dialog id="result-edit-<?= (int) $result['id'] ?>"><form method="post" action="<?= site_url('results/'.$result['id'].'/update') ?>" class="modal-card wide" data-confirm="Save these result changes? The updated result will remain unofficial and continue awaiting validator approval."><?= csrf_field() ?><input type="hidden" name="schedule_id" value="<?= (int) $result['schedule_id'] ?>"><div class="modal-head"><h2>Edit Unofficial Result</h2><button type="button" data-close aria-label="Close"><?= ui_icon('x') ?></button></div><div class="form-note result-context"><?= esc($result['sport_name'].' · '.$result['round'].' · '.date('M j, g:i A', strtotime($result['match_date']))) ?></div><?php if ($resultType === 'match'): ?><?php $setCount = max(1, min(9, (int) ($result['set_count'] ?? 1))); $teamAEntry = $entryByTeam[(int) $result['team_a_id']] ?? []; $teamBEntry = $entryByTeam[(int) $result['team_b_id']] ?? []; $teamASets = $entrySetScores($teamAEntry); $teamBSets = $entrySetScores($teamBEntry); ?><?php for ($set = 0; $set < $setCount; $set++): ?><div class="form-grid match-set-row"><label><?= esc($teamAEntry['team_name'] ?? 'Team A') ?> · <?= esc($ordinal($set + 1)) ?> Set<input type="number" step="0.01" min="0" max="99999999.99" name="team_a_sets[]" <?= $set === 0 ? 'required' : '' ?> value="<?= esc($teamASets[$set] ?? '') ?>"></label><label><?= esc($teamBEntry['team_name'] ?? 'Team B') ?> · <?= esc($ordinal($set + 1)) ?> Set<input type="number" step="0.01" min="0" max="99999999.99" name="team_b_sets[]" <?= $set === 0 ? 'required' : '' ?> value="<?= esc($teamBSets[$set] ?? '') ?>"></label></div><?php endfor; ?><?php else: ?><div class="judged-grid"><?php foreach ($teams as $team): ?><label><?= esc($team['name']) ?><input type="number" step="0.01" min="0" max="99999999.99" name="judged[<?= (int) $team['id'] ?>]" value="<?= esc($entryByTeam[(int) $team['id']]['raw_score'] ?? '') ?>" placeholder="Score"></label><?php endforeach; ?></div><?php endif; ?><label>Notes<textarea name="notes" rows="3" placeholder="Optional notes from the score sheet"><?= esc($result['notes']) ?></textarea></label><div class="warning-box">Editing keeps this result <b>UNOFFICIAL</b> and awaiting validator approval.</div><button class="btn primary full"><?= ui_icon('save') ?><span>Save Result Changes</span></button></form></dialog><?php endif; ?>
<?php endforeach; ?>
</div></section>

<?php if ($canSubmit): ?><dialog id="result-modal"><form method="post" action="<?= site_url('results') ?>" class="modal-card wide" data-confirm="Submit this result as unofficial? It will be visible in TallyTech and will require validator approval before becoming official."><?= csrf_field() ?><div class="modal-head"><h2>Encode <?= $resultType === 'match' ? 'Match' : 'Judged' ?> Result</h2><button type="button" data-close aria-label="Close"><?= ui_icon('x') ?></button></div><label>Schedule<select name="schedule_id" required data-result-schedule><option value="">Select schedule</option><?php foreach ($availableSchedules as $schedule): ?><option value="<?= (int) $schedule['id'] ?>" data-set-count="<?= max(1, min(9, (int) ($schedule['set_count'] ?? 1))) ?>" data-team-a="<?= esc($schedule['slot_a_label'] ?? 'Team A', 'attr') ?>" data-team-b="<?= esc($schedule['slot_b_label'] ?? 'Team B', 'attr') ?>"><?= esc($schedule['sport_name'].' · '.$schedule['category'].' · '.$schedule['round'].' · '.date('M j g:i A', strtotime($schedule['match_date']))) ?></option><?php endforeach; ?></select></label><?php if ($resultType === 'match'): ?><div data-match-set-inputs><?php for ($set = 0; $set < $maxAvailableSetCount; $set++): ?><div class="form-grid match-set-row" data-set-index="<?= $set + 1 ?>"><label><span data-team-a-label>Team A</span> · <?= esc($ordinal($set + 1)) ?> Set<input type="number" step="0.01" min="0" max="99999999.99" name="team_a_sets[]" <?= $set === 0 ? 'required' : '' ?>></label><label><span data-team-b-label>Team B</span> · <?= esc($ordinal($set + 1)) ?> Set<input type="number" step="0.01" min="0" max="99999999.99" name="team_b_sets[]" <?= $set === 0 ? 'required' : '' ?>></label></div><?php endfor; ?></div><?php else: ?><div class="judged-grid"><?php foreach ($teams as $team): ?><label><?= esc($team['name']) ?><input type="number" step="0.01" min="0" max="99999999.99" name="judged[<?= (int) $team['id'] ?>]" placeholder="Score"></label><?php endforeach; ?></div><?php endif; ?><label>Notes<textarea name="notes" rows="3" placeholder="Optional notes from the score sheet"></textarea></label><div class="warning-box">This submission is published immediately as <b>UNOFFICIAL</b>. A validator must compare it with the actual score sheet/form before it becomes official.</div><button class="btn primary full"><?= ui_icon('clipboard-check') ?><span>Submit Unofficial Result</span></button></form></dialog><?php endif; ?>

<?php if ($canSubmit && $resultType === 'match'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.querySelector('[data-result-schedule]');
    if (!select) return;
    var rows = Array.from(document.querySelectorAll('[data-match-set-inputs] [data-set-index]'));
    var update = function () {
        var option = select.options[select.selectedIndex];
        var setCount = Math.max(1, parseInt(option && option.dataset.setCount ? option.dataset.setCount : '1', 10));
        var teamA = option && option.dataset.teamA ? option.dataset.teamA : 'Team A';
        var teamB = option && option.dataset.teamB ? option.dataset.teamB : 'Team B';
        rows.forEach(function (row) {
            var index = parseInt(row.dataset.setIndex || '1', 10);
            var enabled = index <= setCount;
            row.hidden = !enabled;
            row.querySelectorAll('input').forEach(function (input) {
                input.disabled = !enabled;
                input.required = enabled && index === 1;
                if (!enabled) input.value = '';
            });
            var a = row.querySelector('[data-team-a-label]');
            var b = row.querySelector('[data-team-b-label]');
            if (a) a.textContent = teamA;
            if (b) b.textContent = teamB;
        });
    };
    select.addEventListener('change', update);
    update();
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
