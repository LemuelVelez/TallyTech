<?php
$panelClass = $sportScorePanelClass ?? 'panel';
$action = $sportScoreAction ?? 'sport-scores';
$table = $sportScoreTable ?? [];
$selectedSport = $table['selectedSport'] ?? null;
$selectedSportIds = array_map('intval', $table['selectedSportIds'] ?? []);
$maxSetCount = max(1, (int) ($table['maxSetCount'] ?? 1));
$ordinal = static function (int $number): string {
    if ($number % 100 >= 11 && $number % 100 <= 13) {
        return $number . 'th';
    }
    return $number . match ($number % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
};
$scoreText = static fn(float|int $score): string => rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
?>
<section class="<?= esc($panelClass) ?> sport-filter-card">
    <div class="section-title"><h2>Select Sport</h2><span>Only the selected sport is shown</span></div>
    <nav class="sport-chip-row" aria-label="Choose sport">
        <?php foreach ($table['sportGroups'] ?? [] as $sportGroup): ?>
            <?php $active = in_array((int) $sportGroup['id'], $selectedSportIds, true); ?>
            <a class="chip sport-chip <?= $active ? 'active' : '' ?>" href="<?= esc(site_url($action) . '?sport=' . (int) $sportGroup['id'], 'attr') ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= esc($sportGroup['name']) ?></a>
        <?php endforeach; ?>
        <?php if (empty($table['sportGroups'])): ?><span class="muted">No sports are configured for the active event.</span><?php endif; ?>
    </nav>
</section>

<?php if ($selectedSport): ?>
<section class="<?= esc($panelClass) ?> sport-score-table-panel">
    <div class="section-title"><h2><?= esc($selectedSport['name']) ?> Score Table</h2><span>Win/Loss is based on sets won</span></div>
    <div class="score-thresholds">
        <?php foreach ($table['thresholds'] ?? [] as $threshold): ?>
            <span class="score-threshold"><b><?= esc($threshold['category']) ?>:</b>
                <?php if (($threshold['result_type'] ?? '') !== 'match'): ?>Judged scoring; no set winning-point threshold
                <?php elseif (($threshold['winning_points'] ?? null) === null): ?>No fixed winning-point threshold configured
                <?php else: ?><?= esc($scoreText($threshold['winning_points'])) ?> points wins a set in this category<?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table class="sport-score-table">
            <thead><tr><th>Category</th><th>Team</th><?php for ($set = 1; $set <= $maxSetCount; $set++): ?><th><?= esc($ordinal($set)) ?></th><?php endfor; ?><th>Status</th><th>Overall Points</th></tr></thead>
            <tbody>
            <?php foreach ($table['categories'] ?? [] as $category): ?>
                <?php $rows = $category['rows'] ?? []; ?>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <?php if ($index === 0): ?><td rowspan="<?= count($rows) ?>" class="sport-category-cell"><b><?= esc($category['category']) ?></b></td><?php endif; ?>
                            <td><b><?= esc($row['team_name']) ?></b><?php if (!empty($row['match_code'])): ?><small class="muted"><?= esc($row['match_code']) ?></small><?php endif; ?></td>
                            <?php for ($set = 0; $set < $maxSetCount; $set++): ?><td><?= array_key_exists($set, $row['set_scores'] ?? []) ? esc($scoreText($row['set_scores'][$set])) : '—' ?></td><?php endfor; ?>
                            <td><?php if (($row['status'] ?? '') !== ''): ?><span class="badge <?= $row['status'] === 'Win' ? 'official' : 'neutral' ?>"><?= esc($row['status']) ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><b><?= !empty($row['set_scores']) ? esc($scoreText($row['overall_points'])) : '—' ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td class="sport-category-cell"><b><?= esc($category['category']) ?></b></td><td colspan="<?= $maxSetCount + 3 ?>" class="empty">No participating teams or submitted scores yet.</td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($table['categories'])): ?><tr><td colspan="<?= $maxSetCount + 4 ?>" class="empty">No score table is available for this sport.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
