<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="page-head"><div><h1>Sport Scores</h1><p>Per-sport set scores grouped by category, with match outcome based on sets won.</p></div></div>
<?php if ($activeEvent): ?><div class="event-banner"><b>Active event:</b> <?= esc($activeEvent['name']) ?></div><?php endif; ?>
<?= view('partials/sport_score_table', [
    'sportScoreTable' => $sportScoreTable,
    'sportScorePanelClass' => 'panel',
    'sportScoreAction' => 'sport-scores',
]) ?>
<?php if ($selectedSport): ?>
<section class="panel sport-points-panel">
    <div class="panel-head"><div><h2>Overall Sport Points</h2><p><?= esc($selectedSport['name']) ?> only · validated results.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Rank</th><th>Team</th><th>Points</th></tr></thead><tbody>
        <?php foreach ($ranking as $i => $team): ?><tr><td><b><?= $i + 1 ?></b></td><td><?= esc($team['name']) ?></td><td><b><?= esc(format_points($team['total_points'])) ?></b></td></tr><?php endforeach; ?>
        <?php if (empty($ranking)): ?><tr><td colspan="3" class="empty">No validated sport points yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
