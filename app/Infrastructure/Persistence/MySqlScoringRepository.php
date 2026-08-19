<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\ScoringRepositoryInterface;
use App\Models\EventModel;
use App\Models\LocationModel;
use App\Models\NotificationModel;
use App\Models\ResultModel;
use App\Models\ScheduleModel;
use App\Models\SportCategoryModel;
use App\Models\SportModel;
use App\Models\TeamModel;
use App\Models\UserModel;
use App\Models\WeightedPointModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class MySqlScoringRepository implements ScoringRepositoryInterface
{
    private const MAX_RESULT_SCORE = 99999999.99;
    private UserModel $users;
    private EventModel $eventsModel;
    private TeamModel $teamsModel;
    private LocationModel $locationsModel;
    private SportCategoryModel $sportCategoriesModel;
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
        $this->locationsModel = new LocationModel($this->db);
        $this->sportCategoriesModel = new SportCategoryModel($this->db);
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
            ->select('s.*, e.name event_name, c.id category_id')
            ->join('events e', 'e.id=s.event_id')
            ->join('sport_categories c', 'c.name=s.category', 'left');
        if ($eventId !== null) {
            $builder->where('s.event_id', $eventId);
        }
        return $builder->orderBy('s.category')->orderBy('s.name')->get()->getResultArray();
    }

    public function locations(): array
    {
        return $this->locationsModel->where('is_active', 1)->orderBy('name')->findAll();
    }

    public function allLocations(): array
    {
        return $this->db->table('locations l')
            ->select('l.*, COUNT(sc.id) schedule_count')
            ->join('schedules sc', 'sc.location_id=l.id', 'left')
            ->groupBy('l.id,l.name,l.is_active,l.created_at,l.updated_at')
            ->orderBy('l.name')
            ->get()
            ->getResultArray();
    }

    public function sportCategories(bool $includeInactive = false): array
    {
        $builder = $this->db->table('sport_categories c')
            ->select('c.*, COUNT(s.id) sport_count')
            ->join('sports s', 's.category=c.name', 'left')
            ->groupBy('c.id,c.name,c.is_active,c.created_at,c.updated_at');
        if (! $includeInactive) {
            $builder->where('c.is_active', 1);
        }
        return $builder->orderBy('c.name')->get()->getResultArray();
    }

    public function sportCategory(int $id): ?array
    {
        return $this->sportCategoriesModel->find($id) ?: null;
    }

    public function schedules(?int $eventId = null, ?string $resultType = null): array
    {
        $builder = $this->db->table('schedules sc')
            ->select('sc.*, s.name sport_name, s.category, s.result_type, l.name location_name, ta.name team_a_name, tb.name team_b_name')
            ->join('sports s', 's.id=sc.sport_id')
            ->join('locations l', 'l.id=sc.location_id', 'left')
            ->join('teams ta', 'ta.id=sc.team_a_id', 'left')
            ->join('teams tb', 'tb.id=sc.team_b_id', 'left');
        if ($eventId !== null) {
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
                ->join('events e', 'e.id=s.event_id')
                ->where('us.user_id', $user['id'])
                ->where('e.is_active', 1)
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
                $this->db->table('user_sports us')
                    ->select('us.sport_id')
                    ->join('sports s', 's.id=us.sport_id')
                    ->join('events e', 'e.id=s.event_id')
                    ->where('us.user_id', $userId)
                    ->where('e.is_active', 1)
                    ->get()->getResultArray(),
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
        if ($eventId !== null) {
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
        if ($eventId !== null) {
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
            ->orderBy('t.name', 'ASC')
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
        $this->db->transStart();
        $this->teamsModel->insert($data);
        $id = (int) $this->teamsModel->getInsertID();
        $this->notify($actorId, 'team_created', 'Added team ' . $data['name']);
        $this->finishTransaction();
        return $id;
    }

    public function updateTeam(int $id, array $data, int $actorId): void
    {
        $team = $this->requireRow('teams', $id, 'Team');
        $this->db->transStart();
        $this->teamsModel->update($id, $data);
        $this->notify($actorId, 'team_updated', 'Updated team ' . ($team['name'] ?? '#'.$id) . ' to ' . $data['name']);
        $this->finishTransaction();
    }

    public function deleteTeam(int $id, int $actorId): void
    {
        $team = $this->requireRow('teams', $id, 'Team');
        $inUse = $this->db->table('schedules')->groupStart()->where('team_a_id', $id)->orWhere('team_b_id', $id)->groupEnd()->countAllResults();
        $inUse += $this->db->table('result_entries')->where('team_id', $id)->countAllResults();
        if ($inUse > 0) {
            throw new RuntimeException('Team cannot be removed while it is used by schedules or results.');
        }
        $this->db->transStart();
        $this->teamsModel->delete($id);
        $this->notify($actorId, 'team_deleted', 'Removed team ' . ($team['name'] ?? '#'.$id));
        $this->finishTransaction();
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
            $data['status'] = 'active';
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

        $dependencies = $this->db->table('sports')->where('event_id', $id)->countAllResults();
        $dependencies += $this->db->table('schedules')->where('event_id', $id)->countAllResults();
        $dependencies += $this->db->table('weighted_points')->where('event_id', $id)->countAllResults();
        $dependencies += $this->db->table('results')->where('event_id', $id)->countAllResults();
        if ($dependencies > 0) {
            throw new RuntimeException('Event cannot be deleted while sports, schedules, weighted points, or results are linked to it.');
        }

        $this->db->transStart();
        $this->eventsModel->delete($id);
        $this->notify($actorId, 'event_deleted', 'Removed event ' . ($event['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function createLocation(string $name, int $actorId): int
    {
        $name = $this->normaliseReferenceName($name, 150, 'Location');
        $this->assertUniqueReferenceName('locations', $name);
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();
        $this->locationsModel->insert([
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->locationsModel->getInsertID();
        $this->notify($actorId, 'location_created', 'Added location ' . $name);
        $this->finishTransaction();
        return $id;
    }

    public function updateLocation(int $id, string $name, int $actorId): void
    {
        $location = $this->requireRow('locations', $id, 'Location');
        $name = $this->normaliseReferenceName($name, 150, 'Location');
        $this->assertUniqueReferenceName('locations', $name, $id);

        $this->db->transStart();
        $this->locationsModel->update($id, ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->notify($actorId, 'location_updated', 'Updated location ' . ($location['name'] ?? '#'.$id) . ' to ' . $name);
        $this->finishTransaction();
    }

    public function setLocationActive(int $id, bool $isActive, int $actorId): void
    {
        $location = $this->requireRow('locations', $id, 'Location');
        $this->db->transStart();
        $this->locationsModel->update($id, [
            'is_active' => $isActive ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notify($actorId, $isActive ? 'location_enabled' : 'location_disabled', ($isActive ? 'Enabled location ' : 'Disabled location ') . ($location['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function deleteLocation(int $id, int $actorId): void
    {
        $location = $this->requireRow('locations', $id, 'Location');
        if ($this->db->table('schedules')->where('location_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('Location cannot be removed while schedules reference it. Disable it instead.');
        }

        $this->db->transStart();
        $this->locationsModel->delete($id);
        $this->notify($actorId, 'location_deleted', 'Removed location ' . ($location['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function createSportCategory(string $name, int $actorId): int
    {
        $name = $this->normaliseReferenceName($name, 80, 'Sport category');
        $this->assertUniqueReferenceName('sport_categories', $name);
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();
        $this->sportCategoriesModel->insert([
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->sportCategoriesModel->getInsertID();
        $this->notify($actorId, 'sport_category_created', 'Added sport category ' . $name);
        $this->finishTransaction();
        return $id;
    }

    public function updateSportCategory(int $id, string $name, int $actorId): void
    {
        $category = $this->requireRow('sport_categories', $id, 'Sport category');
        $name = $this->normaliseReferenceName($name, 80, 'Sport category');
        $this->assertUniqueReferenceName('sport_categories', $name, $id);
        $oldName = (string) $category['name'];

        if ($oldName !== $name) {
            $sports = $this->db->table('sports')->select('id,event_id,name')->where('category', $oldName)->get()->getResultArray();
            foreach ($sports as $sport) {
                $duplicate = $this->db->table('sports')
                    ->where('event_id', $sport['event_id'])
                    ->where('name', $sport['name'])
                    ->where('category', $name)
                    ->where('id !=', $sport['id'])
                    ->countAllResults();
                if ($duplicate > 0) {
                    throw new RuntimeException('Category cannot be renamed because it would create a duplicate sport in an event.');
                }
            }
        }

        $this->db->transStart();
        $this->sportCategoriesModel->update($id, ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($oldName !== $name) {
            $this->db->table('sports')->where('category', $oldName)->update(['category' => $name]);
        }
        $this->notify($actorId, 'sport_category_updated', 'Updated sport category ' . $oldName . ' to ' . $name);
        $this->finishTransaction();
    }

    public function setSportCategoryActive(int $id, bool $isActive, int $actorId): void
    {
        $category = $this->requireRow('sport_categories', $id, 'Sport category');
        $this->db->transStart();
        $this->sportCategoriesModel->update($id, [
            'is_active' => $isActive ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notify($actorId, $isActive ? 'sport_category_enabled' : 'sport_category_disabled', ($isActive ? 'Enabled sport category ' : 'Disabled sport category ') . ($category['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function deleteSportCategory(int $id, int $actorId): void
    {
        $category = $this->requireRow('sport_categories', $id, 'Sport category');
        if ($this->db->table('sports')->where('category', $category['name'])->countAllResults() > 0) {
            throw new RuntimeException('Sport category cannot be removed while sports use it. Disable it instead.');
        }

        $this->db->transStart();
        $this->sportCategoriesModel->delete($id);
        $this->notify($actorId, 'sport_category_deleted', 'Removed sport category ' . ($category['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function createSport(array $data, int $actorId): int
    {
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        if (! $eventId) {
            throw new RuntimeException('Sport requires a valid active event.');
        }
        $category = $this->requireActiveSportCategory($this->positiveIntValue($data['category_id'] ?? null));
        unset($data['category_id']);
        $data['event_id'] = $eventId;
        $data['category'] = $category['name'];
        $this->assertActiveEvent($eventId);
        $this->db->transStart();
        $this->sportsModel->insert($data);
        $id = (int) $this->sportsModel->getInsertID();
        $this->notify($actorId, 'sport_created', 'Added sport ' . $data['name'] . ' (' . $data['category'] . ')');
        $this->finishTransaction();
        return $id;
    }

    public function updateSport(int $id, array $data, int $actorId): void
    {
        $sport = $this->requireRow('sports', $id, 'Sport');
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        if (! $eventId || (int) ($sport['event_id'] ?? 0) !== $eventId) {
            throw new RuntimeException('Sport does not belong to the active event.');
        }
        $categoryId = $this->positiveIntValue($data['category_id'] ?? null);
        $category = $this->sportCategory($categoryId);
        if (! $category) {
            throw new RuntimeException('Select a valid sport category.');
        }
        if (! (int) $category['is_active'] && (string) $category['name'] !== (string) ($sport['category'] ?? '')) {
            throw new RuntimeException('Selected sport category is inactive.');
        }
        unset($data['category_id']);
        $data['event_id'] = $eventId;
        $data['category'] = $category['name'];
        $this->assertActiveEvent((int) $sport['event_id']);
        if (($sport['result_type'] ?? '') !== $data['result_type'] && $this->db->table('schedules')->where('sport_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('Sport type cannot be changed after schedules have been created.');
        }
        $this->db->transStart();
        $this->sportsModel->update($id, $data);
        $this->notify($actorId, 'sport_updated', 'Updated sport ' . ($sport['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function deleteSport(int $id, int $actorId): void
    {
        $sport = $this->requireRow('sports', $id, 'Sport');
        $this->assertActiveEvent((int) $sport['event_id']);

        $dependencies = $this->db->table('schedules')->where('sport_id', $id)->countAllResults();
        $dependencies += $this->db->table('weighted_points')->where('sport_id', $id)->countAllResults();
        $dependencies += $this->db->table('user_sports')->where('sport_id', $id)->countAllResults();
        if ($dependencies > 0) {
            throw new RuntimeException('Sport cannot be removed while schedules, weighted points, results, or facilitator assignments depend on it.');
        }

        $this->db->transStart();
        $this->sportsModel->delete($id);
        $this->notify($actorId, 'sport_deleted', 'Removed sport ' . ($sport['name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function createSchedule(array $data, int $actorId): int
    {
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        if (! $eventId) {
            throw new RuntimeException('Schedule requires a valid active event.');
        }
        $data['event_id'] = $eventId;
        $this->assertActiveEvent($eventId);
        $this->assertSchedulePayload($data);
        $this->db->transStart();
        $this->schedulesModel->insert($data);
        $id = (int) $this->schedulesModel->getInsertID();
        $this->notify($actorId, 'schedule_created', 'Added a schedule for ' . date('M j, Y g:i A', strtotime($data['match_date'])));
        $this->finishTransaction();
        return $id;
    }

    public function updateSchedule(int $id, array $data, int $actorId): void
    {
        $schedule = $this->requireRow('schedules', $id, 'Schedule');
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        if (! $eventId || (int) ($schedule['event_id'] ?? 0) !== $eventId) {
            throw new RuntimeException('Schedule does not belong to the active event.');
        }
        $data['event_id'] = $eventId;
        $this->assertActiveEvent((int) $schedule['event_id']);
        if ($this->db->table('results')->where('schedule_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('A schedule with submitted results cannot be edited.');
        }
        $this->assertSchedulePayload($data, $id);
        $this->db->transStart();
        $this->schedulesModel->update($id, $data);
        $this->notify($actorId, 'schedule_updated', 'Updated schedule #' . ($schedule['id'] ?? $id));
        $this->finishTransaction();
    }

    public function deleteSchedule(int $id, int $actorId): void
    {
        $schedule = $this->requireRow('schedules', $id, 'Schedule');
        $this->assertActiveEvent((int) $schedule['event_id']);
        if ($this->db->table('results')->where('schedule_id', $id)->countAllResults() > 0) {
            throw new RuntimeException('A schedule with submitted results cannot be deleted.');
        }
        $this->db->transStart();
        $this->schedulesModel->delete($id);
        $this->notify($actorId, 'schedule_deleted', 'Removed schedule #' . ($schedule['id'] ?? $id));
        $this->finishTransaction();
    }

    public function createUser(array $data, array $sportIds, int $actorId): int
    {
        $role = (string) ($data['role'] ?? '');
        $this->assertAccountManagementPermission($role, $actorId);
        $sportIds = $this->normaliseManagedUserSports($role, $sportIds);
        $payload = array_intersect_key($data, array_flip(['username', 'password_hash', 'display_name', 'role', 'status', 'created_at']));
        if (empty($payload['password_hash'])) {
            throw new RuntimeException('Password is required.');
        }

        $this->db->transStart();
        $this->db->table('users')->insert($payload);
        $id = (int) $this->db->insertID();
        $this->syncUserSports($id, $sportIds);
        $this->notify($actorId, 'user_created', 'Added ' . $role . ' account for ' . ($payload['display_name'] ?? 'user'));
        $this->finishTransaction();
        return $id;
    }

    public function updateUser(int $id, array $data, array $sportIds, int $actorId): void
    {
        $user = $this->requireRow('users', $id, 'User');
        $currentRole = (string) ($user['role'] ?? '');
        $role = (string) ($data['role'] ?? $currentRole);
        $actor = $this->requireRow('users', $actorId, 'User');

        $this->assertAccountManagementPermission($currentRole, $actorId);
        $this->assertAccountManagementPermission($role, $actorId);
        if ($currentRole !== $role && ($actor['role'] ?? '') !== 'admin') {
            throw new RuntimeException('Only administrators can change an account role.');
        }
        $this->assertAdminContinuityOnUpdate($id, $currentRole, $role, (string) ($data['status'] ?? $user['status'] ?? 'active'));

        $syncSports = true;
        if ($role === 'facilitator' && $currentRole === 'facilitator' && ! $sportIds && ! $this->activeEvent()) {
            // Allow status/profile maintenance while no event is active without erasing historical assignments.
            $syncSports = false;
        } else {
            $sportIds = $this->normaliseManagedUserSports($role, $sportIds);
        }

        $payload = array_intersect_key($data, array_flip(['username', 'password_hash', 'display_name', 'role', 'status']));

        $this->db->transStart();
        $this->db->table('users')->where('id', $id)->update($payload);
        if ($syncSports) {
            $this->syncUserSports($id, $sportIds);
        }
        $this->notify($actorId, 'user_updated', 'Updated account for ' . ($payload['display_name'] ?? $user['display_name']));
        $this->finishTransaction();
    }

    public function deleteUser(int $id, int $actorId): void
    {
        $user = $this->requireRow('users', $id, 'User');
        if ($id === $actorId) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        $this->assertAccountManagementPermission((string) ($user['role'] ?? ''), $actorId);
        $this->assertAdminContinuityOnDelete($id, (string) ($user['role'] ?? ''));

        $this->db->transStart();
        $this->db->table('users')->where('id', $id)->delete();
        $this->notify($actorId, 'user_deleted', 'Removed account for ' . ($user['display_name'] ?? '#'.$id));
        $this->finishTransaction();
    }

    public function saveWeightedPoints(array $data, int $actorId): int
    {
        $data = $this->normaliseWeightedPointsData($data);
        $sport = $this->requireRow('sports', $data['sport_id'], 'Sport');
        if ((int) ($sport['event_id'] ?? 0) !== $data['event_id']) {
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
        $payload = [
            'event_id' => $data['event_id'],
            'sport_id' => $data['sport_id'],
            'first_points' => $data['first_points'],
            'second_points' => $data['second_points'],
            'third_points' => $data['third_points'],
            'participation_points' => $data['participation_points'],
            'status' => 'pending',
            'submitted_by' => $actorId,
            'validated_by' => null,
            'submitted_at' => date('Y-m-d H:i:s'),
            'validated_at' => null,
        ];
        $this->db->transStart();
        $this->db->table('weighted_points')->insert($payload);
        $id = (int) $this->db->insertID();
        $this->notify($actorId, 'weighted_points_submitted', 'Submitted weighted points for validator approval');
        $this->finishTransaction();
        return $id;
    }

    public function updateWeightedPoints(int $id, array $data, int $actorId): void
    {
        $data = $this->normaliseWeightedPointsData($data);
        $points = $this->requireRow('weighted_points', $id, 'Weighted points');
        if ((int) ($points['event_id'] ?? 0) !== (int) $data['event_id']) {
            throw new RuntimeException('Weighted points do not belong to the active event.');
        }
        $this->assertActiveEvent((int) $points['event_id']);
        if ((int) ($points['sport_id'] ?? 0) !== (int) ($data['sport_id'] ?? 0)) {
            throw new RuntimeException('The sport cannot be changed on an existing weighted-points record.');
        }
        $payload = [
            'event_id' => $data['event_id'],
            'sport_id' => $data['sport_id'],
            'first_points' => $data['first_points'],
            'second_points' => $data['second_points'],
            'third_points' => $data['third_points'],
            'participation_points' => $data['participation_points'],
            'status' => 'pending',
            'submitted_by' => $actorId,
            'submitted_at' => date('Y-m-d H:i:s'),
            'validated_by' => null,
            'validated_at' => null,
        ];
        $this->db->transStart();
        $this->db->table('weighted_points')->where('id', $id)->update($payload);
        $this->notify($actorId, 'weighted_points_updated', 'Updated weighted points and returned them to pending validation');
        $this->finishTransaction();
    }

    public function validateWeightedPoints(int $id, int $actorId): void
    {
        $points = $this->requireRow('weighted_points', $id, 'Weighted points');
        $this->assertActiveEvent((int) $points['event_id']);
        if (($points['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Weighted points have already been validated.');
        }

        $this->db->transStart();
        $this->db->table('weighted_points')->where('id', $id)->update([
            'status' => 'validated',
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);
        $validatedResults = $this->db->table('results r')
            ->select('r.id')
            ->join('schedules sc', 'sc.id=r.schedule_id')
            ->where('r.event_id', $points['event_id'])
            ->where('sc.sport_id', $points['sport_id'])
            ->where('r.status', 'validated')
            ->get()->getResultArray();
        foreach ($validatedResults as $validatedResult) {
            $result = $this->resultWithSchedule((int) $validatedResult['id']);
            $this->applyPointsToResult($result, $points);
        }
        $this->notify($actorId, 'weighted_points_validated', 'Validated weighted points configuration');
        $this->finishTransaction();
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
        $this->db->transStart();
        $this->db->table('weighted_points')->where('id', $id)->delete();
        $this->notify($actorId, 'weighted_points_deleted', 'Removed weighted points configuration');
        $this->finishTransaction();
    }

    public function createResult(array $data, int $actorId): int
    {
        $scheduleId = $this->positiveIntValue($data['schedule_id'] ?? null);
        if (! $scheduleId) {
            throw new RuntimeException('Select a valid schedule.');
        }
        $schedule = $this->resultSchedule($scheduleId);
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
        $notes = $this->optionalTextValue($data['notes'] ?? null);

        $this->db->transStart();
        $this->db->table('results')->insert([
            'event_id' => $schedule['event_id'],
            'schedule_id' => $schedule['id'],
            'type' => $schedule['result_type'],
            'status' => 'pending',
            'notes' => $notes,
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
        $notes = $this->optionalTextValue($data['notes'] ?? null);

        $this->db->transStart();
        $this->db->table('results')->where('id', $id)->update([
            'notes' => $notes,
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

        $this->db->transStart();
        $this->applyPointsToResult($result, $weightedPoints);
        $this->db->table('results')->where('id', $id)->update([
            'status' => 'validated',
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notify($actorId, 'result_validated', 'Validated result #' . $id . ' as official');
        $this->finishTransaction();
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
        $this->db->transStart();
        foreach ($settings as $key => $value) {
            $existing = $this->db->table('user_settings')->where(['user_id' => $userId, 'setting_key' => $key])->get()->getRowArray();
            if ($existing) {
                $this->db->table('user_settings')->where('id', $existing['id'])->update(['setting_value' => (string) $value]);
            } else {
                $this->db->table('user_settings')->insert(['user_id' => $userId, 'setting_key' => $key, 'setting_value' => (string) $value]);
            }
        }
        $this->finishTransaction();
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

    private function assertSchedulePayload(array $data, ?int $existingScheduleId = null): void
    {
        $sportId = $this->positiveIntValue($data['sport_id'] ?? null);
        $locationId = $this->positiveIntValue($data['location_id'] ?? null);
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        if (! $sportId || ! $locationId || ! $eventId) {
            throw new RuntimeException('Sport, location, and event identifiers must be valid.');
        }

        $sport = $this->requireRow('sports', $sportId, 'Sport');
        $location = $this->requireRow('locations', $locationId, 'Location');
        if (! (int) ($location['is_active'] ?? 0)) {
            $existingLocationId = 0;
            if ($existingScheduleId !== null) {
                $existingSchedule = $this->requireRow('schedules', $existingScheduleId, 'Schedule');
                $existingLocationId = (int) ($existingSchedule['location_id'] ?? 0);
            }
            if ($existingLocationId !== $locationId) {
                throw new RuntimeException('Selected location is inactive.');
            }
        }
        if ((int) $sport['event_id'] !== $eventId) {
            throw new RuntimeException('Selected sport does not belong to the active event.');
        }

        $teamA = empty($data['team_a_id']) ? 0 : $this->positiveIntValue($data['team_a_id']);
        $teamB = empty($data['team_b_id']) ? 0 : $this->positiveIntValue($data['team_b_id']);
        if (! empty($data['team_a_id']) && ! $teamA) {
            throw new RuntimeException('Team A is invalid.');
        }
        if (! empty($data['team_b_id']) && ! $teamB) {
            throw new RuntimeException('Team B is invalid.');
        }
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


    private function assertAccountManagementPermission(string $targetRole, int $actorId): void
    {
        $actor = $this->requireRow('users', $actorId, 'User');
        if (($actor['status'] ?? '') !== 'active') {
            throw new RuntimeException('Inactive accounts cannot manage users.');
        }

        $actorRole = (string) ($actor['role'] ?? '');
        $allowed = ($actorRole === 'admin' && in_array($targetRole, ['admin', 'manager', 'validator', 'facilitator'], true))
            || ($actorRole === 'manager' && $targetRole === 'facilitator');

        if (! $allowed) {
            throw new RuntimeException('You are not allowed to manage this account role.');
        }
    }

    private function assertAdminContinuityOnUpdate(int $userId, string $currentRole, string $newRole, string $newStatus): void
    {
        if ($currentRole !== 'admin' || ($newRole === 'admin' && $newStatus === 'active')) {
            return;
        }

        $otherActiveAdmins = $this->db->table('users')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->where('id !=', $userId)
            ->countAllResults();

        if ($otherActiveAdmins < 1) {
            throw new RuntimeException('At least one active administrator account must remain.');
        }
    }

    private function assertAdminContinuityOnDelete(int $userId, string $role): void
    {
        if ($role !== 'admin') {
            return;
        }

        $otherActiveAdmins = $this->db->table('users')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->where('id !=', $userId)
            ->countAllResults();

        if ($otherActiveAdmins < 1) {
            throw new RuntimeException('The last active administrator account cannot be deleted.');
        }
    }

    private function normaliseManagedUserSports(string $role, array $sportIds): array
    {
        if (! in_array($role, ['admin', 'manager', 'validator', 'facilitator'], true)) {
            throw new RuntimeException('Invalid managed account role.');
        }
        if ($role !== 'facilitator') {
            return [];
        }

        if (! $this->activeEvent()) {
            throw new RuntimeException('Activate an event before assigning sports to a facilitator.');
        }

        $sportIds = $this->validateActiveSportIds($sportIds);
        if (! $sportIds) {
            throw new RuntimeException('Assign at least one active-event sport to the facilitator.');
        }

        return $sportIds;
    }

    private function syncUserSports(int $userId, array $sportIds): void
    {
        $this->db->table('user_sports')->where('user_id', $userId)->delete();
        foreach ($sportIds as $sportId) {
            $this->db->table('user_sports')->insert(['user_id' => $userId, 'sport_id' => $sportId]);
        }
    }

    private function validateActiveSportIds(array $sportIds): array
    {
        $validated = [];
        foreach ($sportIds as $rawSportId) {
            $sportId = $this->positiveIntValue($rawSportId);
            if (! $sportId) {
                throw new RuntimeException('One or more assigned sports are invalid.');
            }
            $sport = $this->requireRow('sports', $sportId, 'Assigned sport');
            $this->assertActiveEvent((int) $sport['event_id']);
            $validated[] = $sportId;
        }

        return array_values(array_unique($validated));
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
            $scoreA = $this->decimalValue($data['team_a_score'] ?? null, self::MAX_RESULT_SCORE, 'Enter valid non-negative scores for both teams.');
            $scoreB = $this->decimalValue($data['team_b_score'] ?? null, self::MAX_RESULT_SCORE, 'Enter valid non-negative scores for both teams.');
            return [
                ['team_id' => (int) $scheduleOrResult['team_a_id'], 'raw_score' => $scoreA, 'placement' => null],
                ['team_id' => (int) $scheduleOrResult['team_b_id'], 'raw_score' => $scoreB, 'placement' => null],
            ];
        }

        $judged = $data['judged'] ?? [];
        if (! is_array($judged)) {
            throw new RuntimeException('Judged scores must be submitted as valid team-score pairs.');
        }

        $entries = [];
        foreach ($judged as $rawTeamId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }
            $teamId = $this->positiveIntValue($rawTeamId);
            if (! $teamId) {
                throw new RuntimeException('Judged scores contain an invalid team identifier.');
            }
            $value = $this->decimalValue($score, self::MAX_RESULT_SCORE, 'Judged scores must be non-negative numbers with at most 2 decimal places.');
            $this->requireRow('teams', $teamId, 'Team');
            $entries[] = ['team_id' => $teamId, 'raw_score' => $value];
        }
        if (! $entries) {
            throw new RuntimeException('Enter at least one judged score.');
        }
        usort($entries, static fn(array $x, array $y): int => (float) $y['raw_score'] <=> (float) $x['raw_score']);
        foreach ($entries as $index => &$entry) {
            $entry['placement'] = $index + 1;
        }
        unset($entry);
        return $entries;
    }

    private function applyPointsToResult(array $result, array $weightedPoints): void
    {
        $entries = $this->resultEntries((int) $result['id']);
        if (! $entries) {
            throw new RuntimeException('A result cannot be validated without score entries.');
        }

        if (($result['result_type'] ?? '') === 'match') {
            if (count($entries) !== 2) {
                throw new RuntimeException('Match results must contain exactly two team scores.');
            }
            usort($entries, static fn(array $x, array $y): int => (float) $y['raw_score'] <=> (float) $x['raw_score']);
            $round = strtolower((string) $result['round']);
            $isPlacementRound = (str_contains($round, 'final') && ! str_contains($round, 'semi'))
                || str_contains($round, 'bronze') || str_contains($round, '3rd');
            if ($isPlacementRound && (float) $entries[0]['raw_score'] === (float) $entries[1]['raw_score']) {
                throw new RuntimeException('This placement match is tied. A tie-breaking business rule is required before it can be validated.');
            }
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

            return;
        }

        $seenScores = [];
        foreach ($entries as $entry) {
            $scoreKey = number_format((float) $entry['raw_score'], 2, '.', '');
            if (isset($seenScores[$scoreKey])) {
                throw new RuntimeException('This judged result contains tied scores. A tie-ranking business rule is required before it can be validated.');
            }
            $seenScores[$scoreKey] = true;
            $placement = (int) $entry['placement'];
            if ($placement < 1) {
                throw new RuntimeException('Judged result placements are invalid.');
            }
            $this->db->table('result_entries')->where('id', $entry['id'])->update([
                'allocated_points' => $this->pointsForPlacement($weightedPoints, $placement),
            ]);
        }
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

    private function normaliseWeightedPointsData(array $data): array
    {
        $eventId = $this->positiveIntValue($data['event_id'] ?? null);
        $sportId = $this->positiveIntValue($data['sport_id'] ?? null);
        if (! $eventId || ! $sportId) {
            throw new RuntimeException('Weighted points require a valid event and sport.');
        }
        $data['event_id'] = $eventId;
        $data['sport_id'] = $sportId;
        foreach (['first_points', 'second_points', 'third_points', 'participation_points'] as $field) {
            $data[$field] = $this->decimalValue(
                $data[$field] ?? null,
                999999.99,
                'Point values must be non-negative numbers with at most 2 decimal places and no more than 999999.99.'
            );
        }

        return $data;
    }

    private function requireActiveSportCategory(int $categoryId): array
    {
        if (! $categoryId) {
            throw new RuntimeException('Select a valid sport category.');
        }
        $category = $this->sportCategory($categoryId);
        if (! $category) {
            throw new RuntimeException('Select a valid sport category.');
        }
        if (! (int) ($category['is_active'] ?? 0)) {
            throw new RuntimeException('Selected sport category is inactive.');
        }
        return $category;
    }

    private function normaliseReferenceName(string $name, int $maxLength, string $label): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || mb_strlen($name) > $maxLength) {
            throw new RuntimeException($label . ' name is invalid.');
        }
        return $name;
    }

    private function assertUniqueReferenceName(string $table, string $name, ?int $exceptId = null): void
    {
        $rows = $this->db->table($table)->select('id,name')->get()->getResultArray();
        foreach ($rows as $row) {
            if ($exceptId !== null && (int) $row['id'] === $exceptId) {
                continue;
            }
            if (mb_strtolower(trim((string) $row['name'])) === mb_strtolower($name)) {
                throw new RuntimeException('A record with this name already exists.');
            }
        }
    }

    private function positiveIntValue(mixed $value): int
    {
        if (! is_int($value) && ! is_string($value)) {
            return 0;
        }
        $raw = trim((string) $value);
        if (! preg_match('/^[1-9]\d*$/', $raw)) {
            return 0;
        }
        $validated = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? 0 : (int) $validated;
    }

    private function decimalValue(mixed $value, float $max, string $error): string
    {
        if (! is_scalar($value)) {
            throw new RuntimeException($error);
        }
        $raw = trim((string) $value);
        if ($raw === '' || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) || (float) $raw > $max) {
            throw new RuntimeException($error);
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function optionalTextValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (! is_scalar($value)) {
            throw new RuntimeException('Notes must be plain text.');
        }

        return trim((string) $value);
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
