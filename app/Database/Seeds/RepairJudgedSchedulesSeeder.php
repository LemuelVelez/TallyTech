<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class RepairJudgedSchedulesSeeder extends Seeder
{
    public function run()
    {
        foreach (['events', 'sports', 'schedules', 'results', 'result_entries', 'weighted_points'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before repairing judged schedules.');
            }
        }

        $event = $this->db->table('events')
            ->select('id,name')
            ->where('is_active', 1)
            ->orderBy('year', 'DESC')
            ->get()
            ->getRowArray();
        if (! $event) {
            throw new RuntimeException('No active event was found.');
        }

        $judgedSports = $this->db->table('sports')
            ->select('id,name,category')
            ->where('event_id', (int) $event['id'])
            ->where('result_type', 'judged')
            ->get()
            ->getResultArray();
        if ($judgedSports === []) {
            CLI::write('No judged sports exist in the active event; nothing was repaired.', 'yellow');
            return;
        }

        $changes = 0;
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the judged-schedule repair transaction.');
        }

        try {
            foreach ($judgedSports as $sport) {
                $sportId = (int) $sport['id'];
                $schedules = $this->db->table('schedules')
                    ->where(['event_id' => (int) $event['id'], 'sport_id' => $sportId])
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getResultArray();
                if ($schedules === []) {
                    continue;
                }

                $scheduleIds = array_map('intval', array_column($schedules, 'id'));
                $results = $this->db->table('results')
                    ->whereIn('schedule_id', $scheduleIds)
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getResultArray();
                $resultBySchedule = [];
                $validatedResults = [];
                foreach ($results as $result) {
                    $resultBySchedule[(int) $result['schedule_id']] = $result;
                    if (($result['status'] ?? '') === 'validated') {
                        $validatedResults[] = $result;
                    }
                }

                if (count($validatedResults) > 1) {
                    throw new RuntimeException('Multiple validated judged results exist for ' . $sport['name'] . ' ' . $sport['category'] . '. Resolve them manually before running this repair.');
                }

                if ($validatedResults !== []) {
                    $keepScheduleId = (int) $validatedResults[0]['schedule_id'];
                } else {
                    $keepScheduleId = 0;
                    foreach ($schedules as $schedule) {
                        if (isset($resultBySchedule[(int) $schedule['id']])) {
                            $keepScheduleId = (int) $schedule['id'];
                            break;
                        }
                    }
                    if ($keepScheduleId < 1) {
                        foreach ($schedules as $schedule) {
                            if (strtoupper(trim((string) ($schedule['match_code'] ?? ''))) === 'M1') {
                                $keepScheduleId = (int) $schedule['id'];
                                break;
                            }
                        }
                    }
                    $keepScheduleId = $keepScheduleId ?: (int) $schedules[0]['id'];
                }

                $duplicateScheduleIds = [];
                $duplicateResultIds = [];
                foreach ($schedules as $schedule) {
                    $scheduleId = (int) $schedule['id'];
                    if ($scheduleId === $keepScheduleId) {
                        continue;
                    }
                    $duplicateScheduleIds[] = $scheduleId;
                    $duplicateResult = $resultBySchedule[$scheduleId] ?? null;
                    if ($duplicateResult) {
                        if (($duplicateResult['status'] ?? '') === 'validated') {
                            throw new RuntimeException('A validated judged result would be removed for ' . $sport['name'] . ' ' . $sport['category'] . '; repair stopped.');
                        }
                        $duplicateResultIds[] = (int) $duplicateResult['id'];
                    }
                }

                if ($duplicateResultIds !== []) {
                    $this->db->table('result_entries')->whereIn('result_id', $duplicateResultIds)->delete();
                    $this->db->table('results')->whereIn('id', $duplicateResultIds)->delete();
                    $changes += count($duplicateResultIds);
                }
                if ($duplicateScheduleIds !== []) {
                    $this->db->table('schedules')->whereIn('id', $duplicateScheduleIds)->delete();
                    $changes += count($duplicateScheduleIds);
                }

                $keptResult = $resultBySchedule[$keepScheduleId] ?? null;
                $scheduleUpdate = [
                    'round' => 'Championship',
                    'tournament_format' => 'single_elimination',
                    'match_code' => 'M1',
                    'phase' => 'final',
                    'bracket_side' => 'grand',
                    'bracket_order' => 1,
                    'team_a_id' => null,
                    'team_b_id' => null,
                    'feeds_from_a' => null,
                    'feeds_from_a_type' => null,
                    'feeds_from_b' => null,
                    'feeds_from_b_type' => null,
                    'is_conditional' => 0,
                    'scheduling_note' => null,
                ];
                if (($keptResult['status'] ?? '') === 'validated') {
                    $scheduleUpdate['status'] = 'played';
                }
                $this->db->table('schedules')->where('id', $keepScheduleId)->update($scheduleUpdate);
                if ($this->db->affectedRows() > 0) {
                    $changes++;
                }

                if (($keptResult['status'] ?? '') !== 'validated') {
                    continue;
                }

                $weightedPoints = $this->db->table('weighted_points')->where([
                    'event_id' => (int) $event['id'],
                    'sport_id' => $sportId,
                    'status' => 'validated',
                ])->get()->getRowArray();
                if (! $weightedPoints) {
                    throw new RuntimeException('Validated weighted points are missing for ' . $sport['name'] . ' ' . $sport['category'] . '.');
                }

                $entries = $this->db->table('result_entries')
                    ->where('result_id', (int) $keptResult['id'])
                    ->orderBy('raw_score', 'DESC')
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getResultArray();
                $seenScores = [];
                foreach ($entries as $index => $entry) {
                    $scoreKey = number_format((float) $entry['raw_score'], 2, '.', '');
                    if (isset($seenScores[$scoreKey])) {
                        throw new RuntimeException('A judged score tie exists for ' . $sport['name'] . ' ' . $sport['category'] . '; placements cannot be repaired automatically.');
                    }
                    $seenScores[$scoreKey] = true;
                    $placement = $index + 1;
                    $points = match ($placement) {
                        1 => (float) $weightedPoints['first_points'],
                        2 => (float) $weightedPoints['second_points'],
                        3 => (float) $weightedPoints['third_points'],
                        4 => (float) $weightedPoints['fourth_points'],
                        default => (float) $weightedPoints['participation_points'],
                    };
                    $this->db->table('result_entries')->where('id', (int) $entry['id'])->update([
                        'placement' => $placement,
                        'allocated_points' => $points,
                    ]);
                    if ($this->db->affectedRows() > 0) {
                        $changes++;
                    }
                }
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the judged-schedule repair.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        CLI::write('Judged schedules repaired for active event: ' . (string) ($event['name'] ?? ('#' . $event['id'])), 'green');
        CLI::write('  changes applied: ' . $changes);
    }
}
