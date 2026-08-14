<?php
$role=(string)session()->get('role'); $path=trim(uri_string(),'/');
$menus=[
'admin'=>[['dashboard','Dashboard','▦'],['notifications','Notifications','◉'],['team-ranking','Team Ranking','♜'],['teams','Teams','♟'],['events','Add Event','◫'],['sports','Add Sport','◆'],['schedules','Add Schedule','▤'],['sports-managers','Sports Managers','♙'],['reports','Reports','▥'],['settings','Settings','⚙']],
'manager'=>[['dashboard','Dashboard','▦'],['notifications','Notifications','◉'],['team-ranking','Team Ranking','♜'],['weighted-points','Weighted Points','◈'],['match-results','Match Results','▤'],['judged-results','Judged Results','◇'],['facilitators','Facilitators','♙'],['reports','Reports','▥'],['settings','Settings','⚙']],
'validator'=>[['dashboard','Dashboard','▦'],['notifications','Notifications','◉'],['weighted-points','Weighted Points','◈'],['match-results','Match Results','▤'],['judged-results','Judged Results','◇'],['settings','Settings','⚙']],
'facilitator'=>[['dashboard','Dashboard','▦'],['notifications','Notifications','◉'],['team-ranking','Team Ranking','♜'],['match-results','Match Results','▤'],['judged-results','Judged Results','◇'],['settings','Settings','⚙']],
]; ?>
<aside class="sidebar"><div class="nav-label">NAVIGATION</div><?php foreach($menus[$role]??[] as [$url,$label,$icon]): ?><a class="nav-item <?= $path===$url?'active':'' ?>" href="<?= site_url($url) ?>"><span><?= $icon ?></span><?= esc($label) ?></a><?php endforeach; ?><a class="nav-item logout" href="<?= site_url('logout') ?>"><span>↪</span>Logout</a></aside>
