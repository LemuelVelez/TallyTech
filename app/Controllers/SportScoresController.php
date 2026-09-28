<?php

namespace App\Controllers;

class SportScoresController extends BaseController
{
    public function index()
    {
        $managerSportIds = $this->managerSportIds();
        $rawSport = $this->request->getGet('sport');
        $hasRequestedSport = is_scalar($rawSport) && trim((string) $rawSport) !== '';
        $sportId = $hasRequestedSport && preg_match('/^[1-9]\d*$/', (string) $rawSport) ? (int) $rawSport : 0;

        if (($hasRequestedSport && $sportId === 0) || ($sportId > 0 && ! in_array($sportId, $managerSportIds, true))) {
            $fallbackSportId = (int) ($managerSportIds[0] ?? 0);
            $target = site_url('sport-scores') . ($fallbackSportId > 0 ? '?sport=' . $fallbackSportId : '');
            return redirect()->to($target)->with('error', 'You can only view scores for your assigned sports.');
        }
        if ($sportId === 0) {
            $sportId = (int) ($managerSportIds[0] ?? 0);
        }

        $data = $this->scoringService()->sportScores($sportId ?: null, $managerSportIds);
        $data['title'] = 'Sport Scores';
        return view('sport_scores/index', $data);
    }
}
