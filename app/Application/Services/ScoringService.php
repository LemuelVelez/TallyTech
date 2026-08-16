<?php

namespace App\Application\Services;

use App\Domain\Repositories\ScoringRepositoryInterface;
use RuntimeException;

class ScoringService
{
    public function __construct(private ScoringRepositoryInterface $repository) {}

    public function commonData(): array
    {
        $event = $this->repository->activeEvent();
        $eventId = (int) ($event['id'] ?? 0);
        return ['activeEvent'=>$event,'teams'=>$this->repository->teams(),'sports'=>$this->repository->sports($eventId),'locations'=>$this->repository->locations()];
    }

    public function dashboard(string $role): array
    {
        $event = $this->repository->activeEvent(); $eventId=(int)($event['id']??0);
        $ranking=$this->repository->ranking($eventId);
        $results=$this->repository->results($eventId);
        $schedules=$this->repository->schedules($eventId);
        return [
            'activeEvent'=>$event, 'ranking'=>$ranking, 'results'=>$results, 'schedules'=>$schedules,
            'teams'=>$this->repository->teams(), 'sports'=>$this->repository->sports($eventId),
            'notifications'=>$this->repository->notifications(5), 'weightedPoints'=>$this->repository->weightedPoints($eventId), 'role'=>$role,
        ];
    }

    public function scoreboard(): array
    {
        $event=$this->repository->activeEvent(); $eventId=(int)($event['id']??0);
        $results=$this->repository->results($eventId);
        $schedules=$this->repository->schedules($eventId);
        usort($schedules, static fn(array $a, array $b): int => strcmp((string) $a['match_date'], (string) $b['match_date']));
        return ['activeEvent'=>$event,'ranking'=>$this->repository->ranking($eventId),'results'=>$results,'schedules'=>$schedules];
    }

    public function saveResult(array $data, int $actorId): int
    {
        if (empty($data['schedule_id'])) throw new RuntimeException('Select a schedule.');
        return $this->repository->createResult($data, $actorId);
    }
}
