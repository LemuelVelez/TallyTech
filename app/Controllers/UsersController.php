<?php
namespace App\Controllers;

class UsersController extends BaseController
{
    public function index()
    {
        $repo = $this->repository();
        $event = $repo->activeEvent();
        $users = [];
        foreach (['admin','manager','facilitator'] as $role) {
            $users = array_merge($users, $repo->usersByRole($role));
        }
        usort($users, static fn($a, $b) => strcasecmp((string) $a['display_name'], (string) $b['display_name']));

        return view('users/index', [
            'title' => 'User Management',
            'manageMode' => 'admin',
            'roleType' => 'manager',
            'roleOptions' => ['manager'],
            'users' => $users,
            'sports' => $repo->sports((int) ($event['id'] ?? 0)),
            'activeEvent' => $event,
        ]);
    }

    public function sportsManagers(){return $this->index();}

    public function facilitators()
    {
        $repo = $this->repository();
        $event = $repo->activeEvent();
        $managerSportIds = $this->managerSportIds();
        $users = array_values(array_filter($repo->usersByRole('facilitator'), static function (array $user) use ($managerSportIds): bool {
            $userSportIds = array_map('intval', array_column($user['sports'] ?? [], 'id'));
            return $userSportIds !== [] && array_diff($userSportIds, $managerSportIds) === [];
        }));
        $sports = array_values(array_filter(
            $repo->sports((int) ($event['id'] ?? 0)),
            static fn(array $sport): bool => in_array((int) $sport['id'], $managerSportIds, true)
        ));

        return view('users/index', [
            'title' => 'Facilitators',
            'manageMode' => 'facilitator',
            'roleType' => 'facilitator',
            'roleOptions' => ['facilitator'],
            'users' => $users,
            'sports' => $sports,
            'activeEvent' => $event,
        ]);
    }

    public function store(){return $this->storeRole('manager');}
    public function update(int $id){return $this->updateRole($id,'manager');}
    public function delete(int $id){return $this->deleteManagedUser($id,'User');}
    public function storeSportsManager(){return $this->storeRole('manager');}
    public function storeFacilitator(){return $this->storeRole('facilitator');}
    public function updateSportsManager(int $id){return $this->updateRole($id,'manager');}
    public function updateFacilitator(int $id){return $this->updateRole($id,'facilitator');}
    public function deleteSportsManager(int $id){return $this->deleteRole($id,'manager');}
    public function deleteFacilitator(int $id){return $this->deleteRole($id,'facilitator');}

