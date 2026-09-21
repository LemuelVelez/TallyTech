<?php
$rankingRows = is_array($ranking ?? null) ? $ranking : [];
$scoreboardTheme = ($rankingVariant ?? 'admin') === 'scoreboard';
$provisional = ! empty($rankingProvisional);
$subtitle = (string) ($rankingSubtitle ?? ($provisional ? 'Pending results · provisional points' : 'Medal finishes and accumulated official points'));
$emptyMessage = (string) ($rankingEmptyMessage ?? ($provisional ? 'No provisional ranking data is available.' : 'No validated results are available for ranking.'));
$placeLabels = ['1st Place', '2nd Place', '3rd Place', '4th Place'];
$icons = ['trophy', 'medal', 'award', 'target'];
?>
<div class="team-ranking<?= $scoreboardTheme ? ' team-ranking--scoreboard' : '' ?><?= $provisional ? ' team-ranking--provisional' : '' ?>">
    <div class="podium team-ranking-podium">
        <?php foreach (array_slice($rankingRows, 0, 4) as $i => $team): ?>
            <article class="podium-card p<?= (int) ($i + 1) ?>">
                <span><?= ui_icon($icons[$i]) ?></span>
                <small><?= esc($placeLabels[$i]) ?></small>
                <h3><?= esc($team['name'] ?? '') ?></h3>
                <b><?= esc(format_points($team['total_points'] ?? 0)) ?></b>
                <em>Total Points</em>
            </article>
        <?php endforeach; ?>
        <?php if (! $rankingRows): ?><div class="empty"><?= esc($emptyMessage) ?></div><?php endif; ?>
    </div>

    <section class="panel team-ranking-table-panel">
        <div class="panel-head">
            <div>
                <h2>Overall Team Ranking</h2>
                <p><?= esc($subtitle) ?></p>
                <p class="team-ranking-note">1st, 2nd, 3rd, and 4th show how many sports each team finished in that place.</p>
            </div>
            <?php if ($provisional): ?><span class="badge unofficial">PROVISIONAL</span><?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th class="team-ranking-col-rank">Rank</th><th class="team-ranking-col-team">Team</th><th title="Sports finished in 1st place">1st</th><th title="Sports finished in 2nd place">2nd</th><th title="Sports finished in 3rd place">3rd</th><th title="Sports finished in 4th place">4th</th><th>Total Points</th></tr></thead>
                <tbody>
                <?php foreach ($rankingRows as $i => $team): ?>
                    <tr>
                        <td class="team-ranking-col-rank"><span class="rank-no"><?= esc((string) ($i + 1)) ?></span></td>
                        <td class="team-ranking-col-team"><b><?= esc($team['name'] ?? '') ?></b><small class="muted"><?= esc($team['code'] ?? '') ?></small></td>
                        <td><?= esc((string) (int) ($team['firsts'] ?? 0)) ?></td>
                        <td><?= esc((string) (int) ($team['seconds'] ?? 0)) ?></td>
                        <td><?= esc((string) (int) ($team['thirds'] ?? 0)) ?></td>
                        <td><?= esc((string) (int) ($team['fourths'] ?? 0)) ?></td>
                        <td><b><?= esc(format_points($team['total_points'] ?? 0)) ?></b></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $rankingRows): ?><tr><td colspan="7" class="empty"><?= esc($emptyMessage) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
