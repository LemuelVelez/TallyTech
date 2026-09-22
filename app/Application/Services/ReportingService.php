<?php

namespace App\Application\Services;

use App\Domain\Repositories\ScoringRepositoryInterface;

class ReportingService
{
    private const TITLES = [
        'standings' => 'Team Standings',
        'medal_tally' => 'Medal Tally',
        'sport_results' => 'Sport Results Summary',
        'sport_rankings' => 'Per-Sport Rankings',
        'validation_log' => 'Validation Log',
        'full_event' => 'Full Event Report',
    ];

    private const COLUMNS = [
        'standings' => ['rank', 'team', 'firsts', 'seconds', 'thirds', 'fourths', 'points'],
        'medal_tally' => ['team', 'gold', 'silver', 'bronze', 'total_medals', 'points'],
        'sport_results' => ['date', 'sport', 'category', 'round', 'team', 'score', 'placement', 'points', 'status'],
        'sport_rankings' => ['sport', 'category', 'rank', 'team', 'firsts', 'seconds', 'thirds', 'fourths', 'points'],
        'validation_log' => ['sport', 'category', 'round', 'status', 'submitted_by', 'submitted_at', 'validated_by', 'validated_at'],
        'full_event' => ['date', 'sport', 'category', 'round', 'team', 'score', 'placement', 'points', 'status', 'submitted_by', 'validated_by'],
    ];

    private const LABELS = [
        'rank' => 'Rank',
        'team' => 'Team',
        'firsts' => '1st',
        'seconds' => '2nd',
        'thirds' => '3rd',
        'fourths' => '4th',
        'points' => 'Points',
        'gold' => 'Gold',
        'silver' => 'Silver',
        'bronze' => 'Bronze',
        'total_medals' => 'Total Medals',
        'date' => 'Date',
        'sport' => 'Sport',
        'category' => 'Category',
        'round' => 'Round',
        'score' => 'Score',
        'placement' => 'Placement',
        'status' => 'Result Status',
        'submitted_by' => 'Submitted By',
        'submitted_at' => 'Submitted At',
        'validated_by' => 'Validated By',
        'validated_at' => 'Validated At',
    ];

    public function __construct(private ScoringRepositoryInterface $repository) {}

    public function report(string $type, array $filters, ?array $event): array
    {
        $filters = $this->withDateBounds($filters, $event);
        if (in_array($type, ['standings', 'medal_tally', 'sport_rankings'], true) && ($filters['status'] ?? '') === '') {
            $filters['status'] = 'validated';
        }

        $rows = $this->repository->reportRows($type, $filters);
        if ($type === 'standings') {
            foreach ($rows as $index => &$row) {
                $row = [
                    'rank' => $index + 1,
                    'team' => $row['team'],
                    'firsts' => (int) $row['firsts'],
                    'seconds' => (int) $row['seconds'],
                    'thirds' => (int) $row['thirds'],
                    'fourths' => (int) $row['fourths'],
                    'points' => (float) $row['points'],
                ];
            }
            unset($row);
        } elseif ($type === 'medal_tally') {
            foreach ($rows as &$row) {
                $row = [
                    'team' => $row['team'],
                    'gold' => (int) $row['gold'],
                    'silver' => (int) $row['silver'],
                    'bronze' => (int) $row['bronze'],
                    'total_medals' => (int) $row['gold'] + (int) $row['silver'] + (int) $row['bronze'],
                    'points' => (float) $row['points'],
                ];
            }
            unset($row);
        } elseif ($type === 'sport_rankings') {
            $rank = 0;
            $sportKey = null;
            foreach ($rows as &$row) {
                $key = (string) $row['sport_id'];
                if ($sportKey !== $key) {
                    $sportKey = $key;
                    $rank = 0;
                }
                $rank++;
                $row = [
                    'sport' => $row['sport'],
                    'category' => $row['category'],
                    'rank' => $rank,
                    'team' => $row['team'],
                    'firsts' => (int) $row['firsts'],
                    'seconds' => (int) $row['seconds'],
                    'thirds' => (int) $row['thirds'],
                    'fourths' => (int) $row['fourths'],
                    'points' => (float) $row['points'],
                ];
            }
            unset($row);
        } else {
            foreach ($rows as &$row) {
                if (isset($row['status'])) {
                    $row['status'] = $this->statusLabel((string) $row['status']);
                }
                if (isset($row['points'])) {
                    $row['points'] = (float) $row['points'];
                }
                if (isset($row['score'])) {
                    $row['score'] = (float) $row['score'];
                }
                if (array_key_exists('placement', $row) && $row['placement'] !== null && $row['placement'] !== '') {
                    $row['placement'] = (int) $row['placement'];
                }
            }
            unset($row);
        }

        return $rows;
    }

