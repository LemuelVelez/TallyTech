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

    public function scoreboard(?int $requestedSportId = null): array
    {
        $event = $this->repository->activeEvent();
        $eventId = (int) ($event['id'] ?? 0);
        $sports = $this->repository->sports($eventId);
        $selectedSport = null;

        foreach ($sports as $sport) {
            if ($requestedSportId !== null && (int) $sport['id'] === $requestedSportId) {
                $selectedSport = $sport;
                break;
            }
        }
        $selectedSport ??= $sports[0] ?? null;
        $sportId = (int) ($selectedSport['id'] ?? 0);

        if (! $eventId || ! $sportId) {
            return [
                'activeEvent' => $event,
                'sports' => $sports,
                'selectedSport' => $selectedSport,
                'ranking' => [],
                'results' => [],
                'schedules' => [],
            ];
        }

        $results = array_values(array_filter(
            $this->repository->results($eventId),
            static fn(array $result): bool => (int) $result['sport_id'] === $sportId
        ));
        $schedules = array_values(array_filter(
            $this->repository->resolveBracketSlots($this->repository->schedules($eventId)),
            static fn(array $schedule): bool => (int) $schedule['sport_id'] === $sportId
        ));
        usort($schedules, static fn(array $a, array $b): int => ((int) ($a['bracket_order'] ?? 0) <=> (int) ($b['bracket_order'] ?? 0)) ?: strcmp((string) $a['match_date'], (string) $b['match_date']));

        $resultBySchedule = [];
        foreach ($results as $result) {
            $resultBySchedule[(int) $result['schedule_id']] = $result;
        }
        foreach ($schedules as &$schedule) {
            $result = $resultBySchedule[(int) $schedule['id']] ?? null;
            $schedule['result_status'] = $result['status'] ?? null;
            $schedule['result_entries'] = $result['entries'] ?? [];
            $schedule['score_by_team'] = [];
            foreach ($schedule['result_entries'] as $entry) {
                $schedule['score_by_team'][(int) $entry['team_id']] = $entry['raw_score'];
            }
            $schedule['winner_name'] = null;
            $schedule['loser_name'] = null;
            if ($result && ($result['status'] ?? '') === 'validated' && ($result['type'] ?? '') === 'match' && count($result['entries'] ?? []) === 2) {
                $entries = $result['entries'];
                usort($entries, static fn(array $a, array $b): int => (float) $b['raw_score'] <=> (float) $a['raw_score']);
                if ((float) $entries[0]['raw_score'] !== (float) $entries[1]['raw_score']) {
                    $schedule['winner_name'] = $entries[0]['team_name'];
                    $schedule['loser_name'] = $entries[1]['team_name'];
                }
            }
        }
        unset($schedule);

        return [
            'activeEvent' => $event,
            'sports' => $sports,
            'selectedSport' => $selectedSport,
            'ranking' => $this->repository->rankingBySport($eventId, $sportId),
            'results' => $results,
            'schedules' => $schedules,
        ];
    }

    public function saveResult(array $data, int $actorId): int
    {
        if (empty($data['schedule_id'])) throw new RuntimeException('Select a schedule.');
        return $this->repository->createResult($data, $actorId);
    }
}
