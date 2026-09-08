<?php

namespace App\Controllers;

class SportScoresController extends BaseController
{
    public function index()
    {
        $rawSport = $this->request->getGet('sport');
        $sportId = is_scalar($rawSport) && preg_match('/^[1-9]\d*$/', (string) $rawSport) ? (int) $rawSport : null;
        $data = $this->scoringService()->sportScores($sportId);
        $data['title'] = 'Sport Scores';
        return view('sport_scores/index', $data);
    }
}
