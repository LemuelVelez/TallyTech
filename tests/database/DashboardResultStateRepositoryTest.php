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
    private int $managerAId;
    private int $managerBId;
    private int $facilitatorAId;
    private int $facilitatorBId;
    private int $unassignedFacilitatorId;
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
        $this->managerAId = $this->insertUser('dashboard-manager-a', 'Manager A', 'manager', $now);
        $this->managerBId = $this->insertUser('dashboard-manager-b', 'Manager B', 'manager', $now);
        $this->facilitatorAId = $this->insertUser('dashboard-facilitator-a', 'Facilitator A', 'facilitator', $now);
        $this->facilitatorBId = $this->insertUser('dashboard-facilitator-b', 'Facilitator B', 'facilitator', $now);
        $this->unassignedFacilitatorId = $this->insertUser('dashboard-facilitator-none', 'Facilitator None', 'facilitator', $now);

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
            [$this->managerAId, $this->sportAId],
            [$this->managerAId, $this->sportBId],
            [$this->managerBId, $this->sportBId],
            [$this->facilitatorAId, $this->sportAId],
            [$this->facilitatorBId, $this->sportBId],
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

    public function testDashboardCountsApprovedForFacilitatorAndReturnedForEachRole(): void
    {
        $approvedA = $this->createPendingResult($this->sportAId, $this->facilitatorAId, 21, 15);
        $this->repository->approveResult($approvedA, $this->managerAId);
        $approvedB = $this->createPendingResult($this->sportBId, $this->facilitatorBId, 19, 12);
        $this->repository->approveResult($approvedB, $this->managerBId);

        $returnedA = $this->createPendingResult($this->sportAId, $this->facilitatorAId, 20, 16);
        $this->repository->returnResult($returnedA, 'Fix Sport A score.', $this->managerAId);
        $returnedB = $this->createPendingResult($this->sportBId, $this->facilitatorBId, 18, 11);
        $this->repository->returnResult($returnedB, 'Fix Sport B score.', $this->managerBId);

        $admin = $this->repository->dashboardCounts($this->eventId, 'admin', $this->adminId);
        $managerA = $this->repository->dashboardCounts($this->eventId, 'manager', $this->managerAId);
        $managerB = $this->repository->dashboardCounts($this->eventId, 'manager', $this->managerBId);
        $facilitatorA = $this->repository->dashboardCounts($this->eventId, 'facilitator', $this->facilitatorAId);
        $facilitatorB = $this->repository->dashboardCounts($this->eventId, 'facilitator', $this->facilitatorBId);
        $unassigned = $this->repository->dashboardCounts($this->eventId, 'facilitator', $this->unassignedFacilitatorId);

        $this->assertSame(2, $admin['approved']);
        $this->assertSame(2, $admin['returned']);
        $this->assertSame(1, $managerA['approved']);
        $this->assertSame(1, $managerA['returned']);
        $this->assertSame(1, $managerB['approved']);
        $this->assertSame(1, $managerB['returned']);
        $this->assertSame(1, $facilitatorA['approved']);
        $this->assertSame(1, $facilitatorA['returned']);
        $this->assertSame(1, $facilitatorB['approved']);
        $this->assertSame(1, $facilitatorB['returned']);
        $this->assertSame(0, $unassigned['approved']);
        $this->assertSame(0, $unassigned['returned']);
    }

    public function testPendingTmExcludesReturnedResults(): void
    {
        $this->createPendingResult($this->sportAId, $this->facilitatorAId, 21, 18);
        $returned = $this->createPendingResult($this->sportAId, $this->facilitatorAId, 17, 13);
        $this->repository->returnResult($returned, 'Please correct this entry.', $this->managerAId);

        foreach ([
            ['admin', $this->adminId],
            ['manager', $this->managerAId],
            ['facilitator', $this->facilitatorAId],
        ] as [$role, $userId]) {
            $counts = $this->repository->dashboardCounts($this->eventId, $role, $userId);
            $this->assertSame(1, $counts['pending']);
            $this->assertSame(1, $counts['returned']);
        }
    }

    public function testReturnedResultLeavesReturnedCountAfterUpdateResult(): void
    {
        $resultId = $this->createPendingResult($this->sportAId, $this->facilitatorAId, 20, 14);
        $this->repository->returnResult($resultId, 'Correct the submitted score.', $this->managerAId);

        $before = $this->repository->dashboardCounts($this->eventId, 'facilitator', $this->facilitatorAId);
        $this->assertSame(1, $before['returned']);
        $this->assertSame(0, $before['pending']);

        $this->repository->updateResult($resultId, [
            'team_a_score' => '22',
            'team_b_score' => '16',
            'notes' => 'Corrected score',
        ], $this->facilitatorAId);

        $after = $this->repository->dashboardCounts($this->eventId, 'facilitator', $this->facilitatorAId);
        $this->assertSame(0, $after['returned']);
        $this->assertSame(1, $after['pending']);

        $result = $this->db->table('results')->where('id', $resultId)->get()->getRowArray();
        $this->assertNull($result['return_note']);
        $this->assertNull($result['returned_by']);
        $this->assertNull($result['returned_at']);

        $notification = $this->db->table('notifications')
            ->where('recipient_user_id', $this->managerAId)
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

    private function createPendingResult(int $sportId, int $facilitatorId, int $scoreA, int $scoreB): int
    {
        $scheduleId = $this->insertSchedule($sportId);
        return $this->repository->createResult([
            'schedule_id' => $scheduleId,
            'team_a_score' => (string) $scoreA,
            'team_b_score' => (string) $scoreB,
            'notes' => '',
        ], $facilitatorId);
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