    public function resetPassword(int $id)
    {
        if ((string) session()->get('role') === 'manager' && ! $this->managerCanManageFacilitator($id)) {
            return redirect()->back()->with('error', 'You can only manage facilitators assigned within your sports.');
        }
        try {
            $password = $this->repository()->resetGeneratedPassword($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'Password could not be reset.'));
        }
        return redirect()->back()->with('success', 'New generated password: ' . $password);
    }

    private function storeRole(string $role)
    {
        $payload = $this->userPayload($role, true);
        if (isset($payload['error'])) return redirect()->back()->withInput()->with('error', $payload['error']);
        try {
            $this->repository()->createUser($payload['user'], $payload['sport_ids'], (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'The account could not be created.'));
        }
        return redirect()->back()->with('success', $this->roleLabel($role) . ' account added. Generated password: ' . $payload['generated_password']);
    }

    private function updateRole(int $id, string $role)
    {
        if ($role === 'facilitator' && ! $this->managerCanManageFacilitator($id)) {
            return redirect()->back()->with('error', 'You can only manage facilitators assigned within your sports.');
        }
        $payload = $this->userPayload($role, false);
        if (isset($payload['error'])) return redirect()->back()->with('error', $payload['error']);
        try {
            $this->repository()->updateUser($id, $payload['user'], $payload['sport_ids'], (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The account could not be updated.'));
        }
        return redirect()->back()->with('success', $this->roleLabel($role) . ' account updated.');
    }

    private function deleteRole(int $id, string $role)
    {
        if ($role === 'facilitator' && ! $this->managerCanManageFacilitator($id)) {
            return redirect()->back()->with('error', 'You can only manage facilitators assigned within your sports.');
        }
        $ids = array_map('intval', array_column($this->repository()->usersByRole($role), 'id'));
        if (! in_array($id, $ids, true)) return redirect()->back()->with('error', 'Account not found for this role.');
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

    private function managerCanManageFacilitator(int $id): bool
    {
        $managerSportIds = $this->managerSportIds();
        if ($managerSportIds === []) return false;
        foreach ($this->repository()->usersByRole('facilitator') as $user) {
            if ((int) ($user['id'] ?? 0) !== $id) continue;
            $userSportIds = array_map('intval', array_column($user['sports'] ?? [], 'id'));
            return $userSportIds !== [] && array_diff($userSportIds, $managerSportIds) === [];
        }
        return false;
    }

    private function roleLabel(string $role): string
    {
        return $role === 'manager' ? 'Tournament Manager' : ($role === 'facilitator' ? 'Facilitator' : 'User');
    }

    private function generatedPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $raw = random_bytes(10);
        $out = '';
        for ($i = 0; $i < 10; $i++) $out .= $alphabet[ord($raw[$i]) % strlen($alphabet)];
        return $out . '!7a';
    }

    private function postedSportIds(): array|string
    {
        $rawSportIds = $this->request->getPost('sport_ids');
        if ($rawSportIds !== null && ! is_array($rawSportIds)) {
            return 'Assigned sports are invalid.';
        }

        $sportIds = [];
        foreach (is_array($rawSportIds) ? $rawSportIds : [] as $rawSportId) {
            if (! is_scalar($rawSportId) || ! preg_match('/^[1-9]\d*$/', (string) $rawSportId)) {
                return 'Assigned sports are invalid.';
            }
            $sportIds[] = (int) $rawSportId;
        }

        return array_values(array_unique($sportIds));
    }

    private function userPayload(string $role, bool $creating): array
    {
        $actorRole = (string) session()->get('role');
        if (($actorRole === 'admin' && $role !== 'manager') || ($actorRole === 'manager' && $role !== 'facilitator')) {
            return ['error' => 'You are not allowed to create this account role.'];
        }

        $username = trim($this->postString('username'));
        $display = trim($this->postString('display_name'));
        $status = $this->postString('status') ?: 'active';
        if (mb_strlen($username) < 3 || mb_strlen($username) > 80 || $display === '' || mb_strlen($display) > 120 || ! preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            return ['error' => 'Enter a valid username and display name.'];
        }
        if (! in_array($status, ['active','inactive'], true)) return ['error' => 'Select a valid account status.'];

        $sportIds = $this->postedSportIds();
        if (is_string($sportIds)) return ['error' => $sportIds];
        if ($sportIds === []) {
            return ['error' => $role === 'manager'
                ? 'Tournament Managers require at least one sport.'
                : 'Facilitators require at least one assigned sport.'];
        }

        if ($role === 'facilitator') {
            $managerSportIds = $this->managerSportIds();
            if ($managerSportIds === [] || array_diff($sportIds, $managerSportIds) !== []) {
                return ['error' => 'Facilitator sports must be a non-empty subset of your assigned sports.'];
            }
        }

        $user = ['username' => $username, 'display_name' => $display, 'role' => $role, 'status' => $status];
        $generated = null;
        if ($creating) {
            $generated = $this->generatedPassword();
            $user['password_hash'] = password_hash($generated, PASSWORD_DEFAULT);
            $user['generated_password'] = base64_encode(service('encrypter')->encrypt($generated));
            $user['created_by'] = (int) session()->get('user_id');
            $user['created_at'] = date('Y-m-d H:i:s');
        }

        return ['user' => $user, 'sport_ids' => $sportIds, 'generated_password' => $generated];
    }
}
