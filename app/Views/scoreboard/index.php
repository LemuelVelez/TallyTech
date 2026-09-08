<?php
$hasActiveEvent = ! empty($activeEvent);
$sportGroups = $sportScoreTable['sportGroups'] ?? [];
$selectedSportIds = array_map('intval', $sportScoreTable['selectedSportIds'] ?? []);

$bracketsByCategory = [];
foreach ($schedules as $schedule) {
    $category = trim((string) ($schedule['category'] ?? 'Open')) ?: 'Open';
    if (! isset($bracketsByCategory[$category])) {
        $format = (string) ($schedule['tournament_format'] ?? 'single_elimination');
        $bracketsByCategory[$category] = [
            'format' => $format === 'double_elimination' ? 'double_elimination' : 'single_elimination',
            'format_label' => $format === 'double_elimination' ? 'Double Elimination' : 'Single Elimination',
            'schedules' => [],
        ];
    }
    $bracketsByCategory[$category]['schedules'][] = $schedule;
}

$categoryOrder = static fn(string $category): int => match (strtolower($category)) {
    'men' => 0,
    'women' => 1,
    'mixed' => 2,
    default => 3,
};
uksort($bracketsByCategory, static function (string $a, string $b) use ($categoryOrder): int {
    $order = $categoryOrder($a) <=> $categoryOrder($b);
    return $order ?: strcasecmp($a, $b);
});

$assetVersion = static function (string $relativePath): string {
    $path = defined('FCPATH') ? FCPATH . ltrim($relativePath, '/\\') : '';
    return $path !== '' && is_file($path) ? (string) filemtime($path) : '20260908-2';
};
$cssVersion = $assetVersion('assets/css/app.css');
$jsVersion = $assetVersion('assets/js/app.js');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta http-equiv="refresh" content="30">
    <meta name="theme-color" content="#061b3a">
    <title>Live Scoreboard · TallyTech</title>
    <link rel="icon" type="image/png" href="<?= base_url('logo.png') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css') . '?v=' . rawurlencode($cssVersion), 'attr') ?>">
</head>
<body class="viewer-page">
<a class="skip-link" href="#scoreboard-content">Skip to scoreboard content</a>
<header class="viewer-nav">
    <a class="viewer-brand" href="<?= site_url('scoreboard') ?>"><img src="<?= base_url('assets/img/logo.png') ?>" alt="TallyTech"><b>TallyTech</b></a>
    <div><span><?= $hasActiveEvent ? 'LIVE' : 'IDLE' ?></span><a href="<?= site_url('login') ?>" class="btn viewer-login"><?= ui_icon('log-in') ?><span>Login</span></a></div>
</header>

