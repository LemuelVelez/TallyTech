<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\ScoringRepositoryInterface;
use App\Models\UserModel;
use App\Models\EventModel;
use App\Models\TeamModel;
use App\Models\SportModel;
use App\Models\ScheduleModel;
use App\Models\WeightedPointModel;
use App\Models\ResultModel;
use App\Models\NotificationModel;
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
        $b = $this->db->table('sports s')->select('s.*, e.name event_name')->join('events e', 'e.id=s.event_id');
        if ($eventId) $b->where('s.event_id', $eventId);
        return $b->orderBy('s.category')->orderBy('s.name')->get()->getResultArray();
    }

    public function locations(): array
    {
        return $this->db->table('locations')->orderBy('name')->get()->getResultArray();
    }

    public function schedules(?int $eventId = null, ?string $resultType = null): array
    {
        $b = $this->db->table('schedules sc')
            ->select('sc.*, s.name sport_name, s.category, s.result_type, l.name location_name, ta.name team_a_name, tb.name team_b_name')
            ->join('sports s', 's.id=sc.sport_id')
            ->join('locations l', 'l.id=sc.location_id', 'left')
            ->join('teams ta', 'ta.id=sc.team_a_id', 'left')
            ->join('teams tb', 'tb.id=sc.team_b_id', 'left');
        if ($eventId) $b->where('sc.event_id', $eventId);
        if ($resultType) $b->where('s.result_type', $resultType);
        return $b->orderBy('sc.match_date', 'DESC')->get()->getResultArray();
    }

    public function usersByRole(string $role): array
    {
        $users = $this->db->table('users')->where('role', $role)->orderBy('display_name')->get()->getResultArray();
        foreach ($users as &$user) {
            $user['sports'] = $this->db->table('user_sports us')->select('s.id,s.name,s.category')->join('sports s', 's.id=us.sport_id')->where('us.user_id', $user['id'])->get()->getResultArray();
        }
        return $users;
    }

    public function assignedSportIds(int $userId): array
    {
        return array_map('intval', array_column($this->db->table('user_sports')->select('sport_id')->where('user_id', $userId)->get()->getResultArray(), 'sport_id'));
    }

    public function notifications(int $limit = 30): array
    {
        return $this->db->table('notifications n')->select('n.*, u.display_name actor_name')->join('users u', 'u.id=n.actor_user_id', 'left')->orderBy('n.id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public function weightedPoints(?int $eventId = null): array
    {
        $b = $this->db->table('weighted_points wp')->select('wp.*, s.name sport_name, s.category, u.display_name submitted_by_name, v.display_name validated_by_name')
            ->join('sports s', 's.id=wp.sport_id')
            ->join('users u', 'u.id=wp.submitted_by', 'left')
            ->join('users v', 'v.id=wp.validated_by', 'left');
        if ($eventId) $b->where('wp.event_id', $eventId);
        return $b->orderBy('s.name')->get()->getResultArray();
    }

    public function results(?int $eventId = null, ?string $type = null): array
    {
        $b = $this->db->table('results r')
            ->select('r.*, sc.round, sc.match_date, s.name sport_name, s.category, s.result_type, l.name location_name, u.display_name submitted_by_name, v.display_name validated_by_name')
            ->join('schedules sc', 'sc.id=r.schedule_id')
            ->join('sports s', 's.id=sc.sport_id')
            ->join('locations l', 'l.id=sc.location_id', 'left')
            ->join('users u', 'u.id=r.submitted_by', 'left')
            ->join('users v', 'v.id=r.validated_by', 'left');
        if ($eventId) $b->where('r.event_id', $eventId);
        if ($type) $b->where('r.type', $type);
        $rows = $b->orderBy('r.id', 'DESC')->get()->getResultArray();
        foreach ($rows as &$row) $row['entries'] = $this->resultEntries((int) $row['id']);
        return $rows;
    }

    public function resultEntries(int $resultId): array
    {
        return $this->db->table('result_entries re')->select('re.*, t.name team_name, t.code team_code')->join('teams t', 't.id=re.team_id')->where('re.result_id', $resultId)->orderBy('re.placement')->orderBy('re.id')->get()->getResultArray();
    }

    public function ranking(?int $eventId = null): array
    {
        $eventId ??= (int) ($this->activeEvent()['id'] ?? 0);
        if (! $eventId) return [];
        return $this->db->table('teams t')
            ->select('t.id,t.name,t.code,COALESCE(SUM(CASE WHEN r.status="validated" THEN re.allocated_points ELSE 0 END),0) total_points, SUM(CASE WHEN r.status="validated" AND re.placement=1 THEN 1 ELSE 0 END) firsts, SUM(CASE WHEN r.status="validated" AND re.placement=2 THEN 1 ELSE 0 END) seconds, SUM(CASE WHEN r.status="validated" AND re.placement=3 THEN 1 ELSE 0 END) thirds')
            ->join('result_entries re', 're.team_id=t.id', 'left')
            ->join('results r', 'r.id=re.result_id AND r.event_id=' . $this->db->escape($eventId), 'left')
            ->groupBy('t.id,t.name,t.code')->orderBy('total_points', 'DESC')->orderBy('firsts', 'DESC')->orderBy('seconds', 'DESC')->get()->getResultArray();
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

    public function createEvent(array $data, int $actorId): int
    {
        $this->db->transStart();
        if (! empty($data['is_active'])) $this->eventsModel->set('is_active', 0)->update();
        $this->eventsModel->insert($data);
        $id = (int) $this->eventsModel->getInsertID();
        $this->notify($actorId, 'event_created', 'Added event ' . $data['name']);
        $this->db->transComplete();
        return $id;
    }

    public function activateEvent(int $eventId, int $actorId): void
    {
        $this->db->transStart();
        $this->eventsModel->set('is_active', 0)->update();
        $this->eventsModel->update($eventId, ['is_active' => 1, 'status' => 'active']);
        $event = $this->db->table('events')->where('id', $eventId)->get()->getRowArray();
        $this->notify($actorId, 'event_activated', 'Activated event ' . ($event['name'] ?? '#'.$eventId));
        $this->db->transComplete();
    }

    public function createSport(array $data, int $actorId): int
    {
        $this->sportsModel->insert($data);
        $id = (int) $this->sportsModel->getInsertID();
        $this->notify($actorId, 'sport_created', 'Added sport ' . $data['name'] . ' (' . $data['category'] . ')');
        return $id;
    }

    public function createSchedule(array $data, int $actorId): int
    {
        $this->schedulesModel->insert($data);
        $id = (int) $this->schedulesModel->getInsertID();
        $this->notify($actorId, 'schedule_created', 'Added a schedule for ' . date('M j, Y g:i A', strtotime($data['match_date'])));
        return $id;
    }

    public function createUser(array $data, array $sportIds, int $actorId): int
    {
        $this->db->transStart();
        $this->db->table('users')->insert($data);
        $id = (int) $this->db->insertID();
        foreach (array_unique(array_filter(array_map('intval', $sportIds))) as $sportId) {
            $this->db->table('user_sports')->insert(['user_id' => $id, 'sport_id' => $sportId]);
        }
        $this->notify($actorId, 'user_created', 'Added ' . $data['role'] . ' account for ' . $data['display_name']);
        $this->db->transComplete();
        return $id;
    }

    public function saveWeightedPoints(array $data, int $actorId): int
    {
        $existing = $this->db->table('weighted_points')->where(['event_id' => $data['event_id'], 'sport_id' => $data['sport_id']])->get()->getRowArray();
        $payload = $data + ['status' => 'pending', 'submitted_by' => $actorId, 'validated_by' => null, 'validated_at' => null];
        if ($existing) {
            $this->db->table('weighted_points')->where('id', $existing['id'])->update($payload);
            $id = (int) $existing['id'];
        } else {
            $this->db->table('weighted_points')->insert($payload);
            $id = (int) $this->db->insertID();
        }
        $this->notify($actorId, 'weighted_points_submitted', 'Submitted weighted points for validator approval');
        return $id;
    }

    public function validateWeightedPoints(int $id, int $actorId): void
    {
        $this->db->table('weighted_points')->where('id', $id)->update(['status' => 'validated', 'validated_by' => $actorId, 'validated_at' => date('Y-m-d H:i:s')]);
        $this->notify($actorId, 'weighted_points_validated', 'Validated weighted points configuration');
    }

    public function createResult(array $data, int $actorId): int
    {
        $schedule = $this->db->table('schedules sc')->select('sc.*,s.result_type,s.name sport_name')->join('sports s', 's.id=sc.sport_id')->where('sc.id', $data['schedule_id'])->get()->getRowArray();
        if (! $schedule) throw new RuntimeException('Schedule not found.');
        $actor = $this->db->table('users')->where('id', $actorId)->get()->getRowArray();
        if (($actor['role'] ?? '') === 'facilitator' && ! in_array((int) $schedule['sport_id'], $this->assignedSportIds($actorId), true)) {
            throw new RuntimeException('You are not assigned to score this sport.');
        }
        if ($schedule['result_type'] === 'match' && (! $schedule['team_a_id'] || ! $schedule['team_b_id'])) {
            throw new RuntimeException('Match schedules require Team A and Team B.');
        }
        $wp = $this->db->table('weighted_points')->where(['event_id' => $schedule['event_id'], 'sport_id' => $schedule['sport_id'], 'status' => 'validated'])->get()->getRowArray();
        if (! $wp) throw new RuntimeException('Validated weighted points are required before scores can be submitted.');
        if ($schedule['result_type'] === 'judged') {
            $hasJudgedScore = false;
            foreach (($data['judged'] ?? []) as $score) { if ($score !== '' && $score !== null) { $hasJudgedScore = true; break; } }
            if (! $hasJudgedScore) throw new RuntimeException('Enter at least one judged score.');
        }

        $this->db->transStart();
        $this->db->table('results')->insert([
            'event_id' => $schedule['event_id'], 'schedule_id' => $schedule['id'], 'type' => $schedule['result_type'],
            'status' => 'pending', 'notes' => trim((string) ($data['notes'] ?? '')), 'submitted_by' => $actorId,
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();

        if ($schedule['result_type'] === 'match') {
            $a = (float) ($data['team_a_score'] ?? 0); $b = (float) ($data['team_b_score'] ?? 0);
            $this->db->table('result_entries')->insert(['result_id'=>$id,'team_id'=>$schedule['team_a_id'],'raw_score'=>$a]);
            $this->db->table('result_entries')->insert(['result_id'=>$id,'team_id'=>$schedule['team_b_id'],'raw_score'=>$b]);
        } else {
            $entries = [];
            foreach (($data['judged'] ?? []) as $teamId => $score) {
                if ($score === '' || $score === null) continue;
                $entries[] = ['team_id'=>(int)$teamId, 'raw_score'=>(float)$score];
            }
            usort($entries, static fn($x,$y) => $y['raw_score'] <=> $x['raw_score']);
            foreach ($entries as $i => $entry) $this->db->table('result_entries')->insert(['result_id'=>$id,'team_id'=>$entry['team_id'],'raw_score'=>$entry['raw_score'],'placement'=>$i+1]);
        }
        $this->db->table('schedules')->where('id', $schedule['id'])->update(['status'=>'played']);
        $this->notify($actorId, 'result_submitted', 'Submitted unofficial ' . $schedule['result_type'] . ' result for ' . $schedule['sport_name']);
        $this->db->transComplete();
        return $id;
    }

    public function validateResult(int $id, int $actorId): void
    {
        $result = $this->db->table('results r')->select('r.*,sc.sport_id,sc.round,s.result_type')->join('schedules sc','sc.id=r.schedule_id')->join('sports s','s.id=sc.sport_id')->where('r.id',$id)->get()->getRowArray();
        if (! $result) throw new RuntimeException('Result not found.');
        $wp = $this->db->table('weighted_points')->where(['event_id'=>$result['event_id'],'sport_id'=>$result['sport_id'],'status'=>'validated'])->get()->getRowArray();
        if (! $wp) throw new RuntimeException('Weighted points must be validated first.');

        $entries = $this->resultEntries($id);
        if ($result['result_type'] === 'match') {
            usort($entries, static fn($x,$y) => (float)$y['raw_score'] <=> (float)$x['raw_score']);
            $round = strtolower((string)$result['round']);
            foreach ($entries as $i => $entry) {
                $placement = null; $points = 0;
                if (str_contains($round, 'final') && !str_contains($round,'semi')) { $placement = $i + 1; }
                elseif (str_contains($round, 'bronze') || str_contains($round,'3rd')) { $placement = $i === 0 ? 3 : 4; }
                if ($placement) $points = $this->pointsForPlacement($wp, $placement);
                $this->db->table('result_entries')->where('id',$entry['id'])->update(['placement'=>$placement,'allocated_points'=>$points]);
            }
        } else {
            foreach ($entries as $entry) {
                $placement = (int) $entry['placement'];
                $this->db->table('result_entries')->where('id',$entry['id'])->update(['allocated_points'=>$this->pointsForPlacement($wp,$placement)]);
            }
        }
        $this->db->table('results')->where('id',$id)->update(['status'=>'validated','validated_by'=>$actorId,'validated_at'=>date('Y-m-d H:i:s')]);
        $this->notify($actorId, 'result_validated', 'Validated result #' . $id . ' as official');
    }

    public function updateUserSettings(int $userId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $existing = $this->db->table('user_settings')->where(['user_id'=>$userId,'setting_key'=>$key])->get()->getRowArray();
            if ($existing) $this->db->table('user_settings')->where('id',$existing['id'])->update(['setting_value'=>(string)$value]);
            else $this->db->table('user_settings')->insert(['user_id'=>$userId,'setting_key'=>$key,'setting_value'=>(string)$value]);
        }
    }

    public function getUserSettings(int $userId): array
    {
        $rows = $this->db->table('user_settings')->where('user_id',$userId)->get()->getResultArray();
        $out=[]; foreach($rows as $row) $out[$row['setting_key']]=$row['setting_value']; return $out;
    }

    public function deleteTeam(int $id, int $actorId): void
    {
        $team=$this->db->table('teams')->where('id',$id)->get()->getRowArray();
        $this->db->table('teams')->where('id',$id)->delete();
        $this->notify($actorId,'team_deleted','Removed team '.($team['name'] ?? '#'.$id));
    }

    private function pointsForPlacement(array $wp, int $placement): float
    {
        return match ($placement) {
            1 => (float) $wp['first_points'],
            2 => (float) $wp['second_points'],
            3 => (float) $wp['third_points'],
            default => (float) $wp['participation_points'],
        };
    }

    private function notify(int $actorId, string $action, string $message): void
    {
        $this->notificationsModel->insert(['actor_user_id'=>$actorId,'action'=>$action,'message'=>$message,'created_at'=>date('Y-m-d H:i:s')]);
    }
}
