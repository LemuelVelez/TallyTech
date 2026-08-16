<?php

namespace App\Controllers;

class WeightedPointsController extends BaseController
{
    private const MAX_POINTS = 999999.99;

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
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'Weighted points could not be saved.'));
        }
        return redirect()->back()->with('success', 'Weighted points submitted for validation.');
    }

    public function update(int $id)
    {
        $payload = $this->pointsPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateWeightedPoints($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'Weighted points could not be saved.'));
        }
        return redirect()->back()->with('success', 'Weighted points updated and returned to pending validation.');
    }

    public function validatePoints(int $id)
    {
        try {
            $this->repository()->validateWeightedPoints($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The weighted-points operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Weighted points validated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteWeightedPoints($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The weighted-points operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Weighted points removed.');
    }

    private function pointsPayload(): array
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return ['error' => 'No active event.'];
        }
        $sportId = $this->postPositiveInt('sport_id');
        if (! $sportId) {
            return ['error' => 'Select a sport.'];
        }

        $values = [];
        foreach (['first_points', 'second_points', 'third_points', 'participation_points'] as $field) {
            $value = $this->validDecimal($this->postString($field), self::MAX_POINTS);
            if ($value === null) {
                return ['error' => 'All point values must be non-negative numbers with at most 2 decimal places and no more than 999999.99.'];
            }
            $values[$field] = $value;
        }

        return ['event_id' => (int) $event['id'], 'sport_id' => $sportId] + $values;
    }

    private function validDecimal(string $raw, float $max): ?string
    {
        $value = trim($raw);
        if ($value === '' || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        if ((float) $value > $max) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
