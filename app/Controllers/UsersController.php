<?php

namespace App\Controllers;

class UsersController extends BaseController
{
    private const ADMIN_MANAGED_ROLES = ['admin', 'manager', 'validator', 'facilitator'];

    public function index()
    {
        $users = [];
        foreach (self::ADMIN_MANAGED_ROLES as $role) {
            $users = array_merge($users, $this->repository()->usersByRole($role));
        }
        usort($users, static fn(array $a, array $b): int => strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? '')));

        $event = $this->repository()->activeEvent();

        return view('users/index', [
            'title' => 'User Management',
            'manageMode' => 'admin',
            'roleType' => null,
            'roleOptions' => self::ADMIN_MANAGED_ROLES,
            'users' => $users,
            'sports' => $this->repository()->sports((int) ($event['id'] ?? 0)),
            'activeEvent' => $event,
        ]);
    }

    public function sportsManagers()
    {
        return $this->index();
    }

    public function facilitators()
    {
        return $this->userPage('facilitator', 'Facilitators');
    }

    public function store()
    {
        $role = $this->adminRoleFromRequest();
        if ($role === null) {
            return redirect()->back()->withInput()->with('error', 'Select a valid user role.');
        }

        return $this->storeRole($role);
    }

    public function update(int $id)
    {
        $role = $this->adminRoleFromRequest();
        if ($role === null) {
            return redirect()->back()->with('error', 'Select a valid user role.');
        }

        return $this->updateRole($id, $role);
    }

    public function delete(int $id)
    {
        return $this->deleteManagedUser($id, 'User');
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
            'manageMode' => $role,
            'roleType' => $role,
            'roleOptions' => [$role],
            'users' => $this->repository()->usersByRole($role),
            'sports' => $this->repository()->sports((int) ($event['id'] ?? 0)),
            'activeEvent' => $event,
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
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The account could not be created.'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Username already exists or the account could not be created.');
        }

        return redirect()->back()->with('success', $this->roleLabel($role) . ' account added.');
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
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'Username already exists or the account could not be updated.'));
        }

        return redirect()->back()->with('success', $this->roleLabel($role) . ' account updated.');
    }

    private function deleteRole(int $id, string $role)
    {
        $allowedIds = array_map('intval', array_column($this->repository()->usersByRole($role), 'id'));
        if (! in_array($id, $allowedIds, true)) {
            return redirect()->back()->with('error', 'Account not found for this role.');
        }

        return $this->deleteManagedUser($id, $this->roleLabel($role));
    }

    private function deleteManagedUser(int $id, string $label)
    {
        try {
            $this->repository()->deleteUser($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The account operation could not be completed.'));
        }

        return redirect()->back()->with('success', $label . ' account removed.');
    }

    private function adminRoleFromRequest(): ?string
    {
        $role = $this->postString('role');

        return in_array($role, self::ADMIN_MANAGED_ROLES, true) ? $role : null;
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrator',
            'manager' => 'Sports Manager',
            'validator' => 'Validator',
            'facilitator' => 'Facilitator',
            default => 'User',
        };
    }

    private function userPayload(string $role, bool $passwordRequired): array
    {
        if (! in_array($role, self::ADMIN_MANAGED_ROLES, true)) {
            return ['error' => 'Select a valid user role.'];
        }

        $username = trim($this->postString('username'));
        $displayName = trim($this->postString('display_name'));
        $password = $this->postString('password');
        $status = $this->postString('status') ?: 'active';

        if (mb_strlen($username) < 3 || mb_strlen($username) > 80 || $displayName === '' || mb_strlen($displayName) > 120) {
            return ['error' => 'Username must be 3–80 characters and name must be 1–120 characters.'];
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

        $sportIds = [];
        if ($role === 'facilitator') {
            $rawSportIds = $this->request->getPost('sport_ids');
            if ($rawSportIds !== null && ! is_array($rawSportIds)) {
                return ['error' => 'One or more assigned sports are invalid.'];
            }

            foreach (is_array($rawSportIds) ? $rawSportIds : [] as $rawSportId) {
                if (! is_scalar($rawSportId)) {
                    return ['error' => 'One or more assigned sports are invalid.'];
                }
                $rawSportId = trim((string) $rawSportId);
                if (! preg_match('/^[1-9]\d*$/', $rawSportId)) {
                    return ['error' => 'One or more assigned sports are invalid.'];
                }
                $sportId = filter_var($rawSportId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($sportId === false) {
                    return ['error' => 'One or more assigned sports are invalid.'];
                }
                $sportIds[] = (int) $sportId;
            }
            $sportIds = array_values(array_unique($sportIds));

            if (! $sportIds && ($passwordRequired || $this->repository()->activeEvent())) {
                return ['error' => 'Assign at least one sport to the facilitator.'];
            }
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
