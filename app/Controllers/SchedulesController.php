<?php

namespace App\Controllers;

class SchedulesController extends BaseController
{
    private const TOURNAMENT_FORMATS = ['single_elimination', 'double_elimination'];

    public function index()
    {
        $data = $this->scoringService()->commonData();
        $data['title'] = 'Tournament Schedules';
        $data['schedules'] = $this->repository()->resolveBracketSlots(
            $this->repository()->schedules((int) ($data['activeEvent']['id'] ?? 0))
        );
        $data['allLocations'] = $this->repository()->allLocations();
        return view('schedules/index', $data);
    }

    public function brackets()
    {
        $data = $this->scoringService()->commonData();
        $eventId = (int) ($data['activeEvent']['id'] ?? 0);
        $requestedSportId = $this->request->getGet('sport');
        $selectedSportId = is_scalar($requestedSportId) && preg_match('/^[1-9]\d*$/', (string) $requestedSportId)
            ? (int) $requestedSportId
            : (int) ($data['sports'][0]['id'] ?? 0);

        $validSportIds = array_map('intval', array_column($data['sports'], 'id'));
        if (! in_array($selectedSportId, $validSportIds, true)) {
            $selectedSportId = (int) ($data['sports'][0]['id'] ?? 0);
        }

        $schedules = $this->repository()->resolveBracketSlots($this->repository()->schedules($eventId));
        $data['schedules'] = array_values(array_filter($schedules, static fn(array $row): bool => (int) $row['sport_id'] === $selectedSportId));
        $data['selectedSportId'] = $selectedSportId;
        $data['selectedSport'] = null;
        foreach ($data['sports'] as $sport) {
            if ((int) $sport['id'] === $selectedSportId) {
                $data['selectedSport'] = $sport;
                break;
            }
        }
        $data['title'] = 'Bracket Management';
        return view('schedules/brackets', $data);
    }