    public function title(string $type): string
    {
        return self::TITLES[$type] ?? 'Event Report';
    }

    public function columnsFor(string $type): array
    {
        return self::COLUMNS[$type] ?? [];
    }

    public function label(string $column): string
    {
        return self::LABELS[$column] ?? ucwords(str_replace('_', ' ', $column));
    }

    public function numericColumns(string $type): array
    {
        return match ($type) {
            'standings' => ['rank', 'firsts', 'seconds', 'thirds', 'fourths', 'points'],
            'medal_tally' => ['gold', 'silver', 'bronze', 'total_medals', 'points'],
            'sport_results' => ['score', 'placement', 'points'],
            'sport_rankings' => ['rank', 'firsts', 'seconds', 'thirds', 'fourths', 'points'],
            'full_event' => ['score', 'placement', 'points'],
            default => [],
        };
    }

    public function pointColumns(): array
    {
        return ['points'];
    }

    public function filterSummary(array $filters, ?array $event, array $sports): string
    {
        $parts = [];
        $sportId = (int) ($filters['sport_id'] ?? 0);
        if ($sportId > 0) {
            foreach ($sports as $sport) {
                if ((int) ($sport['id'] ?? 0) === $sportId) {
                    $parts[] = 'Sport: ' . $sport['name'] . ' · ' . $sport['category'];
                    break;
                }
            }
        } else {
            $parts[] = 'Sport: All Sports';
        }
        $parts[] = 'Category: ' . (($filters['category'] ?? '') !== '' ? $filters['category'] : 'All Categories');
        $parts[] = 'Status: ' . (($filters['status'] ?? '') !== '' ? $this->statusLabel((string) $filters['status']) : 'All Statuses');

        $range = (string) ($filters['date_range'] ?? 'all_event');
        if ($range === 'today') {
            $parts[] = 'Date: Today';
        } elseif ($range === 'this_week') {
            $parts[] = 'Date: This Week';
        } elseif ($range === 'custom') {
            $from = (string) ($filters['from'] ?? '');
            $to = (string) ($filters['to'] ?? '');
            $parts[] = 'Date: ' . ($from !== '' ? $from : 'Start') . ' to ' . ($to !== '' ? $to : 'End');
        } else {
            $start = (string) ($event['start_date'] ?? '');
            $end = (string) ($event['end_date'] ?? '');
            $parts[] = $start !== '' && $end !== '' ? 'Date: All Event (' . $start . ' to ' . $end . ')' : 'Date: All Event';
        }

        return implode(' | ', $parts);
    }

    private function withDateBounds(array $filters, ?array $event): array
    {
        $range = (string) ($filters['date_range'] ?? 'all_event');
        $filters['date_from'] = '';
        $filters['date_to'] = '';
        if ($range === 'today') {
            $filters['date_from'] = date('Y-m-d');
            $filters['date_to'] = date('Y-m-d');
        } elseif ($range === 'this_week') {
            $monday = new \DateTimeImmutable('monday this week');
            $filters['date_from'] = $monday->format('Y-m-d');
            $filters['date_to'] = $monday->modify('+6 days')->format('Y-m-d');
        } elseif ($range === 'custom') {
            $filters['date_from'] = (string) ($filters['from'] ?? '');
            $filters['date_to'] = (string) ($filters['to'] ?? '');
        }

        return $filters;
    }

    private function statusLabel(string $status): string
    {
        return $status === 'validated' ? 'Validated (Official)' : ($status === 'pending' ? 'Pending (Unofficial)' : ucfirst($status));
    }
}
