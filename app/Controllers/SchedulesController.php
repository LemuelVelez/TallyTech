<?php

namespace App\Controllers;

class SchedulesController extends BaseController
{
    public function index()
    {
        $data = $this->scoringService()->commonData();
        $data['title'] = 'Team Schedules';
        $data['schedules'] = $this->repository()->schedules((int) ($data['activeEvent']['id'] ?? 0));
        return view('schedules/index', $data);
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
            return redirect()->back()->withInput()->with('error', $e->getMessage());
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
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Schedule updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteSchedule($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Schedule removed.');
    }

    private function schedulePayload(): array
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return ['error' => 'Add and activate an event first.'];
        }
        $sportId = (int) $this->request->getPost('sport_id');
        $locationId = (int) $this->request->getPost('location_id');
        $date = trim((string) $this->request->getPost('match_date'));
        $status = (string) ($this->request->getPost('status') ?: 'scheduled');
        if (! $sportId || ! $locationId || ! $date) {
            return ['error' => 'Sport, location, and match date are required.'];
        }
        if (! in_array($status, ['scheduled', 'played', 'cancelled'], true)) {
            return ['error' => 'Select a valid schedule status.'];
        }
        $parsed = \DateTime::createFromFormat('Y-m-d\TH:i', $date) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if (! $parsed) {
            return ['error' => 'Enter a valid match date and time.'];
        }
        return [
            'event_id' => (int) $event['id'],
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => trim((string) $this->request->getPost('round')) ?: 'Elimination',
            'match_date' => $parsed->format('Y-m-d H:i:s'),
            'team_a_id' => $this->request->getPost('team_a_id') ?: null,
            'team_b_id' => $this->request->getPost('team_b_id') ?: null,
            'status' => $status,
        ];
    }
}
