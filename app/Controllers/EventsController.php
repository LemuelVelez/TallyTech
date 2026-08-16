<?php

namespace App\Controllers;

class EventsController extends BaseController
{
    public function index()
    {
        return view('events/index', ['title' => 'Events', 'events' => $this->repository()->events()]);
    }

    public function store()
    {
        $payload = $this->eventPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        try {
            $this->repository()->createEvent($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Event could not be created. Check for a duplicate event name and year.');
        }
        return redirect()->back()->with('success', 'Event added.');
    }

    public function update(int $id)
    {
        $payload = $this->eventPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateEvent($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Event could not be updated. Check for duplicate values.');
        }
        return redirect()->back()->with('success', 'Event updated.');
    }

    public function activate(int $id)
    {
        try {
            $this->repository()->activateEvent($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The event operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Active event updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteEvent($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The event operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Event removed.');
    }

    private function eventPayload(): array
    {
        $name = trim($this->postString('name'));
        $yearRaw = trim($this->postString('year'));
        $year = preg_match('/^\d{4}$/', $yearRaw) ? (int) $yearRaw : 0;
        $start = trim($this->postString('start_date')) ?: null;
        $end = trim($this->postString('end_date')) ?: null;
        $status = $this->postString('status') ?: 'active';
        if ($name === '' || mb_strlen($name) > 150 || $year < 2000 || $year > 2100) {
            return ['error' => 'Enter a valid event name and year.'];
        }
        if (! in_array($status, ['draft', 'active', 'completed'], true)) {
            return ['error' => 'Select a valid event status.'];
        }
        if (($start && ! $this->validDate($start)) || ($end && ! $this->validDate($end))) {
            return ['error' => 'Enter valid event dates.'];
        }
        if ($start && $end && $end < $start) {
            return ['error' => 'End date cannot be before the start date.'];
        }
        $isActive = $this->postString('is_active') === '1' ? 1 : 0;
        if ($isActive) {
            $status = 'active';
        }
        return [
            'name' => $name,
            'year' => $year,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'is_active' => $isActive,
        ];
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
