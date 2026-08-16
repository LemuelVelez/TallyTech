<?php

namespace App\Controllers;

class TeamsController extends BaseController
{
    public function index()
    {
        return view('teams/index', ['title' => 'Teams', 'teams' => $this->repository()->teams()]);
    }

    public function store()
    {
        $payload = $this->teamPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        try {
            $this->repository()->createTeam($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Team name or code already exists.');
        }
        return redirect()->back()->with('success', 'Team added.');
    }

    public function update(int $id)
    {
        $payload = $this->teamPayload();
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateTeam($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Team could not be updated. Make sure its name and code are unique.');
        }
        return redirect()->back()->with('success', 'Team updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteTeam($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The team operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Team removed.');
    }

    private function teamPayload(): array
    {
        $name = trim($this->postString('name'));
        $code = strtoupper(trim($this->postString('code')));
        if ($name === '' || $code === '') {
            return ['error' => 'Team name and code are required.'];
        }
        if (strlen($name) > 150 || strlen($code) > 30) {
            return ['error' => 'Team name or code is too long.'];
        }
        return ['name' => $name, 'code' => $code];
    }
}
