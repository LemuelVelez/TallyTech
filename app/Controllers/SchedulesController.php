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
        $round = trim($this->postString('round')) ?: 'Elimination';
        if (! $sportId || ! $locationId || $date === '') {
            return ['error' => 'Sport, location, and match date are required.'];
        }
        if (! in_array($status, ['scheduled', 'played', 'cancelled'], true)) {
            return ['error' => 'Select a valid schedule status.'];
        }
        if (mb_strlen($round) > 80) {
            return ['error' => 'Round name must be 80 characters or fewer.'];
        }

        $parsed = $this->parseDateTime($date);
        if (! $parsed) {
            return ['error' => 'Enter a valid match date and time.'];
        }

        return [
            'event_id' => (int) $event['id'],
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => $round,
            'match_date' => $parsed->format('Y-m-d H:i:s'),
            'team_a_id' => $this->postPositiveInt('team_a_id') ?: null,
            'team_b_id' => $this->postPositiveInt('team_b_id') ?: null,
            'status' => $status,
        ];
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
