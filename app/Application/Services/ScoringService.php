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
        return [
            'activeEvent' => $event,
            'teams' => $this->repository->teams(),
            'sports' => $this->repository->sports($eventId),
            'locations' => $this->repository->locations(),
        ];
    }

    public function dashboard(string $role): array
    {
        $event = $this->repository->activeEvent();
        $eventId = (int) ($event['id'] ?? 0);
        return [
            'activeEvent' => $event,
            'ranking' => $this->repository->ranking($eventId),
            'results' => $this->repository->results($eventId),
            'schedules' => $this->repository->schedules($eventId),
            'teams' => $this->repository->teams(),
            'sports' => $this->repository->sports($eventId),
            'notifications' => $this->repository->notifications(5),
            'weightedPoints' => $this->repository->weightedPoints($eventId),
            'role' => $role,
        ];
    }

    public function sportScores(?int $requestedSportId = null): array
    {
        $event = $this->repository->activeEvent();
        $eventId = (int) ($event['id'] ?? 0);
        $sports = $this->repository->sports($eventId);
        $table = $this->buildSportScoreTable($eventId, $sports, $requestedSportId);

        return [
            'activeEvent' => $event,
            'sports' => $sports,
            'selectedSport' => $table['selectedSport'],
            'selectedSportIds' => $table['selectedSportIds'],
            'sportScoreTable' => $table,
            'ranking' => $table['overallPoints'],
        ];
    }

    public function scoreboard(?int $requestedSportId = null): array
    {
        $data = $this->sportScores($requestedSportId);
        $event = $data['activeEvent'];
        $eventId = (int) ($event['id'] ?? 0);
        $selectedSport = $data['selectedSport'];
        $selectedSportIds = $data['selectedSportIds'];

        if (! $eventId || ! $selectedSportIds) {
            return $data + [
                'results' => [],
                'schedules' => [],
            ];
        }

        $results = array_values(array_filter(
            $this->repository->results($eventId),
            static fn(array $result): bool => in_array((int) $result['sport_id'], $selectedSportIds, true)
        ));
        $schedules = array_values(array_filter(
            $this->repository->resolveBracketSlots($this->repository->schedules($eventId)),
            static fn(array $schedule): bool => in_array((int) $schedule['sport_id'], $selectedSportIds, true)
        ));
        usort($schedules, static fn(array $a, array $b): int => ((int) ($a['sport_id'] ?? 0) <=> (int) ($b['sport_id'] ?? 0))
            ?: (((int) ($a['bracket_order'] ?? 0) <=> (int) ($b['bracket_order'] ?? 0))
            ?: strcmp((string) $a['match_date'], (string) $b['match_date'])));

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
            if ($result && ($result['status'] ?? '') === 'validated' && ($result['type'] ?? '') === 'match') {
                $outcome = $this->matchOutcome($result['entries'] ?? []);
                if ($outcome !== null) {
                    $schedule['winner_name'] = $outcome['winner']['team_name'] ?? null;
                    $schedule['loser_name'] = $outcome['loser']['team_name'] ?? null;
                }
            }
        }
        unset($schedule);

        $data['selectedSport'] = $selectedSport;
        $data['results'] = $results;
        $data['schedules'] = $schedules;
        return $data;
    }

    public function saveResult(array $data, int $actorId): int
    {
        if (empty($data['schedule_id'])) {
            throw new RuntimeException('Select a schedule.');
        }
        return $this->repository->createResult($data, $actorId);
    }

    private function buildSportScoreTable(int $eventId, array $sports, ?int $requestedSportId): array
    {
        $groups = [];
        foreach ($sports as $sport) {
            $name = (string) ($sport['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if (! isset($groups[$name])) {
                $groups[$name] = [
                    'id' => (int) $sport['id'],
                    'name' => $name,
                    'sport_ids' => [],
                ];
            }
            $groups[$name]['sport_ids'][] = (int) $sport['id'];
        }
        $sportGroups = array_values($groups);
        usort($sportGroups, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $selectedName = null;
        if ($requestedSportId !== null) {
            foreach ($sports as $sport) {
                if ((int) $sport['id'] === $requestedSportId) {
                    $selectedName = (string) $sport['name'];
                    break;
                }
            }
        }
        $selectedName ??= (string) ($sportGroups[0]['name'] ?? '');

        $selectedSports = array_values(array_filter(
            $sports,
            static fn(array $sport): bool => (string) ($sport['name'] ?? '') === $selectedName
        ));
        $categoryOrder = static fn(string $category): int => match (strtolower($category)) {
            'men' => 0,
            'women' => 1,
            'mixed' => 2,
            default => 3,
        };
        usort($selectedSports, static function (array $a, array $b) use ($categoryOrder): int {
            $order = $categoryOrder((string) ($a['category'] ?? '')) <=> $categoryOrder((string) ($b['category'] ?? ''));
            return $order ?: strcasecmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''));
        });

        $selectedSportIds = array_map('intval', array_column($selectedSports, 'id'));
        $selectedSport = $selectedSports[0] ?? null;
        $maxSetCount = 1;
        foreach ($selectedSports as $sport) {
            $maxSetCount = max($maxSetCount, max(1, min(9, (int) ($sport['set_count'] ?? 1))));
        }

        if (! $eventId || ! $selectedSportIds) {
            return [
                'sportGroups' => $sportGroups,
                'selectedSport' => $selectedSport,
                'selectedSportIds' => $selectedSportIds,
                'maxSetCount' => $maxSetCount,
                'categories' => [],
                'thresholds' => [],
                'overallPoints' => [],
            ];
        }

        $allSchedules = $this->repository->schedules($eventId);
        $allResults = $this->repository->results($eventId);
        $categories = [];
        $thresholds = [];

        foreach ($selectedSports as $sport) {
            $sportId = (int) $sport['id'];
            $category = (string) ($sport['category'] ?? '');
            $resultType = (string) ($sport['result_type'] ?? 'match');
            $setCount = max(1, min(9, (int) ($sport['set_count'] ?? 1)));
            $winningPoints = ($sport['winning_points'] ?? null) === null ? null : (float) $sport['winning_points'];
            $thresholds[] = [
                'category' => $category,
                'winning_points' => $winningPoints,
                'result_type' => $resultType,
            ];

            $participants = [];
            foreach ($allSchedules as $schedule) {
                if ((int) $schedule['sport_id'] !== $sportId) {
                    continue;
                }
                foreach (['a', 'b'] as $slot) {
                    $teamId = (int) ($schedule['team_' . $slot . '_id'] ?? 0);
                    $teamName = (string) ($schedule['team_' . $slot . '_name'] ?? '');
                    if ($teamId > 0 && $teamName !== '') {
                        $participants[$teamId] = $teamName;
                    }
                }
            }

            $latestByTeam = [];
            foreach ($allResults as $result) {
                if ((int) $result['sport_id'] !== $sportId) {
                    continue;
                }
                $outcome = $resultType === 'match' ? $this->matchOutcome($result['entries'] ?? []) : null;
                $winnerId = $outcome === null ? 0 : (int) $outcome['winner']['team_id'];
                $loserId = $outcome === null ? 0 : (int) $outcome['loser']['team_id'];
                foreach ($result['entries'] ?? [] as $entry) {
                    $teamId = (int) ($entry['team_id'] ?? 0);
                    if ($teamId < 1) {
                        continue;
                    }
                    $participants[$teamId] = (string) ($entry['team_name'] ?? ($participants[$teamId] ?? 'Team'));
                    if (isset($latestByTeam[$teamId])) {
                        continue;
                    }
                    $sets = $resultType === 'match'
                        ? $this->entrySetScores($entry)
                        : [(float) ($entry['raw_score'] ?? 0)];
                    $latestByTeam[$teamId] = [
                        'sets' => array_slice($sets, 0, $setCount),
                        'status' => $teamId === $winnerId ? 'Win' : ($teamId === $loserId ? 'Loss' : ''),
                        'match_code' => (string) ($result['match_code'] ?? ''),
                    ];
                }
            }

            $rows = [];
            foreach ($participants as $teamId => $teamName) {
                $latest = $latestByTeam[$teamId] ?? ['sets' => [], 'status' => '', 'match_code' => ''];
                $rows[] = [
                    'team_id' => (int) $teamId,
                    'team_name' => $teamName,
                    'set_scores' => $latest['sets'],
                    'status' => $latest['status'],
                    'overall_points' => array_sum(array_map('floatval', $latest['sets'])),
                    'match_code' => $latest['match_code'],
                ];
            }
            usort($rows, static fn(array $a, array $b): int => strcasecmp($a['team_name'], $b['team_name']));

            $categories[] = [
                'category' => $category,
                'sport_id' => $sportId,
                'set_count' => $setCount,
                'winning_points' => $winningPoints,
                'result_type' => $resultType,
                'rows' => $rows,
            ];
        }

        return [
            'sportGroups' => $sportGroups,
            'selectedSport' => $selectedSport,
            'selectedSportIds' => $selectedSportIds,
            'maxSetCount' => $maxSetCount,
            'categories' => $categories,
            'thresholds' => $thresholds,
            'overallPoints' => $this->combinedSportRanking($eventId, $selectedSportIds),
        ];
    }

    private function combinedSportRanking(int $eventId, array $sportIds): array
    {
        $combined = [];
        foreach ($sportIds as $sportId) {
            foreach ($this->repository->rankingBySport($eventId, (int) $sportId) as $row) {
                $teamId = (int) $row['id'];
                if (! isset($combined[$teamId])) {
                    $combined[$teamId] = [
                        'id' => $teamId,
                        'name' => $row['name'],
                        'code' => $row['code'],
                        'total_points' => 0.0,
                        'firsts' => 0,
                        'seconds' => 0,
                        'thirds' => 0,
                    ];
                }
                $combined[$teamId]['total_points'] += (float) ($row['total_points'] ?? 0);
                $combined[$teamId]['firsts'] += (int) ($row['firsts'] ?? 0);
                $combined[$teamId]['seconds'] += (int) ($row['seconds'] ?? 0);
                $combined[$teamId]['thirds'] += (int) ($row['thirds'] ?? 0);
            }
        }

        $rows = array_values($combined);
        usort($rows, static fn(array $a, array $b): int => ((float) $b['total_points'] <=> (float) $a['total_points'])
            ?: ((int) $b['firsts'] <=> (int) $a['firsts'])
            ?: ((int) $b['seconds'] <=> (int) $a['seconds'])
            ?: strcasecmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }

    private function matchOutcome(array $entries): ?array
    {
        if (count($entries) !== 2) {
            return null;
        }
        $setsA = $this->entrySetScores($entries[0]);
        $setsB = $this->entrySetScores($entries[1]);
        $winsA = 0;
        $winsB = 0;
        for ($index = 0, $count = min(count($setsA), count($setsB)); $index < $count; $index++) {
            if ((float) $setsA[$index] > (float) $setsB[$index]) {
                $winsA++;
            } elseif ((float) $setsB[$index] > (float) $setsA[$index]) {
                $winsB++;
            }
        }
        if ($winsA === $winsB) {
            return null;
        }
        return $winsA > $winsB
            ? ['winner' => $entries[0], 'loser' => $entries[1], 'winner_sets' => $winsA, 'loser_sets' => $winsB]
            : ['winner' => $entries[1], 'loser' => $entries[0], 'winner_sets' => $winsB, 'loser_sets' => $winsA];
    }

    private function entrySetScores(array $entry): array
    {
        $raw = $entry['set_scores'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map('floatval', $decoded));
            }
        }
        return [(float) ($entry['raw_score'] ?? 0)];
    }
}
