<?php
$hasActiveEvent = ! empty($activeEvent);
$heroImage = base_url('assets/img/logo.png');
$heroSlides = [
    ['image' => $heroImage, 'label' => 'TallyTech live event scoreboard'],
    ['image' => $heroImage, 'label' => 'TallyTech tournament standings'],
    ['image' => $heroImage, 'label' => 'TallyTech sports festival results'],
];
$selectedSportId = (int) ($selectedSport['id'] ?? 0);
$format = (string) ($schedules[0]['tournament_format'] ?? 'single_elimination');
$formatLabel = $format === 'double_elimination' ? 'Double Elimination' : 'Single Elimination';
$grouped = ['upper' => [], 'lower' => [], 'grand' => []];
foreach ($schedules as $schedule) {
    $side = (string) ($schedule['bracket_side'] ?? 'upper');
    if (! isset($grouped[$side])) $side = 'upper';
    $grouped[$side][$schedule['round']][] = $schedule;
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
        <p><?= $selectedSport ? esc($selectedSport['name'].' · '.$selectedSport['category']) : 'Select a sport to view its live tournament.' ?></p>
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
        <div class="section-title"><h2>Select Sport</h2><span>Only the selected sport is shown</span></div>
        <form method="get" action="<?= site_url('scoreboard') ?>" class="sport-filter-form">
            <label for="scoreboard-sport">Sport</label>
            <select id="scoreboard-sport" name="sport" onchange="this.form.submit()">
                <?php foreach ($sports as $sport): ?><option value="<?= (int) $sport['id'] ?>" <?= (int) $sport['id'] === $selectedSportId ? 'selected' : '' ?>><?= esc($sport['name'].' · '.$sport['category']) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn primary">View Sport</button></noscript>
        </form>
    </section>

    <?php if ($selectedSport): ?>
    <section class="viewer-panel live-bracket-panel">
        <div class="section-title"><h2 class="title-with-icon"><?= ui_icon('trophy') ?><span><?= esc($selectedSport['name']) ?> Bracket</span></h2><span><?= esc($formatLabel) ?></span></div>
        <?php if ($schedules): ?>
            <div class="live-bracket">
                <?php foreach (['upper' => $format === 'double_elimination' ? 'Winner Bracket' : 'Championship Path', 'lower' => 'Loser Bracket', 'grand' => 'Championship'] as $side => $laneLabel): ?>
                    <?php if (!empty($grouped[$side])): ?>
                        <div class="bracket-lane viewer-bracket-lane">
                            <h3><?= esc($laneLabel) ?></h3>
                            <div class="bracket-rounds">
                                <?php foreach ($grouped[$side] as $round => $matches): ?>
                                    <div class="bracket-column">
                                        <b class="bracket-round-title"><?= esc($round) ?></b>
                                        <?php foreach ($matches as $match): ?>
                                            <?php $winner = (string) ($match['winner_name'] ?? ''); ?>
                                            <article class="tournament-match public-tournament-match <?= !empty($match['is_conditional']) ? 'conditional' : '' ?>">
                                                <div class="match-meta"><span><?= esc($match['match_code'] ?? '—') ?></span><span><?= esc($statusLabel($match)) ?></span></div>
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
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="empty">No bracket is available for this sport yet.</div><?php endif; ?>
    </section>

    <div class="viewer-columns scoreboard-detail-columns">
        <section class="viewer-panel">
            <div class="section-title"><h2>Recent Results</h2><span><?= esc($selectedSport['name']) ?> only</span></div>
            <div class="scroll-box">
                <?php foreach ($results as $result): ?>
                    <article class="public-result">
                        <div><b><?= esc($result['round']) ?></b><span class="badge <?= $result['status'] === 'validated' ? 'official' : 'unofficial' ?>"><?= $result['status'] === 'validated' ? 'OFFICIAL' : 'UNOFFICIAL' ?></span></div>
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