<main id="scoreboard-content">
    <section class="score-hero score-hero--bracket" aria-labelledby="live-scoreboard-title">
        <div class="score-hero-inner">
            <div class="score-hero-copy">
                <div class="live-pill"><?= $hasActiveEvent ? '● LIVE · ' . esc($activeEvent['name']) : 'NO ACTIVE EVENT' ?></div>
                <h1 id="live-scoreboard-title">Live Scoreboard</h1>
                <p><?= $selectedSport ? esc($selectedSport['name']) : 'Select a sport to view its live tournament bracket.' ?></p>
                <small><?= $hasActiveEvent ? 'Validated results automatically update bracket progression, standings, and overall sport points.' : 'Brackets will appear when an event is activated.' ?></small>
            </div>

            <div class="score-hero-rotation" data-scoreboard-rotation-ui hidden>
                <div class="score-hero-rotation-countdown" data-scoreboard-rotation-status>
                    <span data-scoreboard-rotation-label>Next sport in</span>
                    <strong data-scoreboard-rotation-countdown>15</strong><span data-scoreboard-rotation-unit aria-hidden="true">s</span>
                </div>
                <label class="scoreboard-rotation-switch">
                    <input type="checkbox" role="switch" data-scoreboard-auto-rotate-toggle aria-label="Automatically rotate scoreboard sports" checked>
                    <span class="scoreboard-rotation-switch-track" aria-hidden="true"><span></span></span>
                    <span class="scoreboard-rotation-switch-label">Auto rotate</span>
                </label>
            </div>

            <nav class="score-hero-sports sport-chip-row" aria-label="Choose sport" data-scoreboard-sport-nav data-auto-rotate-ms="15000">
                <?php foreach ($sportGroups as $sportGroup): ?>
                    <?php $active = in_array((int) $sportGroup['id'], $selectedSportIds, true); ?>
                    <a class="chip sport-chip <?= $active ? 'active' : '' ?>" data-scoreboard-sport-link href="<?= esc(site_url('scoreboard') . '?sport=' . (int) $sportGroup['id'], 'attr') ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= esc($sportGroup['name']) ?></a>
                <?php endforeach; ?>
                <?php if (empty($sportGroups)): ?><span class="score-hero-empty">No sports are configured for the active event.</span><?php endif; ?>
            </nav>

            <?php if ($selectedSport && $bracketsByCategory): ?>
                <div class="score-hero-brackets">
                    <?php foreach ($bracketsByCategory as $category => $bracketData): ?>
                        <section class="score-hero-bracket-category">
                            <div class="score-hero-bracket-heading">
                                <div><b><?= esc($category) ?></b><span><?= esc($selectedSport['name']) ?> Bracket</span></div>
                                <span><?= esc($bracketData['format_label']) ?></span>
                            </div>
                            <?= view('partials/bracket_tree', [
                                'bracketSchedules' => $bracketData['schedules'],
                                'bracketFormat' => $bracketData['format'],
                                'bracketVariant' => 'public',
                                'bracketAriaLabel' => $selectedSport['name'] . ' ' . $category . ' ' . $bracketData['format_label'] . ' bracket',
                            ]) ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($selectedSport): ?>
                <div class="score-hero-empty score-hero-empty--panel">No bracket is available for <?= esc($selectedSport['name']) ?> yet.</div>
            <?php else: ?>
                <div class="score-hero-empty score-hero-empty--panel">Select an available sport to load its bracket.</div>
            <?php endif; ?>
        </div>
    </section>

    <div class="viewer-content scoreboard-below-bracket">
        <?php if ($selectedSport): ?>
            <div class="viewer-columns scoreboard-detail-columns">
                <section class="viewer-panel">
                    <div class="section-title"><h2>Recent Results</h2><span><?= esc($selectedSport['name']) ?> only</span></div>
                    <div class="scroll-box">
                        <?php foreach ($results as $result): ?>
                            <article class="public-result">
                                <div><b><?= esc($result['category'].' · '.$result['round']) ?></b><span class="badge <?= $result['status'] === 'validated' ? 'official' : 'unofficial' ?>"><?= $result['status'] === 'validated' ? 'OFFICIAL' : 'UNOFFICIAL' ?></span></div>
                                <?php foreach ($result['entries'] as $entry): ?><p><span><?= esc($entry['team_name']) ?></span><strong><?= esc(rtrim(rtrim(number_format((float) $entry['raw_score'], 2, '.', ''), '0'), '.')) ?></strong></p><?php endforeach; ?>
                                <small><?= esc(date('M j, g:i A', strtotime($result['submitted_at']))) ?></small>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($results)): ?><div class="empty">No results have been submitted for this sport.</div><?php endif; ?>
                    </div>
                </section>

                <section class="viewer-panel">
                    <div class="section-title"><h2>Sport Standings</h2><span>Validated results only</span></div>
                    <div class="viewer-podium compact-podium">
                        <?php foreach (array_slice($ranking ?? [], 0, 4) as $i => $team): ?>
                            <article class="viewer-rank r<?= $i + 1 ?>"><span><?= ui_icon(['trophy', 'medal', 'award', 'target'][$i]) ?></span><b><?= $i + 1 ?></b><h3><?= esc($team['name']) ?></h3><strong><?= esc(format_points($team['total_points'])) ?></strong><small>points</small></article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($ranking)): ?><div class="empty">No official standings are available for this sport.</div><?php endif; ?>
                </section>
            </div>

            <section class="viewer-panel sport-points-panel">
                <div class="section-title"><h2>Overall Sport Points</h2><span><?= esc($selectedSport['name']) ?> · validated results</span></div>
                <div class="table-wrap"><table><thead><tr><th>Rank</th><th>Team</th><th>Points</th></tr></thead><tbody>
                    <?php foreach ($ranking as $i => $team): ?><tr><td><b><?= $i + 1 ?></b></td><td><?= esc($team['name']) ?></td><td><b><?= esc(format_points($team['total_points'])) ?></b></td></tr><?php endforeach; ?>
                    <?php if (empty($ranking)): ?><tr><td colspan="3" class="empty">No validated sport points yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        <?php else: ?>
            <section class="viewer-panel"><div class="empty">No sports are configured for the active event.</div></section>
        <?php endif; ?>
    </div>
</main>
<footer class="viewer-footer">© 2026 TallyTech · Intramural Sports Festival Management System</footer>
<script src="<?= esc(base_url('assets/js/app.js') . '?v=' . rawurlencode($jsVersion), 'attr') ?>"></script>
</body>
</html>
