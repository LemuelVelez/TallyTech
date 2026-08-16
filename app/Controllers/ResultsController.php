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
        $schedules = $repository->schedules($eventId, $type);
        $results = $repository->results($eventId, $type);
        if (session()->get('role') === 'facilitator') {
            $allowed = $repository->assignedSportIds((int) session()->get('user_id'));
            $schedules = array_values(array_filter($schedules, static fn(array $schedule): bool => in_array((int) $schedule['sport_id'], $allowed, true)));
            $scheduleIds = array_map('intval', array_column($schedules, 'id'));
            $results = array_values(array_filter($results, static fn(array $result): bool => in_array((int) $result['schedule_id'], $scheduleIds, true)));
        }
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

        return [
            'schedule_id' => $this->postPositiveInt('schedule_id'),
            'team_a_score' => $this->postString('team_a_score'),
            'team_b_score' => $this->postString('team_b_score'),
            'judged' => is_array($judged) ? $judged : [],
            'notes' => $this->postString('notes'),
        ];
    }
}
