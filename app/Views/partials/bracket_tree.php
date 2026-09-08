<?php
/**
 * Reusable elimination bracket tree.
 *
 * Expected variables:
 * - $bracketSchedules: resolved schedule rows for a single sport/category bracket.
 * - $bracketFormat: single_elimination|double_elimination (optional; inferred when omitted).
 * - $bracketVariant: public|management (optional).
 * - $bracketAriaLabel: accessible label for the scroll region (optional).
 */
$bracketSchedules = is_array($bracketSchedules ?? null) ? array_values($bracketSchedules) : [];
$bracketVariant = in_array(($bracketVariant ?? 'public'), ['public', 'management'], true) ? $bracketVariant : 'public';
$bracketFormat = (string) ($bracketFormat ?? ($bracketSchedules[0]['tournament_format'] ?? 'single_elimination'));
$bracketFormat = $bracketFormat === 'double_elimination' ? 'double_elimination' : 'single_elimination';
$bracketAriaLabel = (string) ($bracketAriaLabel ?? 'Tournament bracket');

$sideOrder = ['upper' => 0, 'lower' => 1, 'grand' => 2];
usort($bracketSchedules, static function (array $a, array $b) use ($sideOrder): int {
    $sideA = (string) ($a['bracket_side'] ?? 'upper');
    $sideB = (string) ($b['bracket_side'] ?? 'upper');
    $sideCompare = ($sideOrder[$sideA] ?? 99) <=> ($sideOrder[$sideB] ?? 99);
    if ($sideCompare !== 0) return $sideCompare;

    $orderCompare = (int) ($a['bracket_order'] ?? 0) <=> (int) ($b['bracket_order'] ?? 0);
    if ($orderCompare !== 0) return $orderCompare;

    return strcmp((string) ($a['match_date'] ?? ''), (string) ($b['match_date'] ?? ''));
});

$grouped = ['upper' => [], 'lower' => [], 'grand' => []];
foreach ($bracketSchedules as $schedule) {
    $side = (string) ($schedule['bracket_side'] ?? 'upper');
    if (! isset($grouped[$side])) $side = 'upper';
    $round = trim((string) ($schedule['round'] ?? 'Round')) ?: 'Round';
    if (! isset($grouped[$side][$round])) {
        $grouped[$side][$round] = [];
    }
    $grouped[$side][$round][] = $schedule;
}

$hasUpper = ! empty($grouped['upper']);
$hasLower = ! empty($grouped['lower']);
$hasGrand = ! empty($grouped['grand']);
$formatClass = $bracketFormat === 'double_elimination' ? 'double' : 'single';

$statusLabel = static function (array $match): string {
    if (($match['result_status'] ?? '') === 'validated') return 'FINAL';
    if (($match['status'] ?? '') === 'played') return 'AWAITING VALIDATION';
    if (! empty($match['is_conditional']) && ($match['status'] ?? '') === 'cancelled') return 'IF NECESSARY';
    return strtoupper((string) ($match['status'] ?? 'scheduled'));
};

$scoreFor = static function (array $match, ?int $teamId): string {
    if (! $teamId || ! array_key_exists($teamId, $match['score_by_team'] ?? [])) return '';
    $score = (float) $match['score_by_team'][$teamId];
    return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
};

$winnerName = static function (array $match): string {
    $winner = trim((string) ($match['winner_name'] ?? ''));
    if ($winner !== '') return $winner;
    if (($match['result_status'] ?? '') !== 'validated') return '';
    foreach (($match['result_entries'] ?? []) as $entry) {
        if ((int) ($entry['placement'] ?? 0) === 1) {
            return trim((string) ($entry['team_name'] ?? ''));
        }
    }
    return '';
};

$laneIcon = static fn(string $side): string => match ($side) {
    'upper' => 'trophy',
    'lower' => 'target',
    'grand' => 'award',
    default => 'circle',
};

$matchIcon = static function (array $match, string $side): string {
    if (! empty($match['is_conditional'])) return 'alert-triangle';
    if ($side === 'grand') return 'trophy';
    if (($match['result_status'] ?? '') === 'validated') return 'check-circle';
    if ($side === 'lower') return 'target';
    return 'play-circle';
};

// Follow-up: add a team-logo upload migration/field before replacing these deterministic initials marks.
$teamInitials = static function (string $name): string {
    $name = trim($name);
    if ($name === '') return '—';

    $parts = preg_split('/\s+/', $name) ?: [];
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }

    return strtoupper(substr($name, 0, 2));
};

