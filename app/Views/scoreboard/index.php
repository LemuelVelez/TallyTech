<?php
$hasActiveEvent = ! empty($activeEvent);
$heroImage = base_url('assets/img/logo.png');
$heroSlides = [
    ['image' => $heroImage, 'label' => 'TallyTech live event scoreboard'],
    ['image' => $heroImage, 'label' => 'TallyTech tournament standings'],
    ['image' => $heroImage, 'label' => 'TallyTech sports festival results'],
];
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
        <p>Scores, rankings, and results — refreshed automatically every 30 seconds.</p>
        <small><?= $hasActiveEvent ? 'Official standings use validated results only. Unofficial submissions remain visible and clearly marked.' : 'Standings and schedules will appear when an event is activated.' ?></small>
    </div>

    <button class="carousel-control prev" type="button" data-carousel-prev aria-label="Show previous hero slide"><?= ui_icon('chevron-left') ?></button>
    <button class="carousel-control next" type="button" data-carousel-next aria-label="Show next hero slide"><?= ui_icon('chevron-right') ?></button>
    <div class="carousel-dots" aria-label="Choose hero slide">
        <?php foreach ($heroSlides as $i => $slide): ?>
            <button class="carousel-dot <?= $i === 0 ? 'is-active' : '' ?>" type="button" data-carousel-dot aria-label="Show slide <?= $i + 1 ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button>
        <?php endforeach; ?>
    </div>
</section>

<main class="viewer-content" id="scoreboard-content">
    <section>
        <div class="section-title"><h2 class="title-with-icon"><?= ui_icon('trophy') ?><span>Team Standings</span></h2><span>Official ranking</span></div>
        <div class="viewer-podium">
            <?php foreach (array_slice($ranking ?? [], 0, 4) as $i => $t): ?>
                <article class="viewer-rank r<?= $i + 1 ?>"><span><?= ui_icon(['trophy', 'medal', 'award', 'target'][$i]) ?></span><b><?= $i + 1 ?></b><h3><?= esc($t['name']) ?></h3><strong><?= esc(format_points($t['total_points'])) ?></strong><small>points</small></article>
            <?php endforeach; ?>
        </div>
        <?php if (empty($ranking)): ?><div class="empty">No official standings are available for the active event.</div><?php endif; ?>
    </section>

    <section class="viewer-panel">
        <div class="section-title"><h2>Overall Team Ranking</h2><span>Validated results only</span></div>
        <div class="table-wrap"><table><thead><tr><th>Rank</th><th>Team</th><th>Points</th><th>1st</th><th>2nd</th><th>3rd</th></tr></thead><tbody>
        <?php foreach ($ranking as $i => $t): ?><tr><td><b><?= $i + 1 ?></b></td><td><?= esc($t['name']) ?></td><td><b><?= esc(format_points($t['total_points'])) ?></b></td><td><?= (int) $t['firsts'] ?></td><td><?= (int) $t['seconds'] ?></td><td><?= (int) $t['thirds'] ?></td></tr><?php endforeach; ?>
        <?php if (empty($ranking)): ?><tr><td colspan="6" class="empty">No teams are ranked yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <div class="viewer-columns">
        <section class="viewer-panel">
            <div class="section-title"><h2>Recent Results</h2></div>
            <div class="scroll-box">
                <?php foreach ($results as $r): ?><article class="public-result"><div><b><?= esc($r['sport_name'] . ' · ' . $r['category'] . ' · ' . strtoupper($r['result_type'])) ?></b><span class="badge <?= $r['status'] === 'validated' ? 'official' : 'unofficial' ?>"><?= $r['status'] === 'validated' ? 'OFFICIAL' : 'UNOFFICIAL' ?></span></div><?php foreach ($r['entries'] as $e): ?><p><span><?= esc($e['team_name']) ?></span><strong><?= number_format((float) $e['raw_score'], 2) ?></strong></p><?php endforeach; ?><small><?= esc(date('M j, g:i A', strtotime($r['submitted_at']))) ?></small></article><?php endforeach; ?>
                <?php if (empty($results)): ?><div class="empty">No results have been submitted for the active event.</div><?php endif; ?>
            </div>
        </section>
        <section class="viewer-panel">
            <div class="section-title"><h2>Upcoming Matches & Judged Events</h2><span>Scrollable bracket</span></div>
            <?php $publicBracket = []; foreach ($schedules as $s) { if ($s['status'] === 'scheduled') { $publicBracket[$s['round']][] = $s; } } ?>
            <div class="public-bracket">
                <?php foreach ($publicBracket as $round => $items): ?><div class="public-stage"><b><?= esc($round) ?></b><?php foreach ($items as $s): ?><article class="upcoming"><b><?= esc($s['sport_name'] . ' · ' . $s['category'] . ' · ' . strtoupper($s['result_type'])) ?></b><p><?= esc($s['team_a_name'] ?? 'All teams') ?><?= $s['team_b_name'] ? ' vs ' . esc($s['team_b_name']) : '' ?></p><small><?= esc(($s['location_name'] ?? '—') . ' · ' . date('M j, g:i A', strtotime($s['match_date']))) ?></small></article><?php endforeach; ?></div><?php endforeach; ?>
                <?php if (empty($publicBracket)): ?><div class="empty">No upcoming schedules are available.</div><?php endif; ?>
            </div>
        </section>
    </div>
</main>
<footer class="viewer-footer">© 2026 TallyTech · Intramural Sports Festival Management System</footer>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
