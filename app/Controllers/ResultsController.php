<?php

namespace App\Controllers;

class ResultsController extends BaseController
{
    public function matches()
    {
        return $this->page('match', 'Match Results');
    }

    public function judged()
    {
        return $this->page('judged', 'Judged Results');
    }

    public function store()
    {
        try {
            $this->scoringService()->saveResult($this->resultPayload(), (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The result could not be submitted.'));
        }
        return redirect()->back()->with('success', 'Unofficial result submitted for validation.');
    }

    public function update(int $id)
    {
        try {
            $this->repository()->updateResult($id, $this->resultPayload(), (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The result operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Unofficial result updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteResult($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The result operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Unofficial result removed.');
    }

    public function validateResult(int $id)
    {
        if ($this->postString('confirmed_sheet') !== '1') {
            return redirect()->back()->with('error', 'Confirm comparison with the official score sheet/form before validation.');
        }
        try {
            $this->repository()->validateResult($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The result operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Result validated and published as official.');
    }

    private function page(string $type, string $title)
    {
        $repository = $this->repository();
        $event = $repository->activeEvent();
        $eventId = (int) ($event['id'] ?? 0);
        $schedules = $repository->resolveBracketSlots($repository->schedules($eventId, $type));
        $results = $repository->results($eventId, $type);

        if (session()->get('role') === 'facilitator') {
            $allowed = $repository->assignedSportIds((int) session()->get('user_id'));
            $schedules = array_values(array_filter($schedules, static fn(array $schedule): bool => in_array((int) $schedule['sport_id'], $allowed, true)));
            $scheduleIds = array_map('intval', array_column($schedules, 'id'));
            $results = array_values(array_filter($results, static fn(array $result): bool => in_array((int) $result['schedule_id'], $scheduleIds, true)));
        }

        $resultBySchedule = [];
        foreach ($results as $result) {
            $resultBySchedule[(int) $result['schedule_id']] = $result;
        }
        foreach ($schedules as &$schedule) {
            $result = $resultBySchedule[(int) $schedule['id']] ?? null;
            $schedule['result_status'] = $result['status'] ?? null;
            $schedule['score_by_team'] = [];
            $schedule['winner_team_id'] = null;
            $schedule['winner_name'] = null;
            if ($result) {
                foreach ($result['entries'] as $entry) {
                    $schedule['score_by_team'][(int) $entry['team_id']] = $entry['raw_score'];
                }
                $outcome = $this->matchOutcome($result['entries'] ?? []);
                if ($outcome !== null) {
                    $schedule['winner_team_id'] = (int) $outcome['winner']['team_id'];
                    $schedule['winner_name'] = (string) ($outcome['winner']['team_name'] ?? '');
                }
            }
        }
        unset($schedule);

        return view('results/index', [
            'title' => $title,
            'resultType' => $type,
            'activeEvent' => $event,
            'schedules' => $schedules,
            'results' => $results,
            'teams' => $repository->teams(),
        ]);
    }

    private function resultPayload(): array
    {
        $judged = $this->request->getPost('judged');
        $teamASets = $this->request->getPost('team_a_sets');
        $teamBSets = $this->request->getPost('team_b_sets');

        return [
            'schedule_id' => $this->postPositiveInt('schedule_id'),
            'team_a_score' => $this->postString('team_a_score'),
            'team_b_score' => $this->postString('team_b_score'),
            'team_a_sets' => is_array($teamASets) ? $teamASets : [],
            'team_b_sets' => is_array($teamBSets) ? $teamBSets : [],
            'judged' => is_array($judged) ? $judged : [],
            'notes' => $this->postString('notes'),
        ];
    }

    private function matchOutcome(array $entries): ?array
    {
        if (count($entries) !== 2) {
            return null;
        }

        $sets = [];
        foreach ($entries as $entry) {
            $decoded = null;
            if (is_string($entry['set_scores'] ?? null) && trim((string) $entry['set_scores']) !== '') {
                $decoded = json_decode((string) $entry['set_scores'], true);
            }
            $sets[] = is_array($decoded) ? array_values(array_map('floatval', $decoded)) : [(float) ($entry['raw_score'] ?? 0)];
        }

        $wins = [0, 0];
        for ($index = 0, $count = min(count($sets[0]), count($sets[1])); $index < $count; $index++) {
            if ($sets[0][$index] > $sets[1][$index]) {
                $wins[0]++;
            } elseif ($sets[1][$index] > $sets[0][$index]) {
                $wins[1]++;
            }
        }
        if ($wins[0] === $wins[1]) {
            return null;
        }

        return $wins[0] > $wins[1]
            ? ['winner' => $entries[0], 'loser' => $entries[1]]
            : ['winner' => $entries[1], 'loser' => $entries[0]];
    }
}
