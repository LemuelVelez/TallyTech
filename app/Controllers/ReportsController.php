<?php

namespace App\Controllers;

use App\Application\Services\ReportingService;
use App\Application\Services\XlsxReportService;

class ReportsController extends BaseController
{
    private function input(): array
    {
        $string = static fn(mixed $value): string => is_scalar($value) ? (string) $value : '';
        $type = $string($this->request->getGet('type'));
        if (! in_array($type, ['standings', 'medal_tally', 'sport_results', 'sport_rankings', 'validation_log', 'full_event'], true)) {
            $type = 'standings';
        }

        $repository = $this->repository();
        $events = $repository->events();
        $activeEventId = (int) ($repository->activeEvent()['id'] ?? 0);
        $eventIds = array_map('intval', array_column($events, 'id'));
        $rawEventId = $this->request->getGet('event_id');
        $eventId = is_scalar($rawEventId) && preg_match('/^[1-9]\d*$/', (string) $rawEventId) ? (int) $rawEventId : 0;
        if (! in_array($eventId, $eventIds, true)) {
            $eventId = in_array($activeEventId, $eventIds, true) ? $activeEventId : (int) ($eventIds[0] ?? 0);
        }

        $sports = $eventId > 0 ? $repository->sports($eventId) : [];
        $sportIds = array_map('intval', array_column($sports, 'id'));
        $rawSportId = $this->request->getGet('sport_id');
        $sportId = is_scalar($rawSportId) && preg_match('/^[1-9]\d*$/', (string) $rawSportId) ? (int) $rawSportId : 0;
        if (! in_array($sportId, $sportIds, true)) {
            $sportId = 0;
        }

        $category = $string($this->request->getGet('category'));
        if (! in_array($category, ['Men', 'Women', 'Mixed'], true)) {
            $category = '';
        }
        $status = $string($this->request->getGet('status'));
        if (! in_array($status, ['pending', 'validated'], true)) {
            $status = '';
        }
        $dateRange = $string($this->request->getGet('date_range'));
        if (! in_array($dateRange, ['all_event', 'today', 'this_week', 'custom'], true)) {
            $dateRange = 'all_event';
        }
        $validDate = static function (mixed $value): string {
            if (! is_scalar($value)) {
                return '';
            }
            $date = (string) $value;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            return $parsed !== false && $parsed->format('Y-m-d') === $date ? $date : '';
        };
        $from = $dateRange === 'custom' ? $validDate($this->request->getGet('from')) : '';
        $to = $dateRange === 'custom' ? $validDate($this->request->getGet('to')) : '';
        if ($from !== '' && $to !== '' && $from > $to) {
            $from = '';
            $to = '';
        }

        return [$type, [
            'event_id' => $eventId,
            'sport_id' => $sportId,
            'category' => $category,
            'status' => $status,
            'date_range' => $dateRange,
            'from' => $from,
            'to' => $to,
        ]];
    }

    public function index()
    {
        [$type, $filters] = $this->input();
        $repository = $this->repository();
        $events = $repository->events();
        $event = $this->selectedEvent($events, (int) $filters['event_id']);
        $sports = (int) $filters['event_id'] > 0 ? $repository->sports((int) $filters['event_id']) : [];
        $categories = array_values(array_unique(array_filter(array_map(static fn(array $sport): string => (string) ($sport['category'] ?? ''), $sports))));
        $reporting = new ReportingService($repository);

        return view('reports/index', [
            'title' => $event['name'] ?? 'Reports',
            'event' => $event,
            'events' => $events,
            'sports' => $sports,
            'categories' => $categories,
            'type' => $type,
            'reportLabel' => $reporting->title($type),
            'filters' => $filters,
            'rows' => $reporting->report($type, $filters, $event),
            'columns' => $reporting->columnsFor($type),
            'numericCols' => $reporting->numericColumns($type),
            'pointCols' => $reporting->pointColumns(),
            'columnLabels' => array_combine($reporting->columnsFor($type), array_map([$reporting, 'label'], $reporting->columnsFor($type))) ?: [],
            'filterSummary' => $reporting->filterSummary($filters, $event, $sports),
        ]);
    }

    public function print()
    {
        [$type, $filters] = $this->input();
        $repository = $this->repository();
        $event = $this->selectedEvent($repository->events(), (int) $filters['event_id']);
        $sports = (int) $filters['event_id'] > 0 ? $repository->sports((int) $filters['event_id']) : [];
        $reporting = new ReportingService($repository);
        $settings = $repository->getUserSettings((int) session()->get('user_id'));

        return view('reports/print', [
            'title' => $event['name'] ?? 'TallyTech Report',
            'event' => $event,
            'type' => $type,
            'reportLabel' => $reporting->title($type),
            'filters' => $filters,
            'rows' => $reporting->report($type, $filters, $event),
            'columns' => $reporting->columnsFor($type),
            'numericCols' => $reporting->numericColumns($type),
            'pointCols' => $reporting->pointColumns(),
            'columnLabels' => array_combine($reporting->columnsFor($type), array_map([$reporting, 'label'], $reporting->columnsFor($type))) ?: [],
            'filterSummary' => $reporting->filterSummary($filters, $event, $sports),
            'printSettings' => $settings,
            'teamRanking' => ($settings['include_team_ranking'] ?? '1') === '1' && (int) $filters['event_id'] > 0 ? $repository->ranking((int) $filters['event_id'], true) : [],
        ]);
    }

    public function xlsx()
    {
        [$type, $filters] = $this->input();
        $repository = $this->repository();
        $event = $this->selectedEvent($repository->events(), (int) $filters['event_id']);
        $sports = (int) $filters['event_id'] > 0 ? $repository->sports((int) $filters['event_id']) : [];
        $reporting = new ReportingService($repository);
        try {
            $rows = $reporting->report($type, $filters, $event);
            $export = (new XlsxReportService())->create(
                (string) ($event['name'] ?? 'TallyTech Report'),
                $reporting->title($type),
                $rows,
                $reporting->columnsFor($type),
                $reporting->numericColumns($type),
                $reporting->pointColumns(),
                $reporting->filterSummary($filters, $event, $sports)
            );
            return $this->response->download($export['filename'], $export['content'], true);
        } catch (\Throwable $e) {
            $query = http_build_query(array_merge(['type' => $type], $filters));
            return redirect()->to('/reports?' . $query)->with('error', $this->safeErrorMessage($e, 'Excel report could not be generated.'));
        }
    }

    private function selectedEvent(array $events, int $eventId): ?array
    {
        foreach ($events as $event) {
            if ((int) ($event['id'] ?? 0) === $eventId) {
                return $event;
            }
        }
        return null;
    }
}
