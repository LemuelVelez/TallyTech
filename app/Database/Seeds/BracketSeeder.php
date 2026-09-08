<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class BracketSeeder extends Seeder
{
    private int $changes = 0;

    public function run()
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('TallyTech bracket seed data must not be loaded in production.');
        }

        $requiredScheduleFields = [
            'match_code',
            'phase',
            'bracket_side',
            'bracket_order',
            'feeds_from_a',
            'feeds_from_a_type',
            'feeds_from_b',
            'feeds_from_b_type',
            'court_label',
            'is_conditional',
            'scheduling_note',
            'tournament_format',
        ];

        if (! $this->db->tableExists('schedules')
            || ! $this->db->tableExists('results')
            || ! $this->db->tableExists('result_entries')
            || ! $this->db->tableExists('sport_categories')
            || ! $this->db->fieldExists('is_active', 'locations')
            || ! $this->db->fieldExists('set_count', 'sports')
            || ! $this->db->fieldExists('winning_points', 'sports')
            || ! $this->db->fieldExists('set_scores', 'result_entries')) {
            throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before seeding brackets.');
        }

        foreach ($requiredScheduleFields as $field) {
            if (! $this->db->fieldExists($field, 'schedules')) {
                throw new RuntimeException('Bracket scheduling schema is incomplete. Run "php spark migrate" before seeding brackets.');
            }
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start bracket seed transaction.');
        }

        try {
            $now = date('Y-m-d H:i:s');

            $facilitatorId = $this->ensureUser([
                'username' => 'facilitator',
                'password_hash' => password_hash('Facilitator_123', PASSWORD_DEFAULT),
                'display_name' => 'Game Facilitator',
                'role' => 'facilitator',
                'status' => 'active',
                'created_at' => $now,
            ], 'Facilitator_123');
            $validatorId = $this->ensureUser([
                'username' => 'validator',
                'password_hash' => password_hash('Validator_123', PASSWORD_DEFAULT),
                'display_name' => 'ISF Validator',
                'role' => 'validator',
                'status' => 'active',
                'created_at' => $now,
            ], 'Validator_123');

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
                'Auditorium' => $this->ensureLocation('Auditorium', $now),
            ];

            foreach (['Men', 'Women', 'Mixed'] as $category) {
                $this->ensureSportCategory($category, $now);
            }

            $sports = [
                'Basketball Men' => $this->ensureSport($eventId, 'Basketball', 'Men', 'match', 1, null, $now),
                'Volleyball Men' => $this->ensureSport($eventId, 'Volleyball', 'Men', 'match', 5, 25, $now),
                'Volleyball Women' => $this->ensureSport($eventId, 'Volleyball', 'Women', 'match', 5, 25, $now),
                'Badminton Men' => $this->ensureSport($eventId, 'Badminton', 'Men', 'match', 3, 21, $now),
                'Cheerdance Mixed' => $this->ensureSport($eventId, 'Cheerdance', 'Mixed', 'judged', 1, null, $now),
            ];

            $this->seedBasketballSingleElimination($eventId, $sports['Basketball Men'], $locations['Main Gymnasium'], $teams, $facilitatorId, $validatorId, $now);
            $this->seedVolleyballMenSingleElimination($eventId, $sports['Volleyball Men'], $locations['Covered Court'], $teams, $facilitatorId, $validatorId, $now);
            $this->seedVolleyballWomenDoubleElimination($eventId, $sports['Volleyball Women'], $locations['Covered Court'], $teams, $facilitatorId, $validatorId, $now);
            $this->seedBadmintonSingleElimination($eventId, $sports['Badminton Men'], $locations['Main Gymnasium'], $teams, $facilitatorId, $validatorId, $now);
            $this->seedCheerdanceChampionship($eventId, $sports['Cheerdance Mixed'], $locations['Auditorium'], $teams, $facilitatorId, $now);

            if (! $this->db->transComplete()) {
                throw new RuntimeException('Unable to complete bracket seed transaction.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if ($this->changes === 0) {
            CLI::write('ℹ️  No pending bracket seed data; brackets are already seeded.', 'green');
            return;
        }

        CLI::write('✅ Bracket seed data synchronized successfully (' . $this->changes . ' change' . ($this->changes === 1 ? '' : 's') . ').', 'green');
    }

    private function seedBasketballSingleElimination(int $eventId, int $sportId, int $locationId, array $teams, int $facilitatorId, int $validatorId, string $now): void
    {
        $m1 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M1',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 1,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-15 09:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m2 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M2',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 2,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-15 10:30:00',
            'team_a_id' => $teams['CIT-COC'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M3',
            'phase' => 'final',
            'bracket_side' => 'grand',
            'bracket_order' => 3,
            'feeds_from_a' => 'M1',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M2',
            'feeds_from_b_type' => 'winner',
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Championship match.',
            'match_date' => '2026-08-15 18:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'scheduled',
            'created_at' => $now,
        ]);

        $this->ensureMatchResult($eventId, $m1, $teams['CBA'], 86, null, $teams['CCS-CAF'], 78, null, $facilitatorId, $validatorId, '2026-08-15 10:20:00');
        $this->ensureMatchResult($eventId, $m2, $teams['CIT-COC'], 75, null, $teams['SCA-CLAIM'], 82, null, $facilitatorId, $validatorId, '2026-08-15 11:50:00');
    }

    private function seedVolleyballMenSingleElimination(int $eventId, int $sportId, int $locationId, array $teams, int $facilitatorId, int $validatorId, string $now): void
    {
        $m1 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M1',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 1,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-16 08:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m2 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M2',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 2,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-16 09:30:00',
            'team_a_id' => $teams['CCS-CAF'],
            'team_b_id' => $teams['CIT-COC'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M3',
            'phase' => 'final',
            'bracket_side' => 'grand',
            'bracket_order' => 3,
            'feeds_from_a' => 'M1',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M2',
            'feeds_from_b_type' => 'winner',
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Championship match.',
            'match_date' => '2026-08-16 13:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['CIT-COC'],
            'status' => 'scheduled',
            'created_at' => $now,
        ]);

        $this->ensureMatchResult($eventId, $m1, $teams['CBA'], 3, [25, 20, 25, 25], $teams['SCA-CLAIM'], 1, [18, 25, 21, 19], $facilitatorId, $validatorId, '2026-08-16 09:15:00');
        $this->ensureMatchResult($eventId, $m2, $teams['CCS-CAF'], 2, [25, 18, 22, 25, 12], $teams['CIT-COC'], 3, [21, 25, 25, 19, 15], $facilitatorId, $validatorId, '2026-08-16 11:10:00');
    }

    private function seedVolleyballWomenDoubleElimination(int $eventId, int $sportId, int $locationId, array $teams, int $facilitatorId, int $validatorId, string $now): void
    {
        $m1 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Upper Semi Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M1',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 1,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M4; loser drops to M3.',
            'match_date' => '2026-08-17 08:00:00',
            'team_a_id' => $teams['CIT-COC'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m2 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Upper Semi Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M2',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 2,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M4; loser drops to M3.',
            'match_date' => '2026-08-17 09:30:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m3 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Lower Round 1',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M3',
            'phase' => 'lower_r1',
            'bracket_side' => 'lower',
            'bracket_order' => 3,
            'feeds_from_a' => 'M1',
            'feeds_from_a_type' => 'loser',
            'feeds_from_b' => 'M2',
            'feeds_from_b_type' => 'loser',
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Loser is eliminated; winner advances to M5.',
            'match_date' => '2026-08-17 11:30:00',
            'team_a_id' => $teams['SCA-CLAIM'],
            'team_b_id' => $teams['CBA'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m4 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Upper Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M4',
            'phase' => 'final',
            'bracket_side' => 'upper',
            'bracket_order' => 4,
            'feeds_from_a' => 'M1',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M2',
            'feeds_from_b_type' => 'winner',
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M6; loser drops to M5.',
            'match_date' => '2026-08-17 13:30:00',
            'team_a_id' => $teams['CIT-COC'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m5 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Lower Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M5',
            'phase' => 'final',
            'bracket_side' => 'lower',
            'bracket_order' => 5,
            'feeds_from_a' => 'M3',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M4',
            'feeds_from_b_type' => 'loser',
            'court_label' => 'Court 1',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M6; loser is eliminated.',
            'match_date' => '2026-08-17 15:30:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Grand Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M6',
            'phase' => 'final',
            'bracket_side' => 'grand',
            'bracket_order' => 6,
            'feeds_from_a' => 'M4',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M5',
            'feeds_from_b_type' => 'winner',
            'court_label' => 'Center Court',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner-bracket champion vs loser-bracket champion.',
            'match_date' => '2026-08-17 18:00:00',
            'team_a_id' => $teams['CIT-COC'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'scheduled',
            'created_at' => $now,
        ]);
        $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Bracket Reset Final',
            'tournament_format' => 'double_elimination',
            'match_code' => 'M7',
            'phase' => 'tiebreaker',
            'bracket_side' => 'grand',
            'bracket_order' => 7,
            'feeds_from_a' => 'M6',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M6',
            'feeds_from_b_type' => 'loser',
            'court_label' => 'Center Court',
            'is_conditional' => 1,
            'scheduling_note' => 'If necessary: played only if the loser-bracket finalist wins M6.',
            'match_date' => '2026-08-17 20:00:00',
            'team_a_id' => null,
            'team_b_id' => null,
            'status' => 'cancelled',
            'created_at' => $now,
        ]);

        $this->ensureMatchResult($eventId, $m1, $teams['CIT-COC'], 3, [25, 22, 25, 25], $teams['SCA-CLAIM'], 1, [18, 25, 19, 21], $facilitatorId, $validatorId, '2026-08-17 09:20:00');
        $this->ensureMatchResult($eventId, $m2, $teams['CBA'], 2, [25, 20, 22, 25, 13], $teams['CCS-CAF'], 3, [21, 25, 25, 20, 15], $facilitatorId, $validatorId, '2026-08-17 11:15:00');
        $this->ensureMatchResult($eventId, $m3, $teams['SCA-CLAIM'], 0, [19, 18, 22], $teams['CBA'], 3, [25, 25, 25], $facilitatorId, $validatorId, '2026-08-17 12:50:00');
        $this->ensureMatchResult($eventId, $m4, $teams['CIT-COC'], 3, [25, 21, 25, 25], $teams['CCS-CAF'], 1, [20, 25, 18, 22], $facilitatorId, $validatorId, '2026-08-17 15:05:00');
        $this->ensureMatchResult($eventId, $m5, $teams['CBA'], 2, [25, 20, 25, 21, 12], $teams['CCS-CAF'], 3, [22, 25, 19, 25, 15], $facilitatorId, $validatorId, '2026-08-17 17:20:00');
    }

    private function seedBadmintonSingleElimination(int $eventId, int $sportId, int $locationId, array $teams, int $facilitatorId, int $validatorId, string $now): void
    {
        $m1 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M1',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 1,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 2',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-18 08:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['CCS-CAF'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $m2 = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Semi Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M2',
            'phase' => 'semi',
            'bracket_side' => 'upper',
            'bracket_order' => 2,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Court 2',
            'is_conditional' => 0,
            'scheduling_note' => 'Winner advances to M3.',
            'match_date' => '2026-08-18 09:00:00',
            'team_a_id' => $teams['CIT-COC'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'played',
            'created_at' => $now,
        ]);
        $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Final',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M3',
            'phase' => 'final',
            'bracket_side' => 'grand',
            'bracket_order' => 3,
            'feeds_from_a' => 'M1',
            'feeds_from_a_type' => 'winner',
            'feeds_from_b' => 'M2',
            'feeds_from_b_type' => 'winner',
            'court_label' => 'Court 2',
            'is_conditional' => 0,
            'scheduling_note' => 'Championship match.',
            'match_date' => '2026-08-18 13:00:00',
            'team_a_id' => $teams['CBA'],
            'team_b_id' => $teams['SCA-CLAIM'],
            'status' => 'scheduled',
            'created_at' => $now,
        ]);

        $this->ensureMatchResult($eventId, $m1, $teams['CBA'], 2, [21, 21], $teams['CCS-CAF'], 0, [15, 18], $facilitatorId, $validatorId, '2026-08-18 08:50:00');
        $this->ensureMatchResult($eventId, $m2, $teams['CIT-COC'], 1, [21, 17, 18], $teams['SCA-CLAIM'], 2, [19, 21, 21], $facilitatorId, $validatorId, '2026-08-18 10:10:00');
    }

    private function seedCheerdanceChampionship(int $eventId, int $sportId, int $locationId, array $teams, int $facilitatorId, string $now): void
    {
        $scheduleId = $this->ensureBracketSchedule([
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Championship',
            'tournament_format' => 'single_elimination',
            'match_code' => 'M1',
            'phase' => 'final',
            'bracket_side' => 'grand',
            'bracket_order' => 1,
            'feeds_from_a' => null,
            'feeds_from_a_type' => null,
            'feeds_from_b' => null,
            'feeds_from_b_type' => null,
            'court_label' => 'Main Stage',
            'is_conditional' => 0,
            'scheduling_note' => 'Judged championship for all participating teams.',
            'match_date' => '2026-08-16 14:00:00',
            'team_a_id' => null,
            'team_b_id' => null,
            'status' => 'played',
            'created_at' => $now,
        ]);

        $resultId = $this->ensureResult([
            'event_id' => $eventId,
            'schedule_id' => $scheduleId,
            'type' => 'judged',
            'status' => 'pending',
            'submitted_by' => $facilitatorId,
            'validated_by' => null,
            'submitted_at' => '2026-08-16 16:00:00',
            'validated_at' => null,
        ]);

        $this->ensureResultEntry($resultId, $teams['SCA-CLAIM'], 94.5, 1, 0, null);
        $this->ensureResultEntry($resultId, $teams['CIT-COC'], 92, 2, 0, null);
        $this->ensureResultEntry($resultId, $teams['CBA'], 89.5, 3, 0, null);
        $this->ensureResultEntry($resultId, $teams['CCS-CAF'], 87, 4, 0, null);
    }

    private function ensureMatchResult(
        int $eventId,
        int $scheduleId,
        int $teamAId,
        float|int $teamAScore,
        ?array $teamASetScores,
        int $teamBId,
        float|int $teamBScore,
        ?array $teamBSetScores,
        int $facilitatorId,
        int $validatorId,
        string $validatedAt
    ): void {
        $resultId = $this->ensureResult([
            'event_id' => $eventId,
            'schedule_id' => $scheduleId,
            'type' => 'match',
            'status' => 'validated',
            'submitted_by' => $facilitatorId,
            'validated_by' => $validatorId,
            'submitted_at' => $validatedAt,
            'validated_at' => $validatedAt,
        ]);

        $teamAWins = $this->matchWins($teamAScore, $teamASetScores, $teamBScore, $teamBSetScores);
        $teamBWins = ! $teamAWins;
        $this->ensureResultEntry($resultId, $teamAId, $teamAScore, $teamAWins ? 1 : 2, 0, $teamASetScores);
        $this->ensureResultEntry($resultId, $teamBId, $teamBScore, $teamBWins ? 1 : 2, 0, $teamBSetScores);
        $this->removeUnexpectedResultEntries($resultId, [$teamAId, $teamBId]);
    }

    private function matchWins(float|int $teamAScore, ?array $teamASetScores, float|int $teamBScore, ?array $teamBSetScores): bool
    {
        if ($teamASetScores === null || $teamBSetScores === null) {
            return (float) $teamAScore > (float) $teamBScore;
        }

        $winsA = 0;
        $winsB = 0;
        for ($index = 0, $count = min(count($teamASetScores), count($teamBSetScores)); $index < $count; $index++) {
            if ((float) $teamASetScores[$index] > (float) $teamBSetScores[$index]) {
                $winsA++;
            } elseif ((float) $teamBSetScores[$index] > (float) $teamASetScores[$index]) {
                $winsB++;
            }
        }

        return $winsA > $winsB;
    }

    private function ensureUser(array $data, string $defaultPassword): int
    {
        $row = $this->db->table('users')
            ->select('id,password_hash,display_name,role,status')
            ->where('username', $data['username'])
            ->get()
            ->getRowArray();

        if ($row) {
            $updates = [];
            if (! password_verify($defaultPassword, (string) ($row['password_hash'] ?? ''))) {
                $updates['password_hash'] = $data['password_hash'];
            }
            foreach (['display_name', 'role', 'status'] as $field) {
                if ((string) ($row[$field] ?? '') !== (string) $data[$field]) {
                    $updates[$field] = $data[$field];
                }
            }
            if ($updates !== []) {
                $this->db->table('users')->where('id', (int) $row['id'])->update($updates);
                $this->changes++;
            }
            return (int) $row['id'];
        }

        return $this->existingOrInsert('users', null, $data);
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
        if ($row) {
            $this->db->table('locations')->where('id', (int) $row['id'])->update(['is_active' => 1]);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        return $this->existingOrInsert('locations', null, [
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSportCategory(string $name, string $now): int
    {
        $row = $this->db->table('sport_categories')->select('id')->where('name', $name)->get()->getRowArray();
        if ($row) {
            $this->db->table('sport_categories')->where('id', (int) $row['id'])->update(['is_active' => 1]);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        return $this->existingOrInsert('sport_categories', null, [
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSport(int $eventId, string $name, string $category, string $resultType, int $setCount, float|int|null $winningPoints, string $now): int
    {
        $row = $this->db->table('sports')
            ->select('id,result_type,set_count,winning_points')
            ->where('event_id', $eventId)
            ->where('name', $name)
            ->where('category', $category)
            ->get()
            ->getRowArray();

        $data = [
            'event_id' => $eventId,
            'name' => $name,
            'category' => $category,
            'result_type' => $resultType,
            'set_count' => $setCount,
            'winning_points' => $winningPoints,
            'created_at' => $now,
        ];

        if ($row) {
            $updates = [
                'result_type' => $resultType,
                'set_count' => $setCount,
                'winning_points' => $winningPoints,
            ];
            $this->db->table('sports')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        return $this->existingOrInsert('sports', null, $data);
    }

    private function ensureBracketSchedule(array $data): int
    {
        $row = $this->db->table('schedules')
            ->select('id')
            ->where('event_id', $data['event_id'])
            ->where('sport_id', $data['sport_id'])
            ->where('match_code', $data['match_code'])
            ->get()
            ->getRowArray();

        if ($row) {
            $updates = $data;
            unset($updates['event_id'], $updates['sport_id'], $updates['match_code'], $updates['created_at']);
            $this->db->table('schedules')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return (int) $row['id'];
        }

        return $this->existingOrInsert('schedules', null, $data);
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

        return $this->existingOrInsert('results', null, $data);
    }

    private function ensureResultEntry(int $resultId, int $teamId, float|int $rawScore, int $placement, float|int $allocatedPoints, ?array $setScores): void
    {
        $row = $this->db->table('result_entries')
            ->select('id')
            ->where('result_id', $resultId)
            ->where('team_id', $teamId)
            ->get()
            ->getRowArray();

        $data = [
            'result_id' => $resultId,
            'team_id' => $teamId,
            'raw_score' => $rawScore,
            'set_scores' => $setScores === null ? null : json_encode(array_values($setScores), JSON_THROW_ON_ERROR),
            'placement' => $placement,
            'allocated_points' => $allocatedPoints,
        ];

        if ($row) {
            $updates = $data;
            unset($updates['result_id'], $updates['team_id']);
            $this->db->table('result_entries')->where('id', (int) $row['id'])->update($updates);
            if ($this->db->affectedRows() > 0) {
                $this->changes++;
            }
            return;
        }

        $this->db->table('result_entries')->insert($data);
        $this->changes++;
    }

    private function removeUnexpectedResultEntries(int $resultId, array $teamIds): void
    {
        $builder = $this->db->table('result_entries')->where('result_id', $resultId);
        if ($teamIds !== []) {
            $builder->whereNotIn('team_id', array_map('intval', $teamIds));
        }
        $builder->delete();
        if ($this->db->affectedRows() > 0) {
            $this->changes++;
        }
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
