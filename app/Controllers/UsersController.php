<?php

namespace App\Controllers;

class UsersController extends BaseController
{
    public function sportsManagers()
    {
        return $this->userPage('manager', 'Sports Managers');
    }

    public function facilitators()
    {
        return $this->userPage('facilitator', 'Facilitators');
    }

    public function storeSportsManager()
    {
        return $this->storeRole('manager');
    }

    public function storeFacilitator()
    {
        return $this->storeRole('facilitator');
    }

    public function updateSportsManager(int $id)
    {
        return $this->updateRole($id, 'manager');
    }

    public function updateFacilitator(int $id)
    {
        return $this->updateRole($id, 'facilitator');
    }

    public function deleteSportsManager(int $id)
    {
        return $this->deleteRole($id, 'manager');
    }

    public function deleteFacilitator(int $id)
    {
        return $this->deleteRole($id, 'facilitator');
    }

    private function userPage(string $role, string $title)
    {
        $event = $this->repository()->activeEvent();
        return view('users/index', [
            'title' => $title,
            'roleType' => $role,
            'users' => $this->repository()->usersByRole($role),
            'sports' => $this->repository()->sports((int) ($event['id'] ?? 0)),
        ]);
    }

    private function storeRole(string $role)
    {
        $payload = $this->userPayload($role, true);
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        try {
            $this->repository()->createUser($payload['user'], $payload['sport_ids'], (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Username already exists or the account could not be created.');
        }
        return redirect()->back()->with('success', ucfirst($role) . ' account added.');
    }

    private function updateRole(int $id, string $role)
    {
        $payload = $this->userPayload($role, false);
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateUser($id, $payload['user'], $payload['sport_ids'], (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', ucfirst($role) . ' account updated.');
    }

    private function deleteRole(int $id, string $role)
    {
        $allowedIds = array_map('intval', array_column($this->repository()->usersByRole($role), 'id'));
        if (! in_array($id, $allowedIds, true)) {
            return redirect()->back()->with('error', 'Account not found for this role.');
        }
        try {
            $this->repository()->deleteUser($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', ucfirst($role) . ' account removed.');
    }

    private function userPayload(string $role, bool $passwordRequired): array
    {
        $username = trim((string) $this->request->getPost('username'));
        $displayName = trim((string) $this->request->getPost('display_name'));
        $password = (string) $this->request->getPost('password');
        $status = (string) ($this->request->getPost('status') ?: 'active');
        if (strlen($username) < 3 || $displayName === '') {
            return ['error' => 'Username and name are required.'];
        }
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            return ['error' => 'Username may only contain letters, numbers, dots, underscores, and hyphens.'];
        }
        if (! in_array($status, ['active', 'inactive'], true)) {
            return ['error' => 'Select a valid account status.'];
        }
        if ($passwordRequired && $password === '') {
            return ['error' => 'Password is required.'];
        }
        if ($password !== '' && ! preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
            return ['error' => 'Password must be 8+ characters with uppercase, lowercase, number, and special character.'];
        }
        $sportIds = $role === 'facilitator' ? (array) $this->request->getPost('sport_ids') : [];
        if ($role === 'facilitator' && ! array_filter($sportIds)) {
            return ['error' => 'Assign at least one sport to the facilitator.'];
        }
        $user = [
            'username' => $username,
            'display_name' => $displayName,
            'role' => $role,
            'status' => $status,
        ];
        if ($password !== '') {
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($passwordRequired) {
            $user['created_at'] = date('Y-m-d H:i:s');
        }
        return ['user' => $user, 'sport_ids' => $sportIds];
    }
}
