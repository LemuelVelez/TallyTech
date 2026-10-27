<?php
namespace App\Controllers;

class UsersController extends BaseController
{
    public function index()
    {
        return $this->tournamentManagers();
    }

    public function sportsCoordinators()
    {
        $repo = $this->repository();
        $event = $repo->activeEvent();

        return view('users/index', [
            'title' => 'Sports Coordinators',
            'manageMode' => 'sports_coordinator',
            'roleType' => 'sports_coordinator',
            'roleOptions' => ['sports_coordinator'],
            'users' => $repo->usersByRole('sports_coordinator'),
            'sports' => $repo->sports((int) ($event['id'] ?? 0)),
            'activeEvent' => $event,
        ]);
    }


    public function store()
    {
        return $this->storeRole($this->managedRoleForActor());
    }

    public function update(int $id)
    {
        return $this->updateRole($id, $this->managedRoleForActor());
    }

    public function delete(int $id)
    {
        return $this->deleteRole($id, $this->managedRoleForActor());
    }

    public function storeSportsCoordinator(){return $this->storeRole('sports_coordinator');}
    public function storeTournamentManager(){return $this->storeRole('tournament_manager');}
    public function updateSportsCoordinator(int $id){return $this->updateRole($id,'sports_coordinator');}
    public function updateTournamentManager(int $id){return $this->updateRole($id,'tournament_manager');}
    public function deleteSportsCoordinator(int $id){return $this->deleteRole($id,'sports_coordinator');}
    public function deleteTournamentManager(int $id){return $this->deleteRole($id,'tournament_manager');}

    public function resetPassword(int $id)
    {
        if ((string) session()->get('role') === 'sports_coordinator' && ! $this->coordinatorCanManageTournamentManager($id)) {
            return redirect()->back()->with('error', 'You can only manage Tournament Managers assigned within your sports.');
        }
        try {
            $password = $this->repository()->resetGeneratedPassword($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'Password could not be reset.'));
        }
        return redirect()->back()->with('success', 'New generated password: ' . $password);
    }

    private function tournamentManagers()
    {
        $repo = $this->repository();
        $event = $repo->activeEvent();
        $coordinatorSportIds = $this->coordinatorSportIds();
        $users = array_values(array_filter($repo->usersByRole('tournament_manager'), static function (array $user) use ($coordinatorSportIds): bool {
            $userSportIds = array_map('intval', array_column($user['sports'] ?? [], 'id'));
            return $userSportIds !== [] && array_diff($userSportIds, $coordinatorSportIds) === [];
        }));
        $sports = array_values(array_filter(
            $repo->sports((int) ($event['id'] ?? 0)),
            static fn(array $sport): bool => in_array((int) $sport['id'], $coordinatorSportIds, true)
        ));

        return view('users/index', [
            'title' => 'User Management',
            'manageMode' => 'tournament_manager',
            'roleType' => 'tournament_manager',
            'roleOptions' => ['tournament_manager'],
            'users' => $users,
            'sports' => $sports,
            'activeEvent' => $event,
        ]);
    }

    private function managedRoleForActor(): string
    {
        return (string) session()->get('role') === 'admin' ? 'sports_coordinator' : 'tournament_manager';
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
        return redirect()->back()->with('success', role_label($role) . ' account added. Generated password: ' . $payload['generated_password']);
    }

    private function updateRole(int $id, string $role)
    {
        if ($role === 'tournament_manager' && ! $this->coordinatorCanManageTournamentManager($id)) {
            return redirect()->back()->with('error', 'You can only manage Tournament Managers assigned within your sports.');
        }
        $payload = $this->userPayload($role, false);
        if (isset($payload['error'])) return redirect()->back()->with('error', $payload['error']);
        try {
            $this->repository()->updateUser($id, $payload['user'], $payload['sport_ids'], (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The account could not be updated.'));
        }
        return redirect()->back()->with('success', role_label($role) . ' account updated.');
    }

    private function deleteRole(int $id, string $role)
    {
        if ($role === 'tournament_manager' && ! $this->coordinatorCanManageTournamentManager($id)) {
            return redirect()->back()->with('error', 'You can only manage Tournament Managers assigned within your sports.');
        }
        $ids = array_map('intval', array_column($this->repository()->usersByRole($role), 'id'));
        if (! in_array($id, $ids, true)) return redirect()->back()->with('error', 'Account not found for this role.');
        return $this->deleteManagedUser($id, role_label($role));
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
        if (($actorRole === 'admin' && $role !== 'sports_coordinator') || ($actorRole === 'sports_coordinator' && $role !== 'tournament_manager')) {
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
            return ['error' => $role === 'sports_coordinator'
                ? 'Sports Coordinators require at least one sport.'
                : 'Tournament Managers require at least one assigned sport.'];
        }

        if ($role === 'tournament_manager') {
            $coordinatorSportIds = $this->coordinatorSportIds();
            if ($coordinatorSportIds === [] || array_diff($sportIds, $coordinatorSportIds) !== []) {
                return ['error' => 'Tournament Manager sports must be a non-empty subset of your assigned sports.'];
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
