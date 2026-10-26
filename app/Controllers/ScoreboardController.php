<?php

namespace App\Controllers;

class ScoreboardController extends BaseController
{
    public function version()
    {
        return $this->response->setHeader('Cache-Control', 'no-store')
            ->setJSON(['version' => (string) (cache()->get('tt_scoreboard_version') ?: 'initial')]);
    }

    public function index()
    {
        $rawSport = $this->request->getGet('sport');
        $sportId = is_scalar($rawSport) && preg_match('/^[1-9]\d*$/', (string) $rawSport) ? (int) $rawSport : null;
        $requestedView = strtolower(trim((string) $this->request->getGet('view')));
        $overall = $requestedView === 'overall' || $sportId === null;

        $data = $this->scoringService()->scoreboard($sportId, $overall);
        $data['title'] = 'Live Scoreboard';
        $data['scoreboardVersion'] = (string) (cache()->get('tt_scoreboard_version') ?: 'initial');
        return view('scoreboard/index', $data);
    }
}
