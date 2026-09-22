<?php

use App\Application\Services\ReportingService;
use App\Domain\Repositories\ScoringRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ReportingServiceTest extends TestCase
{
    public function testStandingsPreservePlacementRankingOrderAndValuesIncludingZeroPointTeams(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('reportRows')
            ->with('standings', $this->callback(static fn(array $filters): bool => ($filters['status'] ?? '') === 'validated'
                && ($filters['date_from'] ?? '') === ''
                && ($filters['date_to'] ?? '') === ''))
            ->willReturn([
                ['team' => 'CBA Lions', 'firsts' => 1, 'seconds' => 1, 'thirds' => 2, 'fourths' => 0, 'points' => 27],
                ['team' => 'Zero Team', 'firsts' => 0, 'seconds' => 0, 'thirds' => 0, 'fourths' => 0, 'points' => 0],
            ]);

        $rows = (new ReportingService($repository))->report('standings', $this->filters(), $this->event());

        $this->assertSame([
            ['rank' => 1, 'team' => 'CBA Lions', 'firsts' => 1, 'seconds' => 1, 'thirds' => 2, 'fourths' => 0, 'points' => 27.0],
            ['rank' => 2, 'team' => 'Zero Team', 'firsts' => 0, 'seconds' => 0, 'thirds' => 0, 'fourths' => 0, 'points' => 0.0],
        ], $rows);
    }

    public function testMedalTallyUsesPlacementCountsFromRankingRows(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('reportRows')->willReturn([
            ['team' => 'SCJE Eagles', 'gold' => 2, 'silver' => 0, 'bronze' => 0, 'points' => 23],
        ]);

        $rows = (new ReportingService($repository))->report('medal_tally', $this->filters(), $this->event());

        $this->assertSame([
            ['team' => 'SCJE Eagles', 'gold' => 2, 'silver' => 0, 'bronze' => 0, 'total_medals' => 2, 'points' => 23.0],
        ], $rows);
    }

    public function testPerSportRankingKeepsRepositoryOrderAndResetsRankPerSport(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('reportRows')->willReturn([
            ['sport_id' => 7, 'sport' => 'Basketball', 'category' => 'Men', 'team' => 'A', 'firsts' => 1, 'seconds' => 0, 'thirds' => 0, 'fourths' => 0, 'points' => 10],
            ['sport_id' => 7, 'sport' => 'Basketball', 'category' => 'Men', 'team' => 'B', 'firsts' => 0, 'seconds' => 1, 'thirds' => 0, 'fourths' => 0, 'points' => 7],
            ['sport_id' => 8, 'sport' => 'Volleyball', 'category' => 'Women', 'team' => 'C', 'firsts' => 1, 'seconds' => 0, 'thirds' => 0, 'fourths' => 0, 'points' => 10],
        ]);

        $rows = (new ReportingService($repository))->report('sport_rankings', $this->filters(), $this->event());

        $this->assertSame([1, 2, 1], array_column($rows, 'rank'));
        $this->assertSame(['A', 'B', 'C'], array_column($rows, 'team'));
    }

    public function testPendingStatusIsPassedThroughToSyncedRankingReport(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('reportRows')
            ->with('standings', $this->callback(static fn(array $filters): bool => ($filters['status'] ?? '') === 'pending'))
            ->willReturn([]);

        $filters = $this->filters();
        $filters['status'] = 'pending';

        (new ReportingService($repository))->report('standings', $filters, $this->event());
    }

    private function filters(): array
    {
        return [
            'event_id' => 1,
            'sport_id' => 0,
            'category' => '',
            'status' => '',
            'date_range' => 'all_event',
            'from' => '',
            'to' => '',
        ];
    }

    private function event(): array
    {
        return ['id' => 1, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
    }
}
