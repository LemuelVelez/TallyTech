<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$draftSportGroups = is_array($draftSportGroups ?? null) ? $draftSportGroups : [];
$teams = is_array($teams ?? null) ? $teams : [];
$roleLabels = ['EXP Lane', 'Jungler', 'Mid Lane', 'Gold Lane', 'Roamer'];
?>
<div class="page-head draft-page-head">
    <div>
        <h1 class="title-with-icon"><?= ui_icon('sliders') ?><span>Draft Generator</span></h1>
        <p>Create a clean game draft sheet for two teams, roles, bans, match metadata, and winner details.</p>
    </div>
</div>

<?php if ($activeEvent): ?>
    <div class="event-banner"><b>Active event:</b> <?= esc($activeEvent['name']) ?></div>
<?php endif; ?>

<section class="panel draft-generator-panel" data-draft-generator>
    <div class="draft-section-title">
        <span><?= ui_icon('clipboard-score') ?></span>
        <div><h2>Draft Settings</h2><p>Complete the match details, then generate the draft preview.</p></div>
    </div>

    <form class="draft-generator-form" data-draft-form novalidate>
        <div class="draft-meta-grid">
            <label>
                <span class="field-label">Game number <sup>1</sup></span>
                <select name="game_number" data-draft-game required>
                    <?php for ($game = 1; $game <= 7; $game++): ?>
                        <option value="<?= $game ?>"><?= $game ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                <span class="field-label">Duration (M:SS)</span>
                <input name="duration" data-draft-duration inputmode="numeric" autocomplete="off" placeholder="18:42" pattern="^\d{1,3}:[0-5]\d$">
            </label>
            <label>
                <span class="field-label">Number of bans</span>
                <select name="ban_count" data-draft-ban-count>
                    <?php for ($ban = 0; $ban <= 5; $ban++): ?>
                        <option value="<?= $ban ?>" <?= $ban === 5 ? 'selected' : '' ?>><?= $ban ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                <span class="field-label">Winner</span>
                <select name="winner" data-draft-winner>
                    <option value="">Not decided</option>
                    <option value="team1">Team 1</option>
                    <option value="team2">Team 2</option>
                </select>
            </label>
            <label>
                <span class="field-label">Sport</span>
                <select name="sport" data-draft-sport>
                    <option value="">Select sport</option>
                    <?php foreach ($draftSportGroups as $group): ?>
                        <option value="<?= esc($group['name'], 'attr') ?>" data-categories="<?= esc(implode('|', $group['categories']), 'attr') ?>"><?= esc($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="field-label">Category</span>
                <select name="category" data-draft-category disabled>
                    <option value="">Select category</option>
                </select>
            </label>
            <label>
                <span class="field-label">VOD</span>
                <input name="vod" data-draft-vod autocomplete="off" placeholder="https://…">
            </label>
            <label>
                <span class="field-label">Map / Court</span>
                <input name="map" data-draft-map autocomplete="off" placeholder="Main Court / Map name">
            </label>
        </div>

        <div class="draft-versus-grid">
            <?php foreach ([1, 2] as $side): ?>
                <fieldset class="draft-team-card" data-draft-team-card="<?= $side ?>">
                    <legend>Team <?= $side ?></legend>
                    <div class="draft-team-card-head">
                        <span class="draft-team-mark" aria-hidden="true"><?= ui_icon($side === 1 ? 'award' : 'target') ?></span>
                        <label class="draft-team-select">
                            <span class="field-label">Team <?= $side ?> <sup>1</sup></span>
                            <select name="team_<?= $side ?>" data-draft-team="<?= $side ?>" required>
                                <option value="">Select team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= esc((string) ($team['id'] ?? ''), 'attr') ?>" data-team-name="<?= esc((string) ($team['name'] ?? ''), 'attr') ?>"><?= esc((string) ($team['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span class="field-label">Side <sup>1</sup></span>
                            <input name="team_<?= $side ?>_side" data-draft-side="<?= $side ?>" autocomplete="off" value="<?= $side === 1 ? 'Blue' : 'Red' ?>" required>
                        </label>
                    </div>

                    <div class="draft-role-fields">
                        <?php foreach ($roleLabels as $roleIndex => $roleLabel): ?>
                            <label>
                                <span class="field-label"><?= esc($roleLabel) ?> <sup>1</sup></span>
                                <input name="team_<?= $side ?>_role_<?= $roleIndex + 1 ?>" data-draft-role="<?= $side ?>:<?= $roleIndex ?>" autocomplete="off" placeholder="Player / participant" required>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="draft-ban-fields">
                        <?php for ($banIndex = 1; $banIndex <= 5; $banIndex++): ?>
                            <label data-draft-ban-row="<?= $side ?>:<?= $banIndex ?>">
                                <span class="field-label">Ban <?= $banIndex ?></span>
                                <input name="team_<?= $side ?>_ban_<?= $banIndex ?>" data-draft-ban="<?= $side ?>:<?= $banIndex ?>" autocomplete="off" placeholder="Ban / excluded pick">
                            </label>
                        <?php endfor; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <p class="draft-required-note"><sup>1</sup> Mandatory</p>
        <div class="draft-form-message" data-draft-message role="alert" aria-live="polite"></div>
        <div class="draft-actions">
            <button class="btn primary" type="submit"><?= ui_icon('play-circle') ?><span>Generate Draft</span></button>
            <button class="btn" type="reset" data-draft-reset><?= ui_icon('x') ?><span>Reset</span></button>
        </div>
    </form>
</section>

<section class="panel draft-preview-panel" data-draft-preview aria-live="polite">
    <div class="panel-head">
        <div><h2>Draft Preview</h2><p>Generated locally from the form above; no schedule or result records are changed.</p></div>
        <button class="btn tiny" type="button" data-draft-copy disabled><?= ui_icon('clipboard-score') ?><span>Copy Summary</span></button>
    </div>

    <div class="draft-preview-empty" data-draft-preview-empty>
        <?= ui_icon('sliders') ?>
        <b>No draft generated yet</b>
        <span>Fill in the mandatory fields and select Generate Draft.</span>
    </div>

    <div class="draft-sheet" data-draft-sheet hidden>
        <header class="draft-sheet-head">
            <div>
                <span class="draft-sheet-kicker" data-preview-event><?= esc((string) ($activeEvent['name'] ?? 'TallyTech')) ?></span>
                <h3><span data-preview-sport>Match Draft</span> <small data-preview-category></small></h3>
            </div>
            <div class="draft-game-pill">Game <b data-preview-game>1</b></div>
        </header>

        <div class="draft-match-meta">
            <span><?= ui_icon('calendar-clock') ?><b>Duration</b><em data-preview-duration>—</em></span>
            <span><?= ui_icon('target') ?><b>Map / Court</b><em data-preview-map>—</em></span>
            <span><?= ui_icon('play-circle') ?><b>VOD</b><em data-preview-vod>—</em></span>
        </div>

        <div class="draft-sheet-versus">
            <?php foreach ([1, 2] as $side): ?>
                <article class="draft-preview-team" data-preview-team-card="<?= $side ?>">
                    <div class="draft-preview-team-head">
                        <span class="draft-preview-team-icon" aria-hidden="true"><?= ui_icon($side === 1 ? 'award' : 'target') ?></span>
                        <div><small>Team <?= $side ?></small><h4 data-preview-team-name="<?= $side ?>">Team <?= $side ?></h4></div>
                        <span class="draft-winner-badge" data-preview-winner="<?= $side ?>" hidden><?= ui_icon('trophy') ?> Winner</span>
                    </div>
                    <div class="draft-preview-side"><span>Side</span><b data-preview-side="<?= $side ?>">—</b></div>
                    <div class="draft-preview-roster">
                        <?php foreach ($roleLabels as $roleIndex => $roleLabel): ?>
                            <div><span><?= esc($roleLabel) ?></span><b data-preview-role="<?= $side ?>:<?= $roleIndex ?>">—</b></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="draft-preview-bans">
                        <span class="draft-preview-bans-title">Bans</span>
                        <div data-preview-bans="<?= $side ?>"><span class="draft-empty-ban">None</span></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
