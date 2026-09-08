<?php
$hasActiveEvent = ! empty($activeEvent);
$heroImage = base_url('assets/img/logo.png');
$heroSlides = [
    ['image' => $heroImage, 'label' => 'TallyTech live event scoreboard'],
    ['image' => $heroImage, 'label' => 'TallyTech tournament standings'],
    ['image' => $heroImage, 'label' => 'TallyTech sports festival results'],
];
$bracketsByCategory = [];
foreach ($schedules as $schedule) {
    $category = (string) ($schedule['category'] ?? 'Open');
    if (! isset($bracketsByCategory[$category])) {
        $format = (string) ($schedule['tournament_format'] ?? 'single_elimination');
        $bracketsByCategory[$category] = [
            'format' => $format,
            'format_label' => $format === 'double_elimination' ? 'Double Elimination' : 'Single Elimination',
            'grouped' => ['upper' => [], 'lower' => [], 'grand' => []],
        ];
    }
    $side = (string) ($schedule['bracket_side'] ?? 'upper');
    if (! isset($bracketsByCategory[$category]['grouped'][$side])) {
        $side = 'upper';
    }
    $bracketsByCategory[$category]['grouped'][$side][$schedule['round']][] = $schedule;
}
$statusLabel = static function (array $match): string {
    if (($match['result_status'] ?? '') === 'validated') return 'FINAL';
    if (($match['status'] ?? '') === 'played') return 'AWAITING VALIDATION';
    if (!empty($match['is_conditional']) && ($match['status'] ?? '') === 'cancelled') return 'IF NECESSARY';
    return strtoupper((string) ($match['status'] ?? 'scheduled'));
};
$scoreFor = static function (array $match, ?int $teamId): string {
    if (!$teamId || !array_key_exists($teamId, $match['score_by_team'] ?? [])) return '';
    $score = (float) $match['score_by_team'][$teamId];
    return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
};
$sportGroups = $sportScoreTable['sportGroups'] ?? [];
$selectedSportIds = array_map('intval', $sportScoreTable['selectedSportIds'] ?? []);
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
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="viewer-page">
<a class="skip-link" href="#scoreboard-content">Skip to scoreboard content</a>
<header class="viewer-nav">
    <a class="viewer-brand" href="<?= site_url('scoreboard') ?>"><img src="<?= base_url('assets/img/logo.png') ?>" alt="TallyTech"><b>TallyTech</b></a>
    <div><span><?= $hasActiveEvent ? 'LIVE' : 'IDLE' ?></span><a href="<?= site_url('login') ?>" class="btn viewer-login"><?= ui_icon('log-in') ?><span>Login</span></a></div>
</header>

<section class="score-hero" data-hero-carousel data-interval="6000" aria-roledescription="carousel" aria-label="TallyTech event highlights" tabindex="0">
    <div class="hero-carousel" aria-live="off">
        <div class="hero-track">
            <?php foreach ($heroSlides as $i => $slide): ?>
                <div class="hero-slide <?= $i === 0 ? 'is-active' : '' ?>" data-carousel-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                    <img src="<?= esc($slide['image']) ?>" alt="" aria-hidden="true">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="hero-content">
        <div class="live-pill"><?= $hasActiveEvent ? '● LIVE · ' . esc($activeEvent['name']) : 'NO ACTIVE EVENT' ?></div>
        <h1>Live Scoreboard</h1>
        <p><?= $selectedSport ? esc($selectedSport['name']) : 'Select a sport to view its live tournament.' ?></p>
        <small><?= $hasActiveEvent ? 'Validated results automatically update progression, standings, and overall sport points.' : 'Standings and brackets will appear when an event is activated.' ?></small>
    </div>
    <button class="carousel-control prev" type="button" data-carousel-prev aria-label="Show previous hero slide"><?= ui_icon('chevron-left') ?></button>
    <button class="carousel-control next" type="button" data-carousel-next aria-label="Show next hero slide"><?= ui_icon('chevron-right') ?></button>
    <div class="carousel-dots" aria-label="Choose hero slide">
        <?php foreach ($heroSlides as $i => $slide): ?><button class="carousel-dot <?= $i === 0 ? 'is-active' : '' ?>" type="button" data-carousel-dot aria-label="Show slide <?= $i + 1 ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?>
    </div>
</section>

