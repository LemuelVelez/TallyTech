<?php

use App\Application\Services\ScoringService;
use App\Domain\Repositories\ScoringRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ScoringServiceTest extends TestCase
{
    public function testCommonDataUsesExplicitEmptyEventScopeWhenNoEventIsActive(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('activeEvent')->willReturn(null);
        $repository->expects($this->once())->method('sports')->with(0)->willReturn([]);
        $repository->expects($this->once())->method('teams')->willReturn([]);
        $repository->expects($this->once())->method('locations')->willReturn([]);

        $data = (new ScoringService($repository))->commonData();

        $this->assertNull($data['activeEvent']);
        $this->assertSame([], $data['sports']);
    }

    public function testScoreboardDoesNotFallBackToHistoricalDataWithoutActiveEvent(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('activeEvent')->willReturn(null);
        $repository->expects($this->once())->method('sports')->with(0)->willReturn([]);
        $repository->expects($this->never())->method('resultsByStatus');
        $repository->expects($this->never())->method('schedules');
        $repository->expects($this->never())->method('rankingBySport');

        $data = (new ScoringService($repository))->scoreboard();

        $this->assertNull($data['activeEvent']);
        $this->assertSame([], $data['officialScoreboard']['results']);
        $this->assertSame([], $data['officialScoreboard']['standings']);
        $this->assertSame([], $data['officialScoreboard']['overallSportPoints']);
        $this->assertSame([], $data['officialScoreboard']['schedules']);
        $this->assertSame([], $data['unofficialScoreboard']['results']);
        $this->assertSame([], $data['unofficialScoreboard']['standings']);
        $this->assertSame([], $data['unofficialScoreboard']['overallSportPoints']);
        $this->assertSame([], $data['unofficialScoreboard']['schedules']);
    }

    public function testScoreboardDefaultsToOverallWithoutSelectingTheFirstSport(): void
    {
        $event = ['id' => 17, 'name' => 'ISF 2026'];
        $sports = [
            ['id' => 7, 'name' => 'Baseball', 'category' => 'Men'],
            ['id' => 8, 'name' => 'Badminton', 'category' => 'Men'],
        ];
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('activeEvent')->willReturn($event);
        $repository->method('sports')->with(17)->willReturn($sports);
        $repository->method('ranking')->with(17, false)->willReturn([]);
        $repository->method('rankingByStatus')->with(17, 'pending', true)->willReturn([]);

        $data = (new ScoringService($repository))->scoreboard();

        $this->assertTrue($data['isOverall']);
        $this->assertNull($data['selectedSport']);
        $this->assertSame([], $data['selectedSportIds']);
        $this->assertNull($data['sportScoreTable']['selectedSport']);
        $this->assertSame([], $data['sportScoreTable']['selectedSportIds']);
    }

    public function testScoreboardHonorsAnExplicitSportId(): void
    {
        $event = ['id' => 17, 'name' => 'ISF 2026'];
        $sports = [
            ['id' => 7, 'name' => 'Baseball', 'category' => 'Men'],
            ['id' => 8, 'name' => 'Badminton', 'category' => 'Men'],
        ];
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('activeEvent')->willReturn($event);
        $repository->method('sports')->with(17)->willReturn($sports);
        $repository->method('resultsByStatus')->willReturn([]);
        $repository->method('rankingBySport')->willReturn([]);
        $repository->method('schedules')->with(17)->willReturn([]);
        $repository->method('resolveBracketSlots')->willReturn([]);
        $repository->method('ranking')->with(17, false)->willReturn([]);
        $repository->method('rankingByStatus')->with(17, 'pending', true)->willReturn([]);

        $data = (new ScoringService($repository))->scoreboard(7);

        $this->assertFalse($data['isOverall']);
        $this->assertSame('Baseball', $data['selectedSport']['name']);
        $this->assertSame([7], $data['selectedSportIds']);
    }

    public function testDashboardScopesEveryEventSpecificCollectionToActiveEvent(): void
    {
        $event = ['id' => 17, 'name' => 'ISF 2026'];
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('activeEvent')->willReturn($event);
        $repository->expects($this->once())->method('ranking')->with(17)->willReturn([]);
        $repository->expects($this->once())->method('results')->with(17)->willReturn([]);
        $repository->expects($this->once())->method('schedules')->with(17)->willReturn([]);
        $repository->expects($this->once())->method('sports')->with(17)->willReturn([]);
        $repository->expects($this->once())->method('weightedPoints')->with(17)->willReturn([]);
        $repository->method('teams')->willReturn([]);
        $repository->method('notifications')->willReturn([]);

        $data = (new ScoringService($repository))->dashboard('manager');

        $this->assertSame($event, $data['activeEvent']);
        $this->assertSame('manager', $data['role']);
    }

    public function testDoubleEliminationAwardsThirdAndFourthBeforeGrandFinal(): void
    {
        $results = [
            $this->matchResult(31, 9, 'double_elimination', 'M3', 'lower_r1', 'lower', 1, 4, [21, 21], [12, 14]),
            $this->matchResult(35, 9, 'double_elimination', 'M5', 'final', 'lower', 2, 3, [21, 18, 21], [18, 21, 17]),
        ];

        $placements = ScoringService::resolveTournamentPlacements($results);

        $this->assertSame(4, $placements[31][4]);
        $this->assertSame(3, $placements[35][3]);
        $this->assertArrayNotHasKey(1, $placements[31] ?? []);
        $this->assertArrayNotHasKey(2, $placements[35] ?? []);
    }

    public function testSingleEliminationRanksSemifinalLosersWhenThereIsNoThirdPlacePlayoff(): void
    {
        $results = [
            $this->matchResult(11, 5, 'single_elimination', 'M1', 'semi', 'upper', 2, 4, [21, 21], [15, 18]),
            $this->matchResult(12, 5, 'single_elimination', 'M2', 'semi', 'upper', 1, 3, [21, 17, 21], [18, 21, 18]),
            $this->matchResult(13, 5, 'single_elimination', 'M3', 'final', 'grand', 1, 2, [21, 21], [17, 19]),
        ];

        $placements = ScoringService::resolveTournamentPlacements($results);

        $this->assertSame(1, $placements[13][1]);
        $this->assertSame(2, $placements[13][2]);
        $this->assertSame(3, $placements[12][3]);
        $this->assertSame(4, $placements[11][4]);
    }

    public function testSingleEliminationUsesThirdPlacePlayoffWhenConfigured(): void
    {
        $results = [
            $this->matchResult(14, 5, 'single_elimination', 'M1', 'semi', 'upper', 2, 4, [21, 21], [15, 18]),
            $this->matchResult(15, 5, 'single_elimination', 'M2', 'semi', 'upper', 1, 3, [21, 17, 21], [18, 21, 18]),
            $this->matchResult(16, 5, 'single_elimination', 'M3', 'third_place', 'lower', 3, 4, [21, 21], [18, 16]),
        ];

        $placements = ScoringService::resolveTournamentPlacements($results, [5 => true]);

        $this->assertSame(3, $placements[16][3]);
        $this->assertSame(4, $placements[16][4]);
        $this->assertArrayNotHasKey(4, $placements[14] ?? []);
        $this->assertArrayNotHasKey(3, $placements[15] ?? []);
    }

    public function testSingleEliminationFlagsUnresolvedSemifinalLoserTie(): void
    {
        $results = [
            $this->matchResult(21, 6, 'single_elimination', 'M1', 'semi', 'upper', 1, 3, [21, 21], [18, 19]),
            $this->matchResult(22, 6, 'single_elimination', 'M2', 'semi', 'upper', 2, 4, [21, 21], [18, 19]),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('semifinal losers are still tied');

        ScoringService::resolveTournamentPlacements($results, [], true);
    }

    public function testJudgedDuplicateKeepsScheduleWithValidatedResult(): void
    {
        $schedules = [
            ['id' => 51, 'match_code' => 'M1'],
            ['id' => 52, 'match_code' => null],
        ];
        $resultsBySchedule = [
            51 => ['id' => 61, 'status' => 'pending', 'validated_at' => null],
            52 => ['id' => 62, 'status' => 'validated', 'validated_at' => '2026-08-19 10:54:00'],
        ];

        $this->assertSame(52, ScoringService::canonicalJudgedScheduleId($schedules, $resultsBySchedule));
    }

    public function testJudgedOfficialPlacementsRemainCreditEligible(): void
    {
        $results = [[
            'id' => 41,
            'sport_id' => 12,
            'type' => 'judged',
            'result_type' => 'judged',
            'status' => 'validated',
            'entries' => [
                ['team_id' => 1, 'raw_score' => 94.50, 'placement' => 1],
                ['team_id' => 3, 'raw_score' => 92.00, 'placement' => 2],
                ['team_id' => 2, 'raw_score' => 89.50, 'placement' => 3],
                ['team_id' => 4, 'raw_score' => 87.00, 'placement' => 4],
            ],
        ]];

        $placements = ScoringService::resolveTournamentPlacements($results);

        $this->assertSame([1 => 1, 3 => 2, 2 => 3, 4 => 4], $placements[41]);
    }

    private function matchResult(
        int $id,
        int $sportId,
        string $format,
        string $matchCode,
        string $phase,
        string $side,
        int $teamA,
        int $teamB,
        array $teamASets,
        array $teamBSets
    ): array {
        return [
            'id' => $id,
            'sport_id' => $sportId,
            'type' => 'match',
            'result_type' => 'match',
            'status' => 'validated',
            'tournament_format' => $format,
            'match_code' => $matchCode,
            'phase' => $phase,
            'bracket_side' => $side,
            'team_a_id' => $teamA,
            'team_b_id' => $teamB,
            'entries' => [
                [
                    'team_id' => $teamA,
                    'raw_score' => array_sum($teamASets),
                    'set_scores' => json_encode($teamASets, JSON_THROW_ON_ERROR),
                ],
                [
                    'team_id' => $teamB,
                    'raw_score' => array_sum($teamBSets),
                    'set_scores' => json_encode($teamBSets, JSON_THROW_ON_ERROR),
                ],
            ],
        ];
    }
}