    public function generateBracket()
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Add and activate an event first.');
        }

        $sportId = $this->postPositiveInt('sport_id');
        $locationId = $this->postPositiveInt('location_id');
        $format = $this->postString('tournament_format');
        $rawStart = trim($this->postString('start_time'));
        $parsedStart = $this->parseDateTime($rawStart);
        $interval = $this->postPositiveInt('interval_minutes') ?: 60;
        $courtLabel = trim($this->postString('court_label'));

        if (! $sportId || ! $locationId || ! in_array($format, self::TOURNAMENT_FORMATS, true) || ! $parsedStart) {
            return redirect()->back()->withInput()->with('error', 'Sport, tournament format, location, and bracket start time are required.');
        }

        $rawTeamIds = $this->request->getPost('team_ids');
        if ($rawTeamIds !== null && ! is_array($rawTeamIds)) {
            return redirect()->back()->withInput()->with('error', 'Selected teams are invalid.');
        }
        $teamIds = [];
        foreach (is_array($rawTeamIds) ? $rawTeamIds : [] as $rawTeamId) {
            if (! is_scalar($rawTeamId) || ! preg_match('/^[1-9]\d*$/', (string) $rawTeamId)) {
                return redirect()->back()->withInput()->with('error', 'Selected teams are invalid.');
            }
            $teamIds[] = (int) $rawTeamId;
        }

        try {
            $count = $this->repository()->generateBracket([
                'event_id' => (int) $event['id'],
                'sport_id' => $sportId,
                'location_id' => $locationId,
                'tournament_format' => $format,
                'start_time' => $parsedStart->format('Y-m-d H:i:s'),
                'interval_minutes' => $interval,
                'court_label' => $courtLabel ?: null,
            ], $teamIds, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The bracket could not be generated.'));
        }

        return redirect()->to(site_url('brackets') . '?sport=' . $sportId)->with('success', $count . ' bracket matches generated.');
    }

    public function store()
    {
        $payload = $this->schedulePayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        try {
            $this->repository()->createSchedule($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The schedule could not be created.'));
        }
        return redirect()->back()->with('success', 'Schedule added.');
    }

    public function update(int $id)
    {
        $payload = $this->schedulePayload();
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateSchedule($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The schedule operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Schedule updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteSchedule($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The schedule operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Schedule removed.');
    }

    private function schedulePayload(): array
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return ['error' => 'Add and activate an event first.'];
        }

        $sportId = $this->postPositiveInt('sport_id');
        $locationId = $this->postPositiveInt('location_id');
        $date = trim($this->postString('match_date'));
        $status = $this->postString('status') ?: 'scheduled';
        $format = $this->postString('tournament_format') ?: 'single_elimination';
        $stage = $this->postString('stage') ?: 'final';
        $stageData = $this->stageData($stage);

        if (! $sportId || ! $locationId || $date === '') {
            return ['error' => 'Sport, location, and match date are required.'];
        }
        if (! in_array($format, self::TOURNAMENT_FORMATS, true)) {
            return ['error' => 'Select a valid tournament format.'];
        }
        if (! $stageData) {
            return ['error' => 'Select a valid round or stage.'];
        }
        if (! in_array($status, ['scheduled', 'played', 'cancelled'], true)) {
            return ['error' => 'Select a valid schedule status.'];
        }

        $parsed = $this->parseDateTime($date);
        if (! $parsed) {
            return ['error' => 'Enter a valid match date and time.'];
        }

        return [
            'event_id' => (int) $event['id'],
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => $stageData['round'],
            'tournament_format' => $format,
            'match_date' => $parsed->format('Y-m-d H:i:s'),
            'team_a_id' => $this->postPositiveInt('team_a_id') ?: null,
            'team_b_id' => $this->postPositiveInt('team_b_id') ?: null,
            'status' => $status,
            'match_code' => strtoupper(trim($this->postString('match_code'))) ?: null,
            'phase' => $stageData['phase'],
            'bracket_side' => $stageData['bracket_side'],
            'bracket_order' => $this->postPositiveInt('bracket_order') ?: 0,
            'feeds_from_a' => strtoupper(trim($this->postString('feeds_from_a'))) ?: null,
            'feeds_from_a_type' => trim($this->postString('feeds_from_a_type')) ?: null,
            'feeds_from_b' => strtoupper(trim($this->postString('feeds_from_b'))) ?: null,
            'feeds_from_b_type' => trim($this->postString('feeds_from_b_type')) ?: null,
            'court_label' => trim($this->postString('court_label')) ?: null,
            'is_conditional' => $this->postPositiveInt('is_conditional') ? 1 : 0,
            'scheduling_note' => trim($this->postString('scheduling_note')) ?: null,
        ];
    }

    private function stageData(string $stage): ?array
    {
        return match ($stage) {
            'playoff' => ['round' => 'Playoff', 'phase' => 'playoff', 'bracket_side' => 'upper'],
            'quarter' => ['round' => 'Quarter Final', 'phase' => 'quarter', 'bracket_side' => 'upper'],
            'semi' => ['round' => 'Semi Final', 'phase' => 'semi', 'bracket_side' => 'upper'],
            'final' => ['round' => 'Final', 'phase' => 'final', 'bracket_side' => 'grand'],
            'lower_r1' => ['round' => 'Lower Round 1', 'phase' => 'lower_r1', 'bracket_side' => 'lower'],
            'upper_final' => ['round' => 'Upper Final', 'phase' => 'final', 'bracket_side' => 'upper'],
            'lower_final' => ['round' => 'Lower Final', 'phase' => 'final', 'bracket_side' => 'lower'],
            'grand_final' => ['round' => 'Grand Final', 'phase' => 'final', 'bracket_side' => 'grand'],
            'tiebreaker' => ['round' => 'Bracket Reset Final', 'phase' => 'tiebreaker', 'bracket_side' => 'grand'],
            default => null,
        };
    }

    private function parseDateTime(string $value): ?\DateTime
    {
        foreach (['Y-m-d\\TH:i', 'Y-m-d H:i:s'] as $format) {
            $parsed = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($parsed && ! $hasErrors && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }
}
