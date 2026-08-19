<?php

use App\Infrastructure\Persistence\MySqlScoringRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ReferenceDataRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private MySqlScoringRepository $repository;
    private int $adminId;
    private int $eventId;
    private int $teamAId;
    private int $teamBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MySqlScoringRepository($this->db);
        $now = date('Y-m-d H:i:s');

        $this->db->table('users')->insert([
            'username' => 'reference-admin',
            'password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
            'display_name' => 'Reference Admin',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => $now,
        ]);
        $this->adminId = (int) $this->db->insertID();

        $this->db->table('events')->insert([
            'name' => 'Reference Event',
            'year' => 2026,
            'status' => 'active',
            'is_active' => 1,
            'created_at' => $now,
        ]);
        $this->eventId = (int) $this->db->insertID();

        $this->db->table('teams')->insert(['name' => 'Team Alpha', 'code' => 'ALPHA', 'created_at' => $now]);
        $this->teamAId = (int) $this->db->insertID();
        $this->db->table('teams')->insert(['name' => 'Team Beta', 'code' => 'BETA', 'created_at' => $now]);
        $this->teamBId = (int) $this->db->insertID();
    }

    public function testLocationCrudPreventsDuplicatesAndSupportsDisabling(): void
    {
        $locationId = $this->repository->createLocation('University Oval', $this->adminId);

        $this->assertSame('University Oval', $this->repository->locations()[0]['name']);

        try {
            $this->repository->createLocation(' university oval ', $this->adminId);
            $this->fail('Expected duplicate location validation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        $this->repository->updateLocation($locationId, 'Main Oval', $this->adminId);
        $this->repository->setLocationActive($locationId, false, $this->adminId);

        $this->assertSame([], $this->repository->locations());
        $all = $this->repository->allLocations();
        $this->assertSame('Main Oval', $all[0]['name']);
        $this->assertSame(0, (int) $all[0]['is_active']);

        $this->repository->deleteLocation($locationId, $this->adminId);
        $this->assertSame([], $this->repository->allLocations());
    }

    public function testInactiveLocationCannotBeUsedForNewScheduleButHistoricalReferenceIsPreserved(): void
    {
        $categoryId = $this->repository->createSportCategory('Open', $this->adminId);
        $sportId = $this->repository->createSport([
            'event_id' => $this->eventId,
            'name' => 'Basketball',
            'category_id' => $categoryId,
            'result_type' => 'match',
            'created_at' => date('Y-m-d H:i:s'),
        ], $this->adminId);
        $locationId = $this->repository->createLocation('Main Court', $this->adminId);

        $scheduleData = [
            'event_id' => $this->eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Final',
            'match_date' => '2026-08-20 10:00:00',
            'team_a_id' => $this->teamAId,
            'team_b_id' => $this->teamBId,
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->repository->createSchedule($scheduleData, $this->adminId);
        $this->repository->setLocationActive($locationId, false, $this->adminId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Selected location is inactive.');
        $this->repository->createSchedule(array_merge($scheduleData, ['match_date' => '2026-08-20 12:00:00']), $this->adminId);

    }

    public function testDisabledLocationCanRemainOnExistingScheduleAndCannotBeDeletedWhileReferenced(): void
    {
        $categoryId = $this->repository->createSportCategory('Mixed Open', $this->adminId);
        $sportId = $this->repository->createSport([
            'event_id' => $this->eventId,
            'name' => 'Volleyball',
            'category_id' => $categoryId,
            'result_type' => 'match',
            'created_at' => date('Y-m-d H:i:s'),
        ], $this->adminId);
        $locationId = $this->repository->createLocation('Covered Court', $this->adminId);
        $scheduleData = [
            'event_id' => $this->eventId,
            'sport_id' => $sportId,
            'location_id' => $locationId,
            'round' => 'Elimination',
            'match_date' => '2026-08-21 10:00:00',
            'team_a_id' => $this->teamAId,
            'team_b_id' => $this->teamBId,
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $scheduleId = $this->repository->createSchedule($scheduleData, $this->adminId);
        $this->repository->setLocationActive($locationId, false, $this->adminId);

        $this->repository->updateSchedule($scheduleId, array_merge($scheduleData, ['round' => 'Semi-final']), $this->adminId);
        $this->assertSame('Covered Court', $this->repository->schedules($this->eventId)[0]['location_name']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Disable it instead.');
        $this->repository->deleteLocation($locationId, $this->adminId);
    }

    public function testSportCategoriesAreDatabaseDrivenAndInactiveCategoriesAreRejectedForNewSports(): void
    {
        $categoryId = $this->repository->createSportCategory('Open', $this->adminId);
        $sportId = $this->repository->createSport([
            'event_id' => $this->eventId,
            'name' => 'Badminton',
            'category_id' => $categoryId,
            'result_type' => 'match',
            'created_at' => date('Y-m-d H:i:s'),
        ], $this->adminId);

        $this->repository->setSportCategoryActive($categoryId, false, $this->adminId);
        $this->repository->updateSport($sportId, [
            'event_id' => $this->eventId,
            'name' => 'Badminton',
            'category_id' => $categoryId,
            'result_type' => 'match',
        ], $this->adminId);
        $this->repository->updateSportCategory($categoryId, 'Open Division', $this->adminId);

        $sport = $this->repository->sports($this->eventId)[0];
        $this->assertSame('Open Division', $sport['category']);
        $this->assertSame(0, (int) $this->repository->sportCategory($categoryId)['is_active']);

        try {
            $this->repository->createSport([
                'event_id' => $this->eventId,
                'name' => 'Table Tennis',
                'category_id' => $categoryId,
                'result_type' => 'match',
                'created_at' => date('Y-m-d H:i:s'),
            ], $this->adminId);
            $this->fail('Expected inactive sport category validation to fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inactive', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Disable it instead.');
        $this->repository->deleteSportCategory($categoryId, $this->adminId);
    }
}
