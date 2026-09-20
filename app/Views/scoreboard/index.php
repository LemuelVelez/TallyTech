<?php
$hasActiveEvent = ! empty($activeEvent);
$sportGroups = $sportScoreTable['sportGroups'] ?? [];
$selectedSportIds = array_map('intval', $sportScoreTable['selectedSportIds'] ?? []);
$isOverallView = ! empty($isOverall);
$presentationFrame = (string) service('request')->getGet('presentation') === '1';
$requestedScoreboard = strtolower(trim((string) service('request')->getGet('scoreboard')));
$scoreboardMode = in_array($requestedScoreboard, ['official', 'unofficial'], true) ? $requestedScoreboard : 'official';
$scoreboards = is_array($scoreboards ?? null) ? $scoreboards : [];
$activeScoreboard = $scoreboards[$scoreboardMode] ?? ($scoreboardMode === 'unofficial' ? ($unofficialScoreboard ?? []) : ($officialScoreboard ?? []));
$results = is_array($activeScoreboard['results'] ?? null) ? $activeScoreboard['results'] : [];
$ranking = is_array($activeScoreboard['standings'] ?? null) ? $activeScoreboard['standings'] : [];
$overallRanking = is_array($activeScoreboard['overallRanking'] ?? null) ? $activeScoreboard['overallRanking'] : [];
$overallSportPoints = is_array($activeScoreboard['overallSportPoints'] ?? null) ? $activeScoreboard['overallSportPoints'] : [];
$schedules = is_array($activeScoreboard['schedules'] ?? null) ? $activeScoreboard['schedules'] : [];
$isOfficialScoreboard = $scoreboardMode === 'official';
$selectedSportQueryId = (int) ($selectedSportIds[0] ?? 0);

$scoreboardHref = static function (string $mode, ?int $sportId, bool $presentation, bool $overall = false): string {
    $query = ['scoreboard' => $mode];
    if ($overall) {
        $query['view'] = 'overall';
    } elseif (($sportId ?? 0) > 0) {
        $query['sport'] = $sportId;
    }
    if ($presentation) {
        $query['presentation'] = 1;
    }
    return site_url('scoreboard') . '?' . http_build_query($query);
};

$bracketsByCategory = [];
if (! $isOverallView) {
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
    return $path !== '' && is_file($path) ? (string) filemtime($path) : '20260920-1';
};
$cssVersion = $assetVersion('assets/css/app.css');
$jsVersion = $assetVersion('assets/js/app.js');
?>
<!doctype html>
<html lang="en"<?= $presentationFrame ? ' class="scoreboard-presentation-html"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="tallytech-scoreboard-refresh" content="30" data-scoreboard-refresh>
    <meta name="theme-color" content="#061b3a">
    <title><?= esc($isOfficialScoreboard ? 'Official Scoreboard' : 'Unofficial Scoreboard') ?> · TallyTech</title>
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico') ?>?v=2">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css') . '?v=' . rawurlencode($cssVersion), 'attr') ?>">
</head>
<body class="viewer-page<?= $presentationFrame ? ' viewer-page--presentation-frame' : '' ?><?= $isOfficialScoreboard ? ' scoreboard-view--official' : ' scoreboard-view--unofficial' ?>"<?= $presentationFrame ? ' data-scoreboard-presentation-frame="true"' : '' ?>>
<?php if (! $presentationFrame): ?>
<a class="skip-link" href="#scoreboard-content">Skip to scoreboard content</a>
<header class="viewer-nav">
    <a class="viewer-brand" href="<?= site_url('scoreboard') ?>"><img src="<?= base_url('assets/img/logo.webp') ?>" alt="TallyTech"><b>TallyTech</b></a>
    <div><span><?= $hasActiveEvent ? 'LIVE' : 'IDLE' ?></span><a href="<?= site_url('login') ?>" class="btn viewer-login"><?= ui_icon('log-in') ?><span>Login</span></a></div>
</header>
<?php endif; ?>

