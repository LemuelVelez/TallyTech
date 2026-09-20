<?php

namespace App\Controllers;

class ScoreboardController extends BaseController
{
    public function index()
    {
        $rawSport = $this->request->getGet('sport');
        $sportId = is_scalar($rawSport) && preg_match('/^[1-9]\d*$/', (string) $rawSport) ? (int) $rawSport : null;
        $requestedView = strtolower(trim((string) $this->request->getGet('view')));
        $overall = $requestedView === 'overall' || $sportId === null;

        $data = $this->scoringService()->scoreboard($sportId, $overall);
        $data['title'] = 'Live Scoreboard';
        return view('scoreboard/index', $data);
    }
}
