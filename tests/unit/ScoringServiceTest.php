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
}