<main id="scoreboard-content">
    <section class="score-hero score-hero--bracket<?= $isOverallView ? ' score-hero--overall' : '' ?>" aria-labelledby="live-scoreboard-title">
        <div class="score-hero-inner">
            <div class="score-hero-copy">
                <div class="live-pill"><?= $hasActiveEvent ? '● LIVE · ' . esc($activeEvent['name']) : 'NO ACTIVE EVENT' ?></div>
                <h1 id="live-scoreboard-title"><?= esc($activeScoreboard['label'] ?? ($isOfficialScoreboard ? 'Official Scoreboard' : 'Unofficial Scoreboard')) ?></h1>
                <p><?= $isOverallView ? 'Overall Team Ranking' : ($selectedSport ? esc($selectedSport['name']) : 'Select a sport to view its scoreboard.') ?></p>
                <small><?= $hasActiveEvent ? esc($isOverallView ? ($isOfficialScoreboard ? 'Validated results across all sports in the active event.' : 'Pending results across all sports in the active event. Provisional points are subject to validation.') : ($activeScoreboard['description'] ?? '')) : 'Scoreboards will appear when an event is activated.' ?></small>
            </div>

            <nav class="scoreboard-mode-tabs" aria-label="Choose scoreboard type">
                <a class="scoreboard-mode-tab<?= $isOfficialScoreboard ? ' active' : '' ?>" href="<?= esc($scoreboardHref('official', $selectedSportQueryId, $presentationFrame, $isOverallView), 'attr') ?>" <?= $isOfficialScoreboard ? 'aria-current="page"' : '' ?>>
                    <strong>Official Scoreboard</strong>
                    <span>Confirmed</span>
                </a>
                <a class="scoreboard-mode-tab scoreboard-mode-tab--unofficial<?= ! $isOfficialScoreboard ? ' active' : '' ?>" href="<?= esc($scoreboardHref('unofficial', $selectedSportQueryId, $presentationFrame, $isOverallView), 'attr') ?>" <?= ! $isOfficialScoreboard ? 'aria-current="page"' : '' ?>>
                    <strong>Unofficial Scoreboard</strong>
                    <span>Provisional</span>
                </a>
            </nav>

            <?php if (! $isOfficialScoreboard): ?>
                <div class="scoreboard-provisional-notice" role="status">
                    <?= ui_icon('alert-triangle') ?>
                    <span>Provisional scores are awaiting validation and are subject to change.</span>
                </div>
            <?php endif; ?>

            <?php if (! $presentationFrame): ?>
                <div class="scoreboard-presentation-launch">
                    <button type="button" class="scoreboard-present-button" data-scoreboard-present aria-label="Present the live scoreboard in fullscreen">
                        <?= ui_icon('play-circle') ?>
                        <span>Present</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="score-hero-rotation" data-scoreboard-rotation-ui hidden>
                <div class="score-hero-rotation-countdown" data-scoreboard-rotation-status>
                    <span data-scoreboard-rotation-label>Next view in</span>
                    <strong data-scoreboard-rotation-countdown>15</strong><span data-scoreboard-rotation-unit aria-hidden="true">s</span>
                </div>
                <label class="scoreboard-rotation-switch">
                    <input type="checkbox" role="switch" data-scoreboard-auto-rotate-toggle aria-label="Automatically rotate scoreboard views">
                    <span class="scoreboard-rotation-switch-track" aria-hidden="true"><span></span></span>
                    <span class="scoreboard-rotation-switch-label">Auto rotate</span>
                </label>
            </div>

            <nav class="score-hero-sports sport-chip-row" aria-label="Choose scoreboard view" data-scoreboard-sport-nav data-auto-rotate-ms="15000">
                <?php $overallHref = $scoreboardHref($scoreboardMode, null, $presentationFrame, true); ?>
                <a class="chip sport-chip <?= $isOverallView ? 'active' : '' ?>" data-scoreboard-sport-link href="<?= esc($overallHref, 'attr') ?>" <?= $isOverallView ? 'aria-current="page"' : '' ?>>Overall</a>
                <?php foreach ($sportGroups as $sportGroup): ?>
                    <?php $active = ! $isOverallView && in_array((int) $sportGroup['id'], $selectedSportIds, true); ?>
                    <?php $sportHref = $scoreboardHref($scoreboardMode, (int) $sportGroup['id'], $presentationFrame); ?>
                    <a class="chip sport-chip <?= $active ? 'active' : '' ?>" data-scoreboard-sport-link href="<?= esc($sportHref, 'attr') ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= esc($sportGroup['name']) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($isOverallView): ?>
                <div class="scoreboard-overall-ranking">
                    <?= view('partials/team_ranking', [
                        'ranking' => $overallRanking,
                        'rankingVariant' => 'scoreboard',
                        'rankingProvisional' => ! $isOfficialScoreboard,
                        'rankingSubtitle' => $isOfficialScoreboard ? 'Validated results across all sports' : 'Pending results across all sports · subject to validation',
                        'rankingEmptyMessage' => $isOfficialScoreboard ? 'No official team ranking is available yet.' : 'No provisional team ranking is available yet.',
                    ]) ?>
                </div>
            <?php elseif ($selectedSport && $bracketsByCategory): ?>
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
                                'bracketAriaLabel' => ($isOfficialScoreboard ? 'Official ' : 'Unofficial ') . $selectedSport['name'] . ' ' . $category . ' ' . $bracketData['format_label'] . ' bracket',
                            ]) ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($selectedSport): ?>
                <div class="score-hero-empty score-hero-empty--panel">No bracket is available for <?= esc($selectedSport['name']) ?> yet.</div>
            <?php else: ?>
                <div class="score-hero-empty score-hero-empty--panel">No sports are configured for the active event.</div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (! $presentationFrame && ! $isOverallView): ?>
    <div class="viewer-content scoreboard-below-bracket">
        <?php if ($selectedSport): ?>
            <div class="viewer-columns scoreboard-detail-columns">
                <section class="viewer-panel<?= $isOfficialScoreboard ? '' : ' viewer-panel--provisional' ?>">
                    <div class="section-title">
                        <h2><?= $isOfficialScoreboard ? 'Recent Official Results' : 'Recent Unofficial Results' ?></h2>
                        <span><?= esc($selectedSport['name']) ?> only</span>
                    </div>
                    <div class="scroll-box">
                        <?php foreach ($results as $result): ?>
                            <article class="public-result">
                                <div>
                                    <b><?= esc($result['category'] . ' · ' . $result['round']) ?></b>
                                    <span class="badge <?= $isOfficialScoreboard ? 'official' : 'unofficial' ?>"><?= $isOfficialScoreboard ? 'OFFICIAL' : 'PROVISIONAL' ?></span>
                                </div>
                                <?php foreach ($result['entries'] as $entry): ?><p><span><?= esc($entry['team_name']) ?></span><strong><?= esc(rtrim(rtrim(number_format((float) $entry['raw_score'], 2, '.', ''), '0'), '.')) ?></strong></p><?php endforeach; ?>
                                <small><?= esc(date('M j, g:i A', strtotime($result['submitted_at']))) ?></small>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($results)): ?><div class="empty">No <?= $isOfficialScoreboard ? 'validated' : 'pending' ?> results are available for this sport.</div><?php endif; ?>
                    </div>
                </section>

                <section class="viewer-panel<?= $isOfficialScoreboard ? '' : ' viewer-panel--provisional' ?>">
                    <div class="section-title">
                        <h2><?= $isOfficialScoreboard ? 'Official Sport Standings' : 'Unofficial Sport Standings' ?></h2>
                        <span><?= $isOfficialScoreboard ? 'Validated results only' : 'Pending results · provisional' ?></span>
                    </div>
                    <div class="viewer-podium compact-podium">
                        <?php foreach (array_slice($ranking, 0, 4) as $i => $team): ?>
                            <article class="viewer-rank r<?= (int) ($i + 1) ?>"><span><?= ui_icon(['trophy', 'medal', 'award', 'target'][$i]) ?></span><b><?= esc((string) ($i + 1)) ?></b><h3><?= esc($team['name']) ?></h3><strong><?= esc(format_points($team['total_points'])) ?></strong><small>points</small></article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($ranking)): ?><div class="empty">No <?= $isOfficialScoreboard ? 'official' : 'provisional' ?> standings are available for this sport.</div><?php endif; ?>
                </section>
            </div>

            <section class="viewer-panel sport-points-panel<?= $isOfficialScoreboard ? '' : ' viewer-panel--provisional' ?>">
                <div class="section-title">
                    <h2><?= $isOfficialScoreboard ? 'Official Overall Sport Points' : 'Unofficial Overall Sport Points' ?></h2>
                    <span><?= esc($selectedSport['name']) ?> · <?= $isOfficialScoreboard ? 'validated results' : 'pending results · subject to change' ?></span>
                </div>
                <div class="table-wrap"><table><thead><tr><th>Rank</th><th>Team</th><th>Points</th></tr></thead><tbody>
                    <?php foreach ($overallSportPoints as $i => $team): ?><tr><td><b><?= esc((string) ($i + 1)) ?></b></td><td><?= esc($team['name']) ?></td><td><b><?= esc(format_points($team['total_points'])) ?></b></td></tr><?php endforeach; ?>
                    <?php if (empty($overallSportPoints)): ?><tr><td colspan="3" class="empty">No <?= $isOfficialScoreboard ? 'validated' : 'provisional' ?> sport points yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        <?php else: ?>
            <section class="viewer-panel"><div class="empty">No sports are configured for the active event.</div></section>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php if (! $presentationFrame): ?>
<footer class="viewer-footer">© 2026 TallyTech · Intramural Sports Festival Management System</footer>
<div class="scoreboard-presentation-stage" data-scoreboard-presentation-stage hidden aria-label="Live scoreboard presentation">
    <iframe data-scoreboard-presentation-iframe title="Live scoreboard presentation" allow="fullscreen" allowfullscreen></iframe>
    <div class="scoreboard-presentation-stage-controls" aria-label="Presentation controls">
        <button type="button" class="scoreboard-presentation-control scoreboard-presentation-control--exit" data-scoreboard-exit-presentation>
            <?= ui_icon('x') ?>
            <span>Exit presentation</span>
        </button>
    </div>
</div>
<?php endif; ?>
<script src="<?= esc(base_url('assets/js/app.js') . '?v=' . rawurlencode($jsVersion), 'attr') ?>"></script>
</body>
</html>
