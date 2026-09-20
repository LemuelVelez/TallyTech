<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="page-head"><div><h1>Team Ranking</h1><p><?= esc($activeEvent['name'] ?? 'Official event standings') ?> · validated results only</p></div></div>
<?= view('partials/team_ranking', [
    'ranking' => $ranking ?? [],
    'rankingVariant' => 'admin',
    'rankingProvisional' => false,
    'rankingSubtitle' => 'Medal finishes and accumulated official points',
    'rankingEmptyMessage' => 'No validated results are available for ranking.',
]) ?>
<?= $this->endSection() ?>
