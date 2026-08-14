<?php

namespace App\Controllers;

class WeightedPointsController extends BaseController
{
    public function index()
    {
        $event = $this->repository()->activeEvent();
        return view('weighted_points/index', [
            'title' => 'Weighted Points',
            'activeEvent' => $event,
            'sports' => $this->repository()->sports((int) ($event['id'] ?? 0)),
            'weightedPoints' => $this->repository()->weightedPoints((int) ($event['id'] ?? 0)),
        ]);
    }

    public function store()
    {
        $payload = $this->pointsPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        $payload['submitted_at'] = date('Y-m-d H:i:s');
        try {
            $this->repository()->saveWeightedPoints($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Weighted points submitted for validation.');
    }

    public function update(int $id)
    {
        $payload = $this->pointsPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateWeightedPoints($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Weighted points updated and returned to pending validation.');
    }

    public function validatePoints(int $id)
    {
        try {
            $this->repository()->validateWeightedPoints($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Weighted points validated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteWeightedPoints($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Weighted points removed.');
    }

    private function pointsPayload(): array
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return ['error' => 'No active event.'];
        }
        $sportId = (int) $this->request->getPost('sport_id');
        if (! $sportId) {
            return ['error' => 'Select a sport.'];
        }
        $values = [];
        foreach (['first_points', 'second_points', 'third_points', 'participation_points'] as $field) {
            $value = filter_var($this->request->getPost($field), FILTER_VALIDATE_FLOAT);
            if ($value === false || $value < 0) {
                return ['error' => 'All point values must be valid non-negative numbers.'];
            }
            $values[$field] = (float) $value;
        }
        return ['event_id' => (int) $event['id'], 'sport_id' => $sportId] + $values;
    }
}