<main class="viewer-content" id="scoreboard-content">
    <section class="viewer-panel sport-filter-card">
        <div class="section-title"><h2>Select Sport</h2><span>Choose a sport to view its live bracket</span></div>
        <nav class="sport-chip-row" aria-label="Choose sport">
            <?php foreach ($sportGroups as $sportGroup): ?>
                <?php $active = in_array((int) $sportGroup['id'], $selectedSportIds, true); ?>
                <a class="chip sport-chip <?= $active ? 'active' : '' ?>" href="<?= esc(site_url('scoreboard') . '?sport=' . (int) $sportGroup['id'], 'attr') ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= esc($sportGroup['name']) ?></a>
            <?php endforeach; ?>
            <?php if (empty($sportGroups)): ?><span class="muted">No sports are configured for the active event.</span><?php endif; ?>
        </nav>
    </section>

    <?php if ($selectedSport): ?>
    <section class="viewer-panel live-bracket-panel">
        <div class="section-title"><h2 class="title-with-icon"><?= ui_icon('trophy') ?><span><?= esc($selectedSport['name']) ?> Bracket</span></h2><span>Live tournament paths</span></div>
        <?php if ($schedules): ?>
            <?php foreach ($bracketsByCategory as $category => $bracketData): ?>
                <?php
                $formatClass = $bracketData['format'] === 'double_elimination' ? 'double-elimination' : 'single-elimination';
                $hasUpper = ! empty($bracketData['grouped']['upper']);
                $hasLower = ! empty($bracketData['grouped']['lower']);
                $hasGrand = ! empty($bracketData['grouped']['grand']);
                ?>
                <div class="bracket-category-block">
                    <div class="section-title bracket-category-title"><h3><?= esc($category) ?></h3><span><?= esc($bracketData['format_label']) ?></span></div>
                    <div class="live-bracket public-bracket-scroll" role="region" aria-label="<?= esc($category . ' ' . $bracketData['format_label'] . ' bracket', 'attr') ?>" tabindex="0">
                        <div class="public-bracket-canvas public-bracket-canvas--<?= esc($formatClass, 'attr') ?> <?= ! $hasUpper && ! $hasLower && $hasGrand ? 'public-bracket-canvas--grand-only' : '' ?>" data-public-bracket>
                            <svg class="public-bracket-connectors" data-bracket-connectors aria-hidden="true"></svg>
                            <?php foreach (['upper', 'lower', 'grand'] as $side): ?>
                                <?php if (empty($bracketData['grouped'][$side])) continue; ?>
                                <?php
                                $laneLabel = match ($side) {
                                    'upper' => 'Winner Bracket',
                                    'lower' => 'Loser Bracket',
                                    'grand' => $bracketData['format'] === 'double_elimination' ? 'Championship / Grand Final' : 'Championship',
                                    default => 'Bracket',
                                };
                                ?>
                                <section class="bracket-lane viewer-bracket-lane public-bracket-lane public-bracket-lane--<?= esc($side, 'attr') ?>">
                                    <h3><?= esc($laneLabel) ?></h3>
                                    <div class="bracket-rounds">
                                        <?php foreach ($bracketData['grouped'][$side] as $round => $matches): ?>
                                            <div class="bracket-column public-bracket-round">
                                                <b class="bracket-round-title"><?= esc($round) ?></b>
                                                <div class="public-bracket-match-stack">
                                                    <?php foreach ($matches as $match): ?>
                                                        <?php
                                                        $winner = (string) ($match['winner_name'] ?? '');
                                                        $matchCode = strtoupper((string) ($match['match_code'] ?? ''));
                                                        $feedA = strtoupper((string) ($match['feeds_from_a'] ?? ''));
                                                        $feedB = strtoupper((string) ($match['feeds_from_b'] ?? ''));
                                                        ?>
                                                        <article class="tournament-match public-tournament-match <?= !empty($match['is_conditional']) ? 'conditional' : '' ?>"
                                                            data-match-code="<?= esc($matchCode, 'attr') ?>"
                                                            <?= $feedA !== '' ? 'data-feed-a="' . esc($feedA, 'attr') . '"' : '' ?>
                                                            <?= $feedB !== '' ? 'data-feed-b="' . esc($feedB, 'attr') . '"' : '' ?>>
                                                            <div class="match-meta"><span><?= esc($matchCode !== '' ? $matchCode : '—') ?></span><span><?= esc($statusLabel($match)) ?></span></div>
                                                            <?php if (($match['result_type'] ?? '') === 'judged'): ?>
                                                                <strong>Judged Championship</strong>
                                                                <small>All participating teams</small>
                                                            <?php else: ?>
                                                                <div class="bracket-team <?= $winner !== '' && $winner === ($match['slot_a_label'] ?? '') ? 'winner' : '' ?>"><span><?= esc($match['slot_a_label'] ?? 'TBD') ?></span><b><?= esc($scoreFor($match, !empty($match['team_a_id']) ? (int) $match['team_a_id'] : null)) ?></b></div>
                                                                <div class="bracket-team <?= $winner !== '' && $winner === ($match['slot_b_label'] ?? '') ? 'winner' : '' ?>"><span><?= esc($match['slot_b_label'] ?? 'TBD') ?></span><b><?= esc($scoreFor($match, !empty($match['team_b_id']) ? (int) $match['team_b_id'] : null)) ?></b></div>
                                                            <?php endif; ?>
                                                            <small><?= esc(date('M j · g:i A', strtotime($match['match_date']))) ?> · <?= esc($match['court_label'] ?: ($match['location_name'] ?? '—')) ?></small>
                                                            <?php if ($winner !== ''): ?><em><?= esc($winner) ?> advances</em><?php elseif (!empty($match['scheduling_note'])): ?><em><?= esc($match['scheduling_note']) ?></em><?php endif; ?>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?><div class="empty">No bracket is available for this sport yet.</div><?php endif; ?>
    </section>

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
</main>
<footer class="viewer-footer">© 2026 TallyTech · Intramural Sports Festival Management System</footer>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
