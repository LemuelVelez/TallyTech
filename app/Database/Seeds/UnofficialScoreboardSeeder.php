<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class UnofficialScoreboardSeeder extends Seeder
{
    private int $changes = 0;

    public function run()
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('Unofficial scoreboard demo data must not be loaded in production.');
        }

        $this->assertSchemaReady();

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start unofficial scoreboard seed transaction.');
        }

        try {
            $now = date('Y-m-d H:i:s');
            $eventId = $this->activeOrDemoEvent($now);
            $facilitatorId = $this->ensureUser('scoreboard.facilitator', 'Scoreboard Facilitator', 'facilitator', 'Facilitator_123', $now);
            $validatorId = $this->ensureUser('scoreboard.validator', 'Scoreboard Validator', 'validator', 'Validator_123', $now);
            $locationId = $this->ensureLocation('Scoreboard Demo Court', $now);
            $this->ensureSportCategory('Women', $now);

            $teams = [
                'CIT-COC' => $this->ensureTeam('CIT Dragons & COC Stallions', 'CIT-COC', $now),
                'SCA-CLAIM' => $this->ensureTeam('SCA Eagles & CLAIM Phoenix', 'SCA-CLAIM', $now),
            ];

            $sportId = $this->ensureSport($eventId, '3x3 Basketball', 'Women', $now);
            $this->ensureWeightedPoints($eventId, $sportId, $facilitatorId, $validatorId, $now);
            $scheduleId = $this->ensureSchedule([
                'event_id' => $eventId,
                'sport_id' => $sportId,
                'location_id' => $locationId,
                'round' => 'Final',
                'tournament_format' => 'single_elimination',
                'match_code' => 'SB-U1',
                'phase' => 'final',
                'bracket_side' => 'grand',
                'bracket_order' => 1,
                'feeds_from_a' => null,
                'feeds_from_a_type' => null,
                'feeds_from_b' => null,
                'feeds_from_b_type' => null,
                'court_label' => 'Center Court',
                'is_conditional' => 0,
                'scheduling_note' => 'Unofficial 3x3 Basketball Women championship match awaiting validation.',
                'match_date' => '2026-08-18 19:00:00',
                'team_a_id' => $teams['CIT-COC'],
                'team_b_id' => $teams['SCA-CLAIM'],
                'status' => 'played',
                'created_at' => $now,
            ]);

            $resultId = $this->ensureResult([
                'event_id' => $eventId,
                'schedule_id' => $scheduleId,
                'type' => 'match',
                'status' => 'pending',
                'notes' => 'Seeded provisional championship result awaiting validation.',
                'submitted_by' => $facilitatorId,
                'validated_by' => null,
                'submitted_at' => $now,
                'validated_at' => null,
            ]);

            $this->ensureResultEntry($resultId, $teams['CIT-COC'], 19, [19], null, 0);
            $this->ensureResultEntry($resultId, $teams['SCA-CLAIM'], 16, [16], null, 0);
            $this->removeUnexpectedResultEntries($resultId, array_values($teams));

            if (! $this->db->transComplete()) {
                throw new RuntimeException('Unable to complete unofficial scoreboard seed transaction.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if ($this->changes === 0) {
            CLI::write('ℹ️  Unofficial scoreboard demo data is already seeded.', 'green');
            return;
        }

        CLI::write('✅ Unofficial scoreboard demo data synchronized (' . $this->changes . ' change' . ($this->changes === 1 ? '' : 's') . ').', 'green');
    }

    private function assertSchemaReady(): void
    {
        foreach (['users', 'events', 'teams', 'locations', 'sport_categories', 'sports', 'schedules', 'weighted_points', 'results', 'result_entries'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before seeding the unofficial scoreboard.');
            }
        }

        foreach (['tournament_format', 'match_code', 'phase', 'bracket_side', 'bracket_order', 'court_label', 'is_conditional', 'scheduling_note'] as $field) {
            if (! $this->db->fieldExists($field, 'schedules')) {
                throw new RuntimeException('Bracket scheduling schema is incomplete. Run "php spark migrate" before seeding the unofficial scoreboard.');
            }
        }

        if (! $this->db->fieldExists('is_active', 'locations')
            || ! $this->db->fieldExists('set_count', 'sports')
            || ! $this->db->fieldExists('winning_points', 'sports')
            || ! $this->db->fieldExists('set_scores', 'result_entries')) {
            throw new RuntimeException('Scoreboard scoring schema is incomplete. Run "php spark migrate" before seeding the unofficial scoreboard.');
        }
    }

    private function activeOrDemoEvent(string $now): int
    {
        $active = $this->db->table('events')->select('id')->where('is_active', 1)->orderBy('year', 'DESC')->get()->getRowArray();
        if ($active) {
            return (int) $active['id'];
        }

        $row = $this->db->table('events')->select('id')->where('name', 'Intercollegiate Students Festival 2026')->where('year', 2026)->get()->getRowArray();
        if ($row) {
            $this->db->table('events')->where('id', (int) $row['id'])->update(['status' => 'active', 'is_active' => 1]);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('events')->insert([
            'name' => 'Intercollegiate Students Festival 2026',
            'year' => 2026,
            'start_date' => '2026-08-15',
            'end_date' => '2026-08-18',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
        ]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureUser(string $username, string $displayName, string $role, string $password, string $now): int
    {
        $row = $this->db->table('users')->select('id')->where('username', $username)->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('users')->insert([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role,
            'status' => 'active',
            'created_at' => $now,
        ]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureTeam(string $name, string $code, string $now): int
    {
        $row = $this->db->table('teams')->select('id')->where('code', $code)->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('teams')->insert(['name' => $name, 'code' => $code, 'created_at' => $now]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureLocation(string $name, string $now): int
    {
        $row = $this->db->table('locations')->select('id,is_active')->where('name', $name)->get()->getRowArray();
        if ($row) {
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                $this->db->table('locations')->where('id', (int) $row['id'])->update(['is_active' => 1, 'updated_at' => $now]);
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('locations')->insert(['name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureSportCategory(string $name, string $now): int
    {
        $row = $this->db->table('sport_categories')->select('id,is_active')->where('name', $name)->get()->getRowArray();
        if ($row) {
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                $this->db->table('sport_categories')->where('id', (int) $row['id'])->update(['is_active' => 1, 'updated_at' => $now]);
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('sport_categories')->insert(['name' => $name, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureSport(int $eventId, string $name, string $category, string $now): int
    {
        $row = $this->db->table('sports')->select('id,result_type,set_count,winning_points')->where(['event_id' => $eventId, 'name' => $name, 'category' => $category])->get()->getRowArray();
        $values = ['result_type' => 'match', 'set_count' => 1, 'winning_points' => null];
        if ($row) {
            $this->db->table('sports')->where('id', (int) $row['id'])->update($values);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('sports')->insert(['event_id' => $eventId, 'name' => $name, 'category' => $category] + $values + ['created_at' => $now]);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureWeightedPoints(int $eventId, int $sportId, int $submittedBy, int $validatedBy, string $now): void
    {
        $data = [
            'first_points' => 10,
            'second_points' => 7,
            'third_points' => 5,
            'fourth_points' => 3,
            'participation_points' => 2,
            'status' => 'validated',
            'submitted_by' => $submittedBy,
            'validated_by' => $validatedBy,
            'submitted_at' => $now,
            'validated_at' => $now,
        ];
        $row = $this->db->table('weighted_points')->select('id')->where(['event_id' => $eventId, 'sport_id' => $sportId])->get()->getRowArray();
        if ($row) {
            $updates = $data;
            unset($updates['submitted_at'], $updates['validated_at']);
            $this->db->table('weighted_points')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return;
        }

        $this->db->table('weighted_points')->insert(['event_id' => $eventId, 'sport_id' => $sportId] + $data);
        $this->changes++;
    }

    private function ensureSchedule(array $data): int
    {
        $row = $this->db->table('schedules')->select('id')->where(['event_id' => $data['event_id'], 'sport_id' => $data['sport_id'], 'match_code' => $data['match_code']])->get()->getRowArray();
        if ($row) {
            $updates = $data;
            unset($updates['event_id'], $updates['sport_id'], $updates['match_code'], $updates['created_at']);
            $this->db->table('schedules')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('schedules')->insert($data);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureResult(array $data): int
    {
        $row = $this->db->table('results')->select('id')->where('schedule_id', $data['schedule_id'])->get()->getRowArray();
        if ($row) {
            $updates = $data;
            unset($updates['event_id'], $updates['schedule_id'], $updates['submitted_at'], $updates['validated_at']);
            $this->db->table('results')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        $this->db->table('results')->insert($data);
        $this->changes++;
        return (int) $this->db->insertID();
    }

    private function ensureResultEntry(int $resultId, int $teamId, float|int $score, array $setScores, ?int $placement, float|int $points): void
    {
        $data = [
            'raw_score' => $score,
            'set_scores' => json_encode(array_values($setScores), JSON_THROW_ON_ERROR),
            'placement' => $placement,
            'allocated_points' => $points,
        ];
        $row = $this->db->table('result_entries')->select('id')->where(['result_id' => $resultId, 'team_id' => $teamId])->get()->getRowArray();
        if ($row) {
            $this->db->table('result_entries')->where('id', (int) $row['id'])->update($data);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return;
        }

        $this->db->table('result_entries')->insert(['result_id' => $resultId, 'team_id' => $teamId] + $data);
        $this->changes++;
    }

    private function removeUnexpectedResultEntries(int $resultId, array $teamIds): void
    {
        $this->db->table('result_entries')->where('result_id', $resultId)->whereNotIn('team_id', array_map('intval', $teamIds))->delete();
        if ($this->db->affectedRows() > 0) {
            $this->changes++;
        }
    }
}