$teamMarkStyle = static function (string $name): string {
    $seed = strtolower(trim($name));
    $hue = hexdec(substr(md5($seed !== '' ? $seed : 'tbd'), 0, 4)) % 360;
    return '--tt-team-hue: ' . $hue . ';';
};
?>
<div class="tt-bracket-component tt-bracket-component--<?= esc($bracketVariant, 'attr') ?>" data-bracket-component>
    <div class="tt-bracket-toolbar" aria-label="Bracket view controls">
        <span class="tt-bracket-toolbar-label"><?= ui_icon('sliders') ?><span>Bracket view</span></span>
        <div class="tt-bracket-toolbar-actions">
            <span class="tt-bracket-zoom-hint" data-bracket-zoom-hint>Ctrl + scroll to zoom</span>
            <div class="tt-bracket-zoom-controls" role="group" aria-label="Bracket zoom">
                <button type="button" class="tt-bracket-zoom-button" data-bracket-zoom-out aria-label="Zoom bracket out">−</button>
                <button type="button" class="tt-bracket-zoom-reset" data-bracket-zoom-reset aria-label="Reset bracket zoom"><span data-bracket-zoom-value>100%</span></button>
                <button type="button" class="tt-bracket-zoom-button" data-bracket-zoom-in aria-label="Zoom bracket in">+</button>
            </div>
        </div>
    </div>
    <div class="tt-bracket-scroll tt-bracket-scroll--<?= esc($bracketVariant, 'attr') ?>" data-bracket-scroll role="region" aria-label="<?= esc($bracketAriaLabel, 'attr') ?>" tabindex="0">
        <div class="tt-bracket-zoom-shell" data-bracket-zoom-shell>
            <div class="tt-bracket-board tt-bracket-board--<?= esc($formatClass, 'attr') ?> tt-bracket-board--<?= esc($bracketVariant, 'attr') ?> <?= ! $hasUpper && ! $hasLower && $hasGrand ? 'tt-bracket-board--grand-only' : '' ?>" data-bracket-board data-bracket-zoom="1">
        <span class="sr-only" data-bracket-selection-status aria-live="polite"></span>
        <svg class="tt-bracket-connectors" data-bracket-connectors aria-hidden="true"></svg>

        <?php foreach (['upper', 'lower', 'grand'] as $side): ?>
            <?php if (empty($grouped[$side])) continue; ?>
            <?php
            $laneLabel = match ($side) {
                'upper' => 'Winner Bracket',
                'lower' => 'Loser Bracket',
                'grand' => $bracketFormat === 'double_elimination' ? 'Championship / Grand Final' : 'Championship',
                default => 'Bracket',
            };
            ?>
            <section class="tt-bracket-lane tt-bracket-lane--<?= esc($side, 'attr') ?>" data-bracket-lane="<?= esc($side, 'attr') ?>">
                <div class="tt-bracket-lane-heading">
                    <span class="tt-bracket-lane-icon" aria-hidden="true"><?= ui_icon($laneIcon($side)) ?></span>
                    <span><?= esc($laneLabel) ?></span>
                    <small>Select a match to trace its path</small>
                </div>
                <div class="tt-bracket-rounds">
                    <?php foreach ($grouped[$side] as $round => $matches): ?>
                        <section class="tt-bracket-round" data-bracket-round="<?= esc($round, 'attr') ?>">
                            <h4><?= esc($round) ?></h4>
                            <div class="tt-bracket-match-stack">
                                <?php foreach ($matches as $match): ?>
                                    <?php
                                    $winner = $winnerName($match);
                                    $matchCode = strtoupper(trim((string) ($match['match_code'] ?? '')));
                                    $feedA = strtoupper(trim((string) ($match['feeds_from_a'] ?? '')));
                                    $feedB = strtoupper(trim((string) ($match['feeds_from_b'] ?? '')));
                                    $feedAType = strtolower(trim((string) ($match['feeds_from_a_type'] ?? '')));
                                    $feedBType = strtolower(trim((string) ($match['feeds_from_b_type'] ?? '')));
                                    $teamAId = ! empty($match['team_a_id']) ? (int) $match['team_a_id'] : null;
                                    $teamBId = ! empty($match['team_b_id']) ? (int) $match['team_b_id'] : null;
                                    $teamALabel = (string) ($match['slot_a_label'] ?? 'TBD');
                                    $teamBLabel = (string) ($match['slot_b_label'] ?? 'TBD');
                                    $scoreA = $scoreFor($match, $teamAId);
                                    $scoreB = $scoreFor($match, $teamBId);
                                    $isConditional = ! empty($match['is_conditional']);
                                    $status = $statusLabel($match);
                                    $matchAria = trim(($matchCode !== '' ? $matchCode . ', ' : '') . $round . ': ' . $teamALabel . ' versus ' . $teamBLabel . '. ' . $status);
                                    ?>
                                    <article
                                        class="tournament-match tt-bracket-match <?= $isConditional ? 'conditional' : '' ?> <?= $side === 'grand' ? 'tt-bracket-match--championship' : '' ?>"
                                        data-bracket-match
                                        data-match-code="<?= esc($matchCode, 'attr') ?>"
                                        data-bracket-side="<?= esc($side, 'attr') ?>"
                                        data-match-round="<?= esc($round, 'attr') ?>"
                                        tabindex="0"
                                        role="button"
                                        aria-pressed="false"
                                        aria-label="<?= esc($matchAria, 'attr') ?>"
                                        <?= $feedA !== '' ? 'data-feed-a="' . esc($feedA, 'attr') . '"' : '' ?>
                                        <?= $feedB !== '' ? 'data-feed-b="' . esc($feedB, 'attr') . '"' : '' ?>
                                        <?= $feedAType !== '' ? 'data-feed-a-type="' . esc($feedAType, 'attr') . '"' : '' ?>
                                        <?= $feedBType !== '' ? 'data-feed-b-type="' . esc($feedBType, 'attr') . '"' : '' ?>
                                    >
                                        <div class="tt-bracket-match-head">
                                            <div class="tt-bracket-match-title">
                                                <span class="tt-bracket-match-icon" aria-hidden="true"><?= ui_icon($matchIcon($match, $side)) ?></span>
                                                <b><?= esc($matchCode !== '' ? $matchCode : '—') ?></b>
                                            </div>
                                            <span class="tt-bracket-status"><?= esc($status) ?></span>
                                        </div>

                                        <?php if (($match['result_type'] ?? '') === 'judged'): ?>
                                            <div class="tt-bracket-judged">All participating teams</div>
                                        <?php else: ?>
                                            <div class="bracket-team tt-bracket-slot <?= $winner !== '' && $winner === $teamALabel ? 'winner' : '' ?>">
                                                <span class="tt-bracket-team-main">
                                                    <span class="tt-bracket-team-mark" style="<?= esc($teamMarkStyle($teamALabel), 'attr') ?>" aria-hidden="true"><?= esc($teamInitials($teamALabel)) ?></span>
                                                    <span class="tt-bracket-team-name"><span><?= esc($teamALabel) ?></span></span>
                                                </span>
                                                <b class="tt-bracket-score"><?= $scoreA !== '' ? esc($scoreA) : '<span aria-hidden="true">—</span>' ?></b>
                                            </div>
                                            <div class="bracket-team tt-bracket-slot <?= $winner !== '' && $winner === $teamBLabel ? 'winner' : '' ?>">
                                                <span class="tt-bracket-team-main">
                                                    <span class="tt-bracket-team-mark" style="<?= esc($teamMarkStyle($teamBLabel), 'attr') ?>" aria-hidden="true"><?= esc($teamInitials($teamBLabel)) ?></span>
                                                    <span class="tt-bracket-team-name"><span><?= esc($teamBLabel) ?></span></span>
                                                </span>
                                                <b class="tt-bracket-score"><?= $scoreB !== '' ? esc($scoreB) : '<span aria-hidden="true">—</span>' ?></b>
                                            </div>
                                        <?php endif; ?>

                                        <div class="tt-bracket-match-foot">
                                            <small><?= ! empty($match['match_date']) ? esc(date('M j · g:i A', strtotime((string) $match['match_date']))) : 'TBD' ?></small>
                                            <span aria-hidden="true">·</span>
                                            <small><?= esc((string) ($match['court_label'] ?: ($match['location_name'] ?? '—'))) ?></small>
                                        </div>

                                        <?php if ($isConditional): ?>
                                            <div class="tt-bracket-note tt-bracket-note--conditional"><?= ui_icon('alert-triangle') ?><span>If necessary</span></div>
                                        <?php elseif ($winner !== ''): ?>
                                            <div class="tt-bracket-note"><?= ui_icon('chevron-right') ?><span><?= esc($winner) ?> advances</span></div>
                                        <?php elseif (! empty($match['scheduling_note'])): ?>
                                            <div class="tt-bracket-note"><?= ui_icon('calendar-clock') ?><span><?= esc($match['scheduling_note']) ?></span></div>
                                        <?php endif; ?>

                                        <?php if ($side === 'grand' && $winner !== '' && ($match['result_status'] ?? '') === 'validated'): ?>
                                            <div class="tt-bracket-champion">
                                                <span aria-hidden="true"><?= ui_icon('trophy') ?></span>
                                                <div><small>Champion</small><strong><?= esc($winner) ?></strong></div>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
