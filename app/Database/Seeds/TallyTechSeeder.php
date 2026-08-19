<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class TallyTechSeeder extends Seeder
{
    private int $changes = 0;

    public function run()
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('TallyTech development seed data must not be loaded in production.');
        }

        if (! $this->db->tableExists('users')
            || ! $this->db->tableExists('events')
            || ! $this->db->tableExists('sport_categories')
            || ! $this->db->fieldExists('is_active', 'locations')) {
            throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before seeding.');
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start database seed transaction.');
        }

        try {
            $now = date('Y-m-d H:i:s');

            $ids = [
                'admin' => $this->ensureUser([
                    'username' => 'admin',
                    'password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
                    'display_name' => 'System Admin',
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => $now,
                ]),
                'manager' => $this->ensureUser([
                    'username' => 'manager',
                    'password_hash' => password_hash('Manager@12345', PASSWORD_DEFAULT),
                    'display_name' => 'Joy Tournament Manager',
                    'role' => 'manager',
                    'status' => 'active',
                    'created_at' => $now,
                ]),
                'validator' => $this->ensureUser([
                    'username' => 'validator',
                    'password_hash' => password_hash('Validator@12345', PASSWORD_DEFAULT),
                    'display_name' => 'ISF Validator',
                    'role' => 'validator',
                    'status' => 'active',
                    'created_at' => $now,
                ]),
                'facilitator' => $this->ensureUser([
                    'username' => 'facilitator',
                    'password_hash' => password_hash('Facilitator@12345', PASSWORD_DEFAULT),
                    'display_name' => 'Game Facilitator',
                    'role' => 'facilitator',
                    'status' => 'active',
                    'created_at' => $now,
                ]),
            ];

            $eventId = $this->ensureEvent([
                'name' => 'Intercollegiate Students Festival 2026',
                'year' => 2026,
                'start_date' => '2026-08-15',
                'end_date' => '2026-08-18',
                'status' => 'active',
                'is_active' => 1,
                'created_at' => $now,
            ]);

            $teams = [
                'CBA' => $this->ensureTeam(['name' => 'CBA Lions', 'code' => 'CBA', 'created_at' => $now]),
                'CCS-CAF' => $this->ensureTeam(['name' => 'CCS Panthers & CAF Buffaloes', 'code' => 'CCS-CAF', 'created_at' => $now]),
                'CIT-COC' => $this->ensureTeam(['name' => 'CIT Dragons & COC Stallions', 'code' => 'CIT-COC', 'created_at' => $now]),
                'SCA-CLAIM' => $this->ensureTeam(['name' => 'SCA Eagles & CLAIM Phoenix', 'code' => 'SCA-CLAIM', 'created_at' => $now]),
            ];

            $locations = [
                'Main Gymnasium' => $this->ensureLocation('Main Gymnasium', $now),
                'Covered Court' => $this->ensureLocation('Covered Court', $now),
                'ISF Field' => $this->ensureLocation('ISF Field', $now),
                'Auditorium' => $this->ensureLocation('Auditorium', $now),
            ];

            foreach (['Men', 'Women', 'Mixed'] as $category) {
                $this->ensureSportCategory($category, $now);
            }

            $sports = [
                'Basketball' => $this->ensureSport($eventId, 'Basketball', 'Men', 'match', $now),
                'Volleyball' => $this->ensureSport($eventId, 'Volleyball', 'Women', 'match', $now),
                'Badminton' => $this->ensureSport($eventId, 'Badminton', 'Men', 'match', $now),
                'Cheerdance' => $this->ensureSport($eventId, 'Cheerdance', 'Mixed', 'judged', $now),
            ];

            foreach ([$sports['Basketball'], $sports['Volleyball'], $sports['Badminton'], $sports['Cheerdance']] as $sportId) {
                $this->ensureUserSport($ids['manager'], $sportId);
            }

            foreach ([$sports['Basketball'], $sports['Volleyball'], $sports['Cheerdance']] as $sportId) {
                $this->ensureUserSport($ids['facilitator'], $sportId);
            }

            foreach ($sports as $sportId) {
                $this->ensureWeightedPoints($eventId, $sportId, $ids['manager'], $ids['validator'], $now);
            }

            $basketballSchedule = $this->ensureSchedule([
                'event_id' => $eventId,
                'sport_id' => $sports['Basketball'],
                'location_id' => $locations['Main Gymnasium'],
                'round' => 'Final',
                'match_date' => '2026-08-15 18:00:00',
                'team_a_id' => $teams['CBA'],
                'team_b_id' => $teams['CCS-CAF'],
                'status' => 'played',
                'created_at' => $now,
            ]);

            $this->ensureSchedule([
                'event_id' => $eventId,
                'sport_id' => $sports['Volleyball'],
                'location_id' => $locations['Covered Court'],
                'round' => 'Final',
                'match_date' => '2026-08-16 09:00:00',
                'team_a_id' => $teams['CIT-COC'],
                'team_b_id' => $teams['SCA-CLAIM'],
                'status' => 'scheduled',
                'created_at' => $now,
            ]);

            $cheerdanceSchedule = $this->ensureSchedule([
                'event_id' => $eventId,
                'sport_id' => $sports['Cheerdance'],
                'location_id' => $locations['Auditorium'],
                'round' => 'Championship',
                'match_date' => '2026-08-16 14:00:00',
                'team_a_id' => null,
                'team_b_id' => null,
                'status' => 'played',
                'created_at' => $now,
            ]);

            $basketballResult = $this->ensureResult([
                'event_id' => $eventId,
                'schedule_id' => $basketballSchedule,
                'type' => 'match',
                'status' => 'validated',
                'submitted_by' => $ids['facilitator'],
                'validated_by' => $ids['validator'],
                'submitted_at' => $now,
                'validated_at' => $now,
            ]);

            $this->ensureResultEntry($basketballResult, $teams['CBA'], 88, 1, 10);
            $this->ensureResultEntry($basketballResult, $teams['CCS-CAF'], 81, 2, 7);

            $cheerdanceResult = $this->ensureResult([
                'event_id' => $eventId,
                'schedule_id' => $cheerdanceSchedule,
                'type' => 'judged',
                'status' => 'pending',
                'submitted_by' => $ids['facilitator'],
                'validated_by' => null,
                'submitted_at' => $now,
                'validated_at' => null,
            ]);

            $this->ensureResultEntry($cheerdanceResult, $teams['SCA-CLAIM'], 94.5, 1, 0);
            $this->ensureResultEntry($cheerdanceResult, $teams['CIT-COC'], 92, 2, 0);
            $this->ensureResultEntry($cheerdanceResult, $teams['CBA'], 89.5, 3, 0);
            $this->ensureResultEntry($cheerdanceResult, $teams['CCS-CAF'], 87, 4, 0);

            $this->ensureNotification($ids['facilitator'], 'result_submitted', 'Submitted unofficial Cheerdance judged result', $now);
            $this->ensureNotification($ids['validator'], 'result_validated', 'Validated Basketball final as official', $now);
            $this->ensureNotification($ids['manager'], 'weighted_points_validated', 'Weighted points are ready for scoring', $now);

            if (! $this->db->transComplete()) {
                throw new RuntimeException('Unable to complete database seed transaction.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if ($this->changes === 0) {
            CLI::write('No pending seed; database already seeded.', 'green');
            return;
        }

        CLI::write('Seeding complete.', 'green');
    }

    private function ensureUser(array $data): int
    {
        $row = $this->db->table('users')->select('id')->where('username', $data['username'])->get()->getRowArray();
        return $this->existingOrInsert('users', $row, $data);
    }

    private function ensureEvent(array $data): int
    {
        $row = $this->db->table('events')->select('id')->where('name', $data['name'])->where('year', $data['year'])->get()->getRowArray();
        return $this->existingOrInsert('events', $row, $data);
    }

    private function ensureTeam(array $data): int
    {
        $row = $this->db->table('teams')->select('id')->where('code', $data['code'])->get()->getRowArray();
        return $this->existingOrInsert('teams', $row, $data);
    }

    private function ensureLocation(string $name, string $now): int
    {
        $row = $this->db->table('locations')->select('id')->where('name', $name)->get()->getRowArray();
        return $this->existingOrInsert('locations', $row, [
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSportCategory(string $name, string $now): int
    {
        $row = $this->db->table('sport_categories')->select('id')->where('name', $name)->get()->getRowArray();
        return $this->existingOrInsert('sport_categories', $row, [
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSport(int $eventId, string $name, string $category, string $resultType, string $now): int
    {
        $row = $this->db->table('sports')
            ->select('id')
            ->where('event_id', $eventId)
            ->where('name', $name)
            ->where('category', $category)
            ->get()
            ->getRowArray();

        return $this->existingOrInsert('sports', $row, [
            'event_id' => $eventId,
            'name' => $name,
            'category' => $category,
            'result_type' => $resultType,
            'created_at' => $now,
        ]);
    }

    private function ensureUserSport(int $userId, int $sportId): void
    {
        $exists = $this->db->table('user_sports')->where('user_id', $userId)->where('sport_id', $sportId)->countAllResults() > 0;
        if ($exists) {
            return;
        }

        $this->db->table('user_sports')->insert(['user_id' => $userId, 'sport_id' => $sportId]);
        $this->changes++;
    }

    private function ensureWeightedPoints(int $eventId, int $sportId, int $managerId, int $validatorId, string $now): void
    {
        $exists = $this->db->table('weighted_points')->where('event_id', $eventId)->where('sport_id', $sportId)->countAllResults() > 0;
        if ($exists) {
            return;
        }

        $this->db->table('weighted_points')->insert([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'first_points' => 10,
            'second_points' => 7,
            'third_points' => 5,
            'participation_points' => 2,
            'status' => 'validated',
            'submitted_by' => $managerId,
            'validated_by' => $validatorId,
            'submitted_at' => $now,
            'validated_at' => $now,
        ]);
        $this->changes++;
    }

    private function ensureSchedule(array $data): int
    {
        $row = $this->db->table('schedules')
            ->select('id')
            ->where('event_id', $data['event_id'])
            ->where('sport_id', $data['sport_id'])
            ->where('round', $data['round'])
            ->where('match_date', $data['match_date'])
            ->get()
            ->getRowArray();

        return $this->existingOrInsert('schedules', $row, $data);
    }

    private function ensureResult(array $data): int
    {
        $row = $this->db->table('results')->select('id')->where('schedule_id', $data['schedule_id'])->get()->getRowArray();
        return $this->existingOrInsert('results', $row, $data);
    }

    private function ensureResultEntry(int $resultId, int $teamId, float|int $rawScore, int $placement, float|int $allocatedPoints): void
    {
        $exists = $this->db->table('result_entries')->where('result_id', $resultId)->where('team_id', $teamId)->countAllResults() > 0;
        if ($exists) {
            return;
        }

        $this->db->table('result_entries')->insert([
            'result_id' => $resultId,
            'team_id' => $teamId,
            'raw_score' => $rawScore,
            'placement' => $placement,
            'allocated_points' => $allocatedPoints,
        ]);
        $this->changes++;
    }

    private function ensureNotification(int $actorUserId, string $action, string $message, string $now): void
    {
        $exists = $this->db->table('notifications')->where('action', $action)->where('message', $message)->countAllResults() > 0;
        if ($exists) {
            return;
        }

        $this->db->table('notifications')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'message' => $message,
            'created_at' => $now,
        ]);
        $this->changes++;
    }

    private function existingOrInsert(string $table, ?array $row, array $data): int
    {
        if ($row !== null) {
            return (int) $row['id'];
        }

        $this->db->table($table)->insert($data);
        $this->changes++;

        return (int) $this->db->insertID();
    }
}
