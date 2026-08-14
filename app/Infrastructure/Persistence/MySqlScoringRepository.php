<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\ScoringRepositoryInterface;
use App\Models\EventModel;
use App\Models\NotificationModel;
use App\Models\ResultModel;
use App\Models\ScheduleModel;
use App\Models\SportModel;
use App\Models\TeamModel;
use App\Models\UserModel;
use App\Models\WeightedPointModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class MySqlScoringRepository implements ScoringRepositoryInterface
{
    private UserModel $users;
    private EventModel $eventsModel;
    private TeamModel $teamsModel;
    private SportModel $sportsModel;
    private ScheduleModel $schedulesModel;
    private WeightedPointModel $weightedPointsModel;
    private ResultModel $resultsModel;
    private NotificationModel $notificationsModel;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->users = new UserModel($this->db);
        $this->eventsModel = new EventModel($this->db);
        $this->teamsModel = new TeamModel($this->db);
        $this->sportsModel = new SportModel($this->db);
        $this->schedulesModel = new ScheduleModel($this->db);
        $this->weightedPointsModel = new WeightedPointModel($this->db);
        $this->resultsModel = new ResultModel($this->db);
        $this->notificationsModel = new NotificationModel($this->db);
    }

    public function findUserByUsername(string $username): ?array
    {
        return $this->users->where('username', $username)->where('status', 'active')->first() ?: null;
    }

    public function activeEvent(): ?array
    {
        return $this->eventsModel->where('is_active', 1)->orderBy('year', 'DESC')->first() ?: null;
    }

    public function events(): array
    {
        return $this->eventsModel->orderBy('year', 'DESC')->findAll();
    }

    public function teams(): array
    {
        return $this->teamsModel->orderBy('name')->findAll();
    }

    public function sports(?int $eventId = null): array
    {
        $builder = $this->db->table('sports s')
            ->select('s.*, e.name event_name')
            ->join('events e', 'e.id=s.event_id');
        if ($eventId) {
            $builder->where('s.event_id', $eventId);
        }
        return $builder->orderBy('s.category')->orderBy('s.name')->get()->getResultArray();
    }

    public function locations(): array
    {
        return $this->db->table('locations')->orderBy('name')->get()->getResultArray();
    }

    public function schedules(?int $eventId = null, ?string $resultType = null): array
    {
        $builder = $this->db->table('schedules sc')
            ->select('sc.*, s.name sport_name, s.category, s.result_type, l.name location_name, ta.name team_a_name, tb.name team_b_name')
            ->join('sports s', 's.id=sc.sport_id')
            ->join('locations l', 'l.id=sc.location_id', 'left')
            ->join('teams ta', 'ta.id=sc.team_a_id', 'left')
            ->join('teams tb', 'tb.id=sc.team_b_id', 'left');
        if ($eventId) {
            $builder->where('sc.event_id', $eventId);
        }
        if ($resultType) {
            $builder->where('s.result_type', $resultType);
        }
        return $builder->orderBy('sc.match_date', 'DESC')->get()->getResultArray();
    }

    public function usersByRole(string $role): array
    {
        $users = $this->db->table('users')->where('role', $role)->orderBy('display_name')->get()->getResultArray();
        foreach ($users as &$user) {
            $user['sports'] = $this->db->table('user_sports us')
                ->select('s.id,s.name,s.category')
                ->join('sports s', 's.id=us.sport_id')
                ->where('us.user_id', $user['id'])
                ->orderBy('s.name')
                ->get()->getResultArray();
        }
        return $users;
    }

    public function assignedSportIds(int $userId): array
    {
        return array_map(
            'intval',
            array_column(
                $this->db->table('user_sports')->select('sport_id')->where('user_id', $userId)->get()->getResultArray(),
                'sport_id'
            )
        );
    }

    public function notifications(int $limit = 30): array
    {
        return $this->db->table('notifications n')
            ->select('n.*, u.display_name actor_name')
            ->join('users u', 'u.id=n.actor_user_id', 'left')
            ->orderBy('n.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    public function weightedPoints(?int $eventId = null): array
    {
        $builder = $this->db->table('weighted_points wp')
            ->select('wp.*, s.name sport_name, s.category, u.display_name submitted_by_name, v.display_name validated_by_name')
            ->join('sports s', 's.id=wp.sport_id')
            ->join('users u', 'u.id=wp.submitted_by', 'left')
            ->join('users v', 'v.id=wp.validated_by', 'left');
        if ($eventId) {
            $builder->where('wp.event_id', $eventId);
        }
        return $builder->orderBy('s.name')->get()->getResultArray();
    }

    public function results(?int $eventId = null, ?string $type = null): array
    {
        $builder = $this->db->table('results r')
            ->select('r.*, sc.sport_id, sc.team_a_id, sc.team_b_id, sc.round, sc.match_date, s.name sport_name, s.category, s.result_type, l.name location_name, u.display_name submitted_by_name, v.display_name validated_by_name')
            ->join('schedules sc', 'sc.id=r.schedule_id')
            ->join('sports s', 's.id=sc.sport_id')
            ->join('locations l', 'l.id=sc.location_id', 'left')
            ->join('users u', 'u.id=r.submitted_by', 'left')
            ->join('users v', 'v.id=r.validated_by', 'left');
        if ($eventId) {
            $builder->where('r.event_id', $eventId);
        }
        if ($type) {
            $builder->where('r.type', $type);
        }
        $rows = $builder->orderBy('r.id', 'DESC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['entries'] = $this->resultEntries((int) $row['id']);
        }
        return $rows;
    }

    public function resultEntries(int $resultId): array
    {
        return $this->db->table('result_entries re')
            ->select('re.*, t.name team_name, t.code team_code')
            ->join('teams t', 't.id=re.team_id')
            ->where('re.result_id', $resultId)
            ->orderBy('re.placement')
            ->orderBy('re.id')
            ->get()->getResultArray();
    }

    public function ranking(?int $eventId = null): array
    {
        $eventId ??= (int) ($this->activeEvent()['id'] ?? 0);
        if (! $eventId) {
            return [];
        }
        return $this->db->table('teams t')
            ->select('t.id,t.name,t.code,COALESCE(SUM(CASE WHEN r.status="validated" THEN re.allocated_points ELSE 0 END),0) total_points, SUM(CASE WHEN r.status="validated" AND re.placement=1 THEN 1 ELSE 0 END) firsts, SUM(CASE WHEN r.status="validated" AND re.placement=2 THEN 1 ELSE 0 END) seconds, SUM(CASE WHEN r.status="validated" AND re.placement=3 THEN 1 ELSE 0 END) thirds')
            ->join('result_entries re', 're.team_id=t.id', 'left')
            ->join('results r', 'r.id=re.result_id AND r.event_id=' . $this->db->escape($eventId), 'left')
            ->groupBy('t.id,t.name,t.code')
            ->orderBy('total_points', 'DESC')
            ->orderBy('firsts', 'DESC')
            ->orderBy('seconds', 'DESC')
            ->get()->getResultArray();
    }

    public function reportSummary(?int $eventId = null): array
    {
        $eventId ??= (int) ($this->activeEvent()['id'] ?? 0);
        return [
            'event' => $this->activeEvent(),
            'ranking' => $this->ranking($eventId),
            'official_results' => $eventId ? $this->db->table('results')->where(['event_id' => $eventId, 'status' => 'validated'])->countAllResults() : 0,
            'unofficial_results' => $eventId ? $this->db->table('results')->where(['event_id' => $eventId, 'status' => 'pending'])->countAllResults() : 0,
            'sports' => $eventId ? $this->db->table('sports')->where('event_id', $eventId)->countAllResults() : 0,
        ];
    }

    public function createTeam(array $data, int $actorId): int
    {
        $this->teamsModel->insert($data);
        $id = (int) $this->teamsModel->getInsertID();
        $this->notify($actorId, 'team_created', 'Added team ' . $data['name']);
        return $id;
    }

    public function updateTeam(int $id, array $data, int $actorId): void
    {
        $team = $this->requireRow('teams', $id, 'Team');
        $this->teamsModel->update($id, $data);
        $this->notify($actorId, 'team_updated', 'Updated team ' . ($team['name'] ?? '#'.$id) . ' to ' . $data['name']);
    }

    public function deleteTeam(int $id, int $actorId): void
    {
        $team = $this->requireRow('teams', $id, 'Team');
        $inUse = $this->db->table('schedules')->groupStart()->where('team_a_id', $id)->orWhere('team_b_id', $id)->groupEnd()->countAllResults();
        $inUse += $this->db->table('result_entries')->where('team_id', $id)->countAllResults();
        if ($inUse > 0) {
            throw new RuntimeException('Team cannot be removed while it is used by schedules or results.');
        }
        $this->teamsModel->delete($id);
        $this->notify($actorId, 'team_deleted', 'Removed team ' . ($team['name'] ?? '#'.$id));
    }

    public function createEvent(array $data, int $actorId): int
    {
        $this->db->transStart();
        if (! empty($data['is_active'])) {
            $this->db->table('events')->set('is_active', 0)->update();
        }
        $this->eventsModel->insert($data);
        $id = (int) $this->eventsModel->getInsertID();
        $this->notify($actorId, 'event_created', 'Added event ' . $data['name']);
        $this->finishTransaction();
        return $id;
    }

    public function updateEvent(int $id, array $data, int $actorId): void
    {
        $event = $this->requireRow('events', $id, 'Event');
        $this->db->transStart();
        if (! empty($data['is_active'])) {
            $this->db->table('events')->set('is_active', 0)->update();
        } elseif ((int) ($event['is_active'] ?? 0) === 1) {
            $data['is_active'] = 1;
        }
        $this->eventsModel->update($id, $data);
        $this->notify($actorId, 'event_updated', 'Updated event ' . ($event['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function activateEvent(int $eventId, int $actorId): void
    {
        $event = $this->requireRow('events', $eventId, 'Event');
        $this->db->transStart();
        $this->db->table('events')->set('is_active', 0)->update();
        $this->eventsModel->update($eventId, ['is_active' => 1, 'status' => 'active']);
        $this->notify($actorId, 'event_activated', 'Activated event ' . ($event['name'] ?? '#'.$eventId));
        $this->finishTransaction();
    }

    public function deleteEvent(int $id, int $actorId): void
    {
        $event = $this->requireRow('events', $id, 'Event');
        if ((int) ($event['is_active'] ?? 0) === 1) {
            throw new RuntimeException('Activate another event before deleting the active event.');
        }
        $this->eventsModel->delete($id);
        $this->notify($actorId, 'event_deleted', 'Removed event ' . ($event['name'] ?? '#'.$id));
    }

    public function createSport(array $data, int $actorId): int
    {
        $this->sportsModel->insert($data);
        $id = (int) $this->sportsModel->getInsertID();
        $this->notify($actorId, 'sport_created', 'Added sport ' . $data['name'] . ' (' . $data['category'] . ')');
        return $id;
    }

    public function updateSport(int $id, array $data, int $actorId): void
    {
        $sport = $this->requireRow('sports', $id, 'Sport');
        if ((int) ($sport['event_id'] ?? 0) !== (int) ($data['event_id'] ?? 0)) {
            throw new RuntimeException('Sport does not belong to the active event.');
        }
        if (($sport['result_type'] ?? '') !== $data['result_type'] && $this->db->table('schedules')->where('sport_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('Sport type cannot be changed after schedules have been created.');
        }
        $this->sportsModel->update($id, $data);
        $this->notify($actorId, 'sport_updated', 'Updated sport ' . ($sport['name'] ?? '#'.$id));
    }

    public function deleteSport(int $id, int $actorId): void
    {
        $sport = $this->requireRow('sports', $id, 'Sport');
        if ($this->db->table('schedules')->where('sport_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('Sport cannot be removed while schedules or results exist for it.');
        }
        $this->sportsModel->delete($id);
        $this->notify($actorId, 'sport_deleted', 'Removed sport ' . ($sport['name'] ?? '#'.$id));
    }

    public function createSchedule(array $data, int $actorId): int
    {
        $this->assertSchedulePayload($data);
        $this->schedulesModel->insert($data);
        $id = (int) $this->schedulesModel->getInsertID();
        $this->notify($actorId, 'schedule_created', 'Added a schedule for ' . date('M j, Y g:i A', strtotime($data['match_date'])));
        return $id;
    }

    public function updateSchedule(int $id, array $data, int $actorId): void
    {
        $schedule = $this->requireRow('schedules', $id, 'Schedule');
        if ((int) ($schedule['event_id'] ?? 0) !== (int) ($data['event_id'] ?? 0)) {
            throw new RuntimeException('Schedule does not belong to the active event.');
        }
        if ($this->db->table('results')->where('schedule_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('A schedule with submitted results cannot be edited.');
        }
        $this->assertSchedulePayload($data);
        $this->schedulesModel->update($id, $data);
        $this->notify($actorId, 'schedule_updated', 'Updated schedule #' . ($schedule['id'] ?? $id));
    }

    public function deleteSchedule(int $id, int $actorId): void
    {
        $schedule = $this->requireRow('schedules', $id, 'Schedule');
        if ($this->db->table('results')->where('schedule_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('A schedule with submitted results cannot be deleted.');
        }
        $this->schedulesModel->delete($id);
        $this->notify($actorId, 'schedule_deleted', 'Removed schedule #' . ($schedule['id'] ?? $id));
    }

    public function createUser(array $data, array $sportIds, int $actorId): int
    {
        $this->db->transStart();
        $this->db->table('users')->insert($data);
        $id = (int) $this->db->insertID();
        $this->syncUserSports($id, $sportIds);
        $this->notify($actorId, 'user_created', 'Added ' . $data['role'] . ' account for ' . $data['display_name']);
        $this->finishTransaction();
        return $id;
    }

    public function updateUser(int $id, array $data, array $sportIds, int $actorId): void
    {
        $user = $this->requireRow('users', $id, 'User');
        if (($user['role'] ?? '') !== ($data['role'] ?? $user['role'])) {
            throw new RuntimeException('Account role cannot be changed from this page.');
        }
        $this->db->transStart();
        $this->db->table('users')->where('id', $id)->update($data);
        $this->syncUserSports($id, $sportIds);
        $this->notify($actorId, 'user_updated', 'Updated account for ' . ($data['display_name'] ?? $user['display_name']));
        $this->finishTransaction();
    }

    public function deleteUser(int $id, int $actorId): void
    {
        $user = $this->requireRow('users', $id, 'User');
        if ($id === $actorId) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        $this->db->table('users')->where('id', $id)->delete();
        $this->notify($actorId, 'user_deleted', 'Removed account for ' . ($user['display_name'] ?? '#'.$id));
    }

    public function saveWeightedPoints(array $data, int $actorId): int
    {
        $sport = $this->requireRow('sports', (int) ($data['sport_id'] ?? 0), 'Sport');
        if ((int) ($sport['event_id'] ?? 0) !== (int) ($data['event_id'] ?? 0)) {
            throw new RuntimeException('Selected sport does not belong to the active event.');
        }
        $this->assertActiveEvent((int) $data['event_id']);
        $existing = $this->db->table('weighted_points')
            ->where(['event_id' => $data['event_id'], 'sport_id' => $data['sport_id']])
            ->get()->getRowArray();
        if ($existing) {
            $this->updateWeightedPoints((int) $existing['id'], $data, $actorId);
            return (int) $existing['id'];
        }
        $payload = $data + ['status' => 'pending', 'submitted_by' => $actorId, 'validated_by' => null, 'validated_at' => null];
        $this->db->table('weighted_points')->insert($payload);
        $id = (int) $this->db->insertID();
        $this->notify($actorId, 'weighted_points_submitted', 'Submitted weighted points for validator approval');
        return $id;
    }

    public function updateWeightedPoints(int $id, array $data, int $actorId): void
    {
        $points = $this->requireRow('weighted_points', $id, 'Weighted points');
        if ((int) ($points['event_id'] ?? 0) !== (int) $data['event_id']) {
            throw new RuntimeException('Weighted points do not belong to the active event.');
        }
        $this->assertActiveEvent((int) $points['event_id']);
        if ((int) ($points['sport_id'] ?? 0) !== (int) ($data['sport_id'] ?? 0)) {
            throw new RuntimeException('The sport cannot be changed on an existing weighted-points record.');
        }
        $payload = $data + [];
        $payload['status'] = 'pending';
        $payload['submitted_by'] = $actorId;
        $payload['submitted_at'] = date('Y-m-d H:i:s');
        $payload['validated_by'] = null;
        $payload['validated_at'] = null;
        $this->db->table('weighted_points')->where('id', $id)->update($payload);
        $this->notify($actorId, 'weighted_points_updated', 'Updated weighted points and returned them to pending validation');
    }

    public function validateWeightedPoints(int $id, int $actorId): void
    {
        $points = $this->requireRow('weighted_points', $id, 'Weighted points');
        $this->assertActiveEvent((int) $points['event_id']);
        $this->db->table('weighted_points')->where('id', $id)->update([
            'status' => 'validated',
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notify($actorId, 'weighted_points_validated', 'Validated weighted points configuration');
    }

    public function deleteWeightedPoints(int $id, int $actorId): void
    {
        $points = $this->requireRow('weighted_points', $id, 'Weighted points');
        $this->assertActiveEvent((int) $points['event_id']);
        $results = $this->db->table('results r')
            ->join('schedules sc', 'sc.id=r.schedule_id')
            ->where('r.event_id', $points['event_id'])
            ->where('sc.sport_id', $points['sport_id'])
            ->countAllResults();
        if ($results > 0) {
            throw new RuntimeException('Weighted points cannot be deleted after results exist for the sport.');
        }
        $this->db->table('weighted_points')->where('id', $id)->delete();
        $this->notify($actorId, 'weighted_points_deleted', 'Removed weighted points configuration');
    }

    public function createResult(array $data, int $actorId): int
    {
        $schedule = $this->resultSchedule((int) ($data['schedule_id'] ?? 0));
        $this->assertActiveEvent((int) $schedule['event_id']);
        if (($schedule['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Cancelled schedules cannot receive results.');
        }
        $this->assertActorCanManageResult($schedule, $actorId);
        if ($this->db->table('results')->where('schedule_id', $schedule['id'])->countAllResults() > 0) {
            throw new RuntimeException('A result has already been submitted for this schedule. Edit the pending result instead.');
        }
        $this->assertWeightedPointsReady($schedule);
        $entries = $this->normaliseResultEntries($schedule, $data);

        $this->db->transStart();
        $this->db->table('results')->insert([
            'event_id' => $schedule['event_id'],
            'schedule_id' => $schedule['id'],
            'type' => $schedule['result_type'],
            'status' => 'pending',
            'notes' => trim((string) ($data['notes'] ?? '')),
            'submitted_by' => $actorId,
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        $this->replaceResultEntries($id, $entries);
        $this->db->table('schedules')->where('id', $schedule['id'])->update(['status' => 'played']);
        $this->notify($actorId, 'result_submitted', 'Submitted unofficial ' . $schedule['result_type'] . ' result for ' . $schedule['sport_name']);
        $this->finishTransaction();
        return $id;
    }

    public function updateResult(int $id, array $data, int $actorId): void
    {
        $result = $this->resultWithSchedule($id);
        $this->assertActiveEvent((int) $result['event_id']);
        if (($result['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Official validated results cannot be edited.');
        }
        $this->assertActorCanManageResult($result, $actorId, (int) ($result['submitted_by'] ?? 0));
        $this->assertWeightedPointsReady($result);
        $entries = $this->normaliseResultEntries($result, $data);

        $this->db->transStart();
        $this->db->table('results')->where('id', $id)->update([
            'notes' => trim((string) ($data['notes'] ?? '')),
            'validated_by' => null,
            'validated_at' => null,
        ]);
        $this->db->table('result_entries')->where('result_id', $id)->delete();
        $this->replaceResultEntries($id, $entries);
        $this->notify($actorId, 'result_updated', 'Updated unofficial result #' . $id);
        $this->finishTransaction();
    }

    public function validateResult(int $id, int $actorId): void
    {
        $result = $this->resultWithSchedule($id);
        $this->assertActiveEvent((int) $result['event_id']);
        if (($result['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Result has already been validated.');
        }
        $weightedPoints = $this->db->table('weighted_points')->where([
            'event_id' => $result['event_id'],
            'sport_id' => $result['sport_id'],
            'status' => 'validated',
        ])->get()->getRowArray();
        if (! $weightedPoints) {
            throw new RuntimeException('Weighted points must be validated first.');
        }

        $entries = $this->resultEntries($id);
        if ($result['result_type'] === 'match') {
            usort($entries, static fn(array $x, array $y): int => (float) $y['raw_score'] <=> (float) $x['raw_score']);
            $round = strtolower((string) $result['round']);
            foreach ($entries as $i => $entry) {
                $placement = null;
                $points = 0.0;
                if (str_contains($round, 'final') && ! str_contains($round, 'semi')) {
                    $placement = $i + 1;
                } elseif (str_contains($round, 'bronze') || str_contains($round, '3rd')) {
                    $placement = $i === 0 ? 3 : 4;
                }
                if ($placement) {
                    $points = $this->pointsForPlacement($weightedPoints, $placement);
                }
                $this->db->table('result_entries')->where('id', $entry['id'])->update([
                    'placement' => $placement,
                    'allocated_points' => $points,
                ]);
            }
        } else {
            foreach ($entries as $entry) {
                $placement = (int) $entry['placement'];
                $this->db->table('result_entries')->where('id', $entry['id'])->update([
                    'allocated_points' => $this->pointsForPlacement($weightedPoints, $placement),
                ]);
            }
        }
        $this->db->table('results')->where('id', $id)->update([
            'status' => 'validated',
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notify($actorId, 'result_validated', 'Validated result #' . $id . ' as official');
    }

    public function deleteResult(int $id, int $actorId): void
    {
        $result = $this->resultWithSchedule($id);
        $this->assertActiveEvent((int) $result['event_id']);
        if (($result['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Official validated results cannot be deleted.');
        }
        $this->assertActorCanManageResult($result, $actorId, (int) ($result['submitted_by'] ?? 0));
        $scheduleId = (int) $result['schedule_id'];
        $this->db->transStart();
        $this->db->table('results')->where('id', $id)->delete();
        if ($this->db->table('results')->where('schedule_id', $scheduleId)->countAllResults() === 0) {
            $this->db->table('schedules')->where('id', $scheduleId)->update(['status' => 'scheduled']);
        }
        $this->notify($actorId, 'result_deleted', 'Removed unofficial result #' . $id);
        $this->finishTransaction();
    }

    public function updateUserSettings(int $userId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $existing = $this->db->table('user_settings')->where(['user_id' => $userId, 'setting_key' => $key])->get()->getRowArray();
            if ($existing) {
                $this->db->table('user_settings')->where('id', $existing['id'])->update(['setting_value' => (string) $value]);
            } else {
                $this->db->table('user_settings')->insert(['user_id' => $userId, 'setting_key' => $key, 'setting_value' => (string) $value]);
            }
        }
    }

    public function getUserSettings(int $userId): array
    {
        $rows = $this->db->table('user_settings')->where('user_id', $userId)->get()->getResultArray();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    private function requireRow(string $table, int $id, string $label): array
    {
        $row = $this->db->table($table)->where('id', $id)->get()->getRowArray();
        if (! $row) {
            throw new RuntimeException($label . ' not found.');
        }
        return $row;
    }

    private function assertActiveEvent(int $eventId): void
    {
        $active = $this->activeEvent();
        if (! $active || (int) $active['id'] !== $eventId) {
            throw new RuntimeException('This operation is only allowed for the active event.');
        }
    }

    private function assertSchedulePayload(array $data): void
    {
        $sport = $this->requireRow('sports', (int) ($data['sport_id'] ?? 0), 'Sport');
        $this->requireRow('locations', (int) ($data['location_id'] ?? 0), 'Location');
        if ((int) $sport['event_id'] !== (int) ($data['event_id'] ?? 0)) {
            throw new RuntimeException('Selected sport does not belong to the active event.');
        }
        $teamA = (int) ($data['team_a_id'] ?? 0);
        $teamB = (int) ($data['team_b_id'] ?? 0);
        if ($teamA && $teamB && $teamA === $teamB) {
            throw new RuntimeException('Team A and Team B must be different.');
        }
        if (($sport['result_type'] ?? '') === 'match' && (! $teamA || ! $teamB)) {
            throw new RuntimeException('Match schedules require both Team A and Team B.');
        }
        if ($teamA) {
            $this->requireRow('teams', $teamA, 'Team A');
        }
        if ($teamB) {
            $this->requireRow('teams', $teamB, 'Team B');
        }
    }

    private function syncUserSports(int $userId, array $sportIds): void
    {
        $this->db->table('user_sports')->where('user_id', $userId)->delete();
        foreach (array_unique(array_filter(array_map('intval', $sportIds))) as $sportId) {
            $sport = $this->requireRow('sports', $sportId, 'Assigned sport');
            $this->assertActiveEvent((int) $sport['event_id']);
            $this->db->table('user_sports')->insert(['user_id' => $userId, 'sport_id' => $sportId]);
        }
    }

    private function resultSchedule(int $scheduleId): array
    {
        $schedule = $this->db->table('schedules sc')
            ->select('sc.*, s.result_type, s.name sport_name')
            ->join('sports s', 's.id=sc.sport_id')
            ->where('sc.id', $scheduleId)
            ->get()->getRowArray();
        if (! $schedule) {
            throw new RuntimeException('Schedule not found.');
        }
        return $schedule;
    }

    private function resultWithSchedule(int $resultId): array
    {
        $result = $this->db->table('results r')
            ->select('r.*, sc.sport_id, sc.team_a_id, sc.team_b_id, sc.round, sc.match_date, s.result_type, s.name sport_name')
            ->join('schedules sc', 'sc.id=r.schedule_id')
            ->join('sports s', 's.id=sc.sport_id')
            ->where('r.id', $resultId)
            ->get()->getRowArray();
        if (! $result) {
            throw new RuntimeException('Result not found.');
        }
        return $result;
    }

    private function assertActorCanManageResult(array $scheduleOrResult, int $actorId, ?int $submittedBy = null): void
    {
        $actor = $this->requireRow('users', $actorId, 'User');
        $role = (string) ($actor['role'] ?? '');
        if ($role === 'manager') {
            return;
        }
        if ($role !== 'facilitator') {
            throw new RuntimeException('You are not allowed to manage results.');
        }
        if (! in_array((int) $scheduleOrResult['sport_id'], $this->assignedSportIds($actorId), true)) {
            throw new RuntimeException('You are not assigned to score this sport.');
        }
        if ($submittedBy !== null && $submittedBy !== 0 && $submittedBy !== $actorId) {
            throw new RuntimeException('Facilitators can only edit or delete their own submissions.');
        }
    }

    private function assertWeightedPointsReady(array $scheduleOrResult): void
    {
        $weightedPoints = $this->db->table('weighted_points')->where([
            'event_id' => $scheduleOrResult['event_id'],
            'sport_id' => $scheduleOrResult['sport_id'],
            'status' => 'validated',
        ])->get()->getRowArray();
        if (! $weightedPoints) {
            throw new RuntimeException('Validated weighted points are required before scores can be submitted.');
        }
    }

    private function normaliseResultEntries(array $scheduleOrResult, array $data): array
    {
        if (($scheduleOrResult['result_type'] ?? '') === 'match') {
            if (! $scheduleOrResult['team_a_id'] || ! $scheduleOrResult['team_b_id']) {
                throw new RuntimeException('Match schedules require Team A and Team B.');
            }
            $scoreA = filter_var($data['team_a_score'] ?? null, FILTER_VALIDATE_FLOAT);
            $scoreB = filter_var($data['team_b_score'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($scoreA === false || $scoreB === false || $scoreA < 0 || $scoreB < 0) {
                throw new RuntimeException('Enter valid non-negative scores for both teams.');
            }
            return [
                ['team_id' => (int) $scheduleOrResult['team_a_id'], 'raw_score' => (float) $scoreA, 'placement' => null],
                ['team_id' => (int) $scheduleOrResult['team_b_id'], 'raw_score' => (float) $scoreB, 'placement' => null],
            ];
        }

        $entries = [];
        foreach (($data['judged'] ?? []) as $teamId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }
            $teamId = (int) $teamId;
            $value = filter_var($score, FILTER_VALIDATE_FLOAT);
            if (! $teamId || $value === false || $value < 0) {
                throw new RuntimeException('Judged scores must be valid non-negative numbers.');
            }
            $this->requireRow('teams', $teamId, 'Team');
            $entries[] = ['team_id' => $teamId, 'raw_score' => (float) $value];
        }
        if (! $entries) {
            throw new RuntimeException('Enter at least one judged score.');
        }
        usort($entries, static fn(array $x, array $y): int => $y['raw_score'] <=> $x['raw_score']);
        foreach ($entries as $index => &$entry) {
            $entry['placement'] = $index + 1;
        }
        unset($entry);
        return $entries;
    }

    private function replaceResultEntries(int $resultId, array $entries): void
    {
        foreach ($entries as $entry) {
            $this->db->table('result_entries')->insert([
                'result_id' => $resultId,
                'team_id' => $entry['team_id'],
                'raw_score' => $entry['raw_score'],
                'placement' => $entry['placement'] ?? null,
                'allocated_points' => 0,
            ]);
        }
    }

    private function pointsForPlacement(array $weightedPoints, int $placement): float
    {
        return match ($placement) {
            1 => (float) $weightedPoints['first_points'],
            2 => (float) $weightedPoints['second_points'],
            3 => (float) $weightedPoints['third_points'],
            default => (float) $weightedPoints['participation_points'],
        };
    }

    private function notify(int $actorId, string $action, string $message): void
    {
        $this->notificationsModel->insert([
            'actor_user_id' => $actorId,
            'action' => $action,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function finishTransaction(): void
    {
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new RuntimeException('The database operation could not be completed.');
        }
    }
}
