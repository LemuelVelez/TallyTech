<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class ResetMobileLegendsBracketSeeder extends Seeder
{
    public function run()
    {
        foreach (['events', 'sports', 'schedules', 'results', 'result_entries', 'weighted_points'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before resetting Mobile Legends.');
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

        $sportIds = [];
        foreach ($this->db->table('sports')->select('id,name')->where('event_id', (int) $event['id'])->get()->getResultArray() as $sport) {
            if (strcasecmp(trim((string) ($sport['name'] ?? '')), 'Mobile Legends') === 0) {
                $sportIds[] = (int) $sport['id'];
            }
        }
        if ($sportIds === []) {
            CLI::write('No Mobile Legends sport exists in the active event; nothing was reset.', 'yellow');
            return;
        }

        $scheduleIds = array_map('intval', array_column(
            $this->db->table('schedules')
                ->select('id')
                ->where('event_id', (int) $event['id'])
                ->whereIn('sport_id', $sportIds)
                ->get()
                ->getResultArray(),
            'id'
        ));

        if ($scheduleIds === []) {
            CLI::write('Mobile Legends already has no bracket data for the active event.', 'green');
            return;
        }

        $resultIds = array_map('intval', array_column(
            $this->db->table('results')->select('id')->whereIn('schedule_id', $scheduleIds)->get()->getResultArray(),
            'id'
        ));

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start the Mobile Legends reset transaction.');
        }

        try {
            if ($resultIds !== []) {
                $this->db->table('result_entries')->whereIn('result_id', $resultIds)->delete();
                $this->db->table('results')->whereIn('id', $resultIds)->delete();
            }
            $this->db->table('schedules')->whereIn('id', $scheduleIds)->delete();

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit the Mobile Legends reset.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        CLI::write('Mobile Legends bracket data reset for active event: ' . (string) ($event['name'] ?? ('#' . $event['id'])), 'green');
        CLI::write('  schedules removed: ' . count($scheduleIds));
        CLI::write('  results removed:   ' . count($resultIds));
        CLI::write('  sport and weighted points were preserved.');
    }
}
