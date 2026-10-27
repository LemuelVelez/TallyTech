<?php

use App\Infrastructure\Persistence\MySqlScoringRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class DashboardResultStateRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private MySqlScoringRepository $repository;
    private int $adminId;
    private int $coordinatorAId;
    private int $coordinatorBId;
    private int $tournamentManagerAId;
    private int $tournamentManagerBId;
    private int $unassignedTournamentManagerId;
    private int $eventId;
    private int $sportAId;
    private int $sportBId;
    private int $teamAId;
    private int $teamBId;
    private int $locationId;
    private int $nextScheduleNumber = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MySqlScoringRepository($this->db);
        $now = date('Y-m-d H:i:s');

        $this->adminId = $this->insertUser('dashboard-admin', 'Dashboard Admin', 'admin', $now);
        $this->coordinatorAId = $this->insertUser('dashboard-sports_coordinator-a', 'Sports Coordinator A', 'sports_coordinator', $now);
        $this->coordinatorBId = $this->insertUser('dashboard-sports_coordinator-b', 'Sports Coordinator B', 'sports_coordinator', $now);
        $this->tournamentManagerAId = $this->insertUser('dashboard-tournament_manager-a', 'Tournament Manager A', 'tournament_manager', $now);
        $this->tournamentManagerBId = $this->insertUser('dashboard-tournament_manager-b', 'Tournament Manager B', 'tournament_manager', $now);
        $this->unassignedTournamentManagerId = $this->insertUser('dashboard-tournament_manager-none', 'Tournament Manager None', 'tournament_manager', $now);

        $this->db->table('events')->insert([
            'name' => 'Dashboard Count Event',
            'year' => 2026,
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
        ]);
        $this->eventId = (int) $this->db->insertID();

        $this->db->table('teams')->insert(['name' => 'Count Team A', 'code' => 'CTA', 'created_at' => $now]);
        $this->teamAId = (int) $this->db->insertID();
        $this->db->table('teams')->insert(['name' => 'Count Team B', 'code' => 'CTB', 'created_at' => $now]);
        $this->teamBId = (int) $this->db->insertID();

        $this->db->table('locations')->insert([
            'name' => 'Dashboard Gym',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->locationId = (int) $this->db->insertID();

        $this->db->table('sport_categories')->insert([
            'name' => 'Dashboard Open',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->sportAId = $this->insertSport('Dashboard Sport A', $now);
        $this->sportBId = $this->insertSport('Dashboard Sport B', $now);

        foreach ([
            [$this->coordinatorAId, $this->sportAId],
            [$this->coordinatorAId, $this->sportBId],
            [$this->coordinatorBId, $this->sportBId],
            [$this->tournamentManagerAId, $this->sportAId],
            [$this->tournamentManagerBId, $this->sportBId],
        ] as [$userId, $sportId]) {
            $this->db->table('user_sports')->insert(['user_id' => $userId, 'sport_id' => $sportId]);
        }

        foreach ([$this->sportAId, $this->sportBId] as $sportId) {
            $this->db->table('weighted_points')->insert([
                'event_id' => $this->eventId,
                'sport_id' => $sportId,
                'first_points' => 10,
                'second_points' => 8,
                'third_points' => 6,
                'fourth_points' => 4,
                'participation_points' => 2,
                'status' => 'validated',
                'submitted_by' => $this->adminId,
                'validated_by' => $this->adminId,
                'submitted_at' => $now,
                'validated_at' => $now,
            ]);
        }
    }

    public function testDashboardCountsApprovedForTournamentManagerAndReturnedForEachRole(): void
    {
        $approvedA = $this->createPendingResult($this->sportAId, $this->tournamentManagerAId, 21, 15);
        $this->repository->approveResult($approvedA, $this->coordinatorAId);
        $approvedB = $this->createPendingResult($this->sportBId, $this->tournamentManagerBId, 19, 12);
        $this->repository->approveResult($approvedB, $this->coordinatorBId);

        $returnedA = $this->createPendingResult($this->sportAId, $this->tournamentManagerAId, 20, 16);
        $this->repository->returnResult($returnedA, 'Fix Sport A score.', $this->coordinatorAId);
        $returnedB = $this->createPendingResult($this->sportBId, $this->tournamentManagerBId, 18, 11);
        $this->repository->returnResult($returnedB, 'Fix Sport B score.', $this->coordinatorBId);

        $admin = $this->repository->dashboardCounts($this->eventId, 'admin', $this->adminId);
        $coordinatorA = $this->repository->dashboardCounts($this->eventId, 'sports_coordinator', $this->coordinatorAId);
        $coordinatorB = $this->repository->dashboardCounts($this->eventId, 'sports_coordinator', $this->coordinatorBId);
        $tournamentManagerA = $this->repository->dashboardCounts($this->eventId, 'tournament_manager', $this->tournamentManagerAId);
        $tournamentManagerB = $this->repository->dashboardCounts($this->eventId, 'tournament_manager', $this->tournamentManagerBId);
        $unassigned = $this->repository->dashboardCounts($this->eventId, 'tournament_manager', $this->unassignedTournamentManagerId);

        $this->assertSame(2, $admin['approved']);
        $this->assertSame(2, $admin['returned']);
        $this->assertSame(1, $coordinatorA['approved']);
        $this->assertSame(1, $coordinatorA['returned']);
        $this->assertSame(1, $coordinatorB['approved']);
        $this->assertSame(1, $coordinatorB['returned']);
        $this->assertSame(1, $tournamentManagerA['approved']);
        $this->assertSame(1, $tournamentManagerA['returned']);
        $this->assertSame(1, $tournamentManagerB['approved']);
        $this->assertSame(1, $tournamentManagerB['returned']);
        $this->assertSame(0, $unassigned['approved']);
        $this->assertSame(0, $unassigned['returned']);
    }

    public function testPendingTmExcludesReturnedResults(): void
    {
        $this->createPendingResult($this->sportAId, $this->tournamentManagerAId, 21, 18);
        $returned = $this->createPendingResult($this->sportAId, $this->tournamentManagerAId, 17, 13);
        $this->repository->returnResult($returned, 'Please correct this entry.', $this->coordinatorAId);

        foreach ([
            ['admin', $this->adminId],
            ['sports_coordinator', $this->coordinatorAId],
            ['tournament_manager', $this->tournamentManagerAId],
        ] as [$role, $userId]) {
            $counts = $this->repository->dashboardCounts($this->eventId, $role, $userId);
            $this->assertSame(1, $counts['pending']);
            $this->assertSame(1, $counts['returned']);
        }
    }

    public function testReturnedResultLeavesReturnedCountAfterUpdateResult(): void
    {
        $resultId = $this->createPendingResult($this->sportAId, $this->tournamentManagerAId, 20, 14);
        $this->repository->returnResult($resultId, 'Correct the submitted score.', $this->coordinatorAId);

        $before = $this->repository->dashboardCounts($this->eventId, 'tournament_manager', $this->tournamentManagerAId);
        $this->assertSame(1, $before['returned']);
        $this->assertSame(0, $before['pending']);

        $this->repository->updateResult($resultId, [
            'team_a_score' => '22',
            'team_b_score' => '16',
            'notes' => 'Corrected score',
        ], $this->tournamentManagerAId);

        $after = $this->repository->dashboardCounts($this->eventId, 'tournament_manager', $this->tournamentManagerAId);
        $this->assertSame(0, $after['returned']);
        $this->assertSame(1, $after['pending']);

        $result = $this->db->table('results')->where('id', $resultId)->get()->getRowArray();
        $this->assertNull($result['return_note']);
        $this->assertNull($result['returned_by']);
        $this->assertNull($result['returned_at']);

        $notification = $this->db->table('notifications')
            ->where('recipient_user_id', $this->coordinatorAId)
            ->where('action', 'result_resubmitted')
            ->get()->getRowArray();
        $this->assertNotNull($notification);
    }

    private function insertUser(string $username, string $displayName, string $role, string $now): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'password_hash' => password_hash('Password@123', PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role,
            'status' => 'active',
            'created_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function insertSport(string $name, string $now): int
    {
        $this->db->table('sports')->insert([
            'event_id' => $this->eventId,
            'name' => $name,
            'category' => 'Dashboard Open',
            'result_type' => 'match',
            'set_count' => 1,
            'winning_points' => null,
            'created_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function createPendingResult(int $sportId, int $tournamentManagerId, int $scoreA, int $scoreB): int
    {
        $scheduleId = $this->insertSchedule($sportId);
        return $this->repository->createResult([
            'schedule_id' => $scheduleId,
            'team_a_score' => (string) $scoreA,
            'team_b_score' => (string) $scoreB,
            'notes' => '',
        ], $tournamentManagerId);
    }

    private function insertSchedule(int $sportId): int
    {
        $number = $this->nextScheduleNumber++;
        $this->db->table('schedules')->insert([
            'event_id' => $this->eventId,
            'sport_id' => $sportId,
            'location_id' => $this->locationId,
            'round' => 'Elimination',
            'tournament_format' => 'single_elimination',
            'match_date' => sprintf('2026-10-%02d 10:00:00', min(28, $number)),
            'team_a_id' => $this->teamAId,
            'team_b_id' => $this->teamBId,
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s'),
            'match_code' => 'M' . $number,
            'phase' => 'playoff',
            'bracket_side' => 'upper',
            'bracket_order' => $number,
            'is_conditional' => 0,
        ]);
        return (int) $this->db->insertID();
    }
}
