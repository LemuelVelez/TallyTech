<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class CanonicalTeamsSeeder extends Seeder
{
    private const OFFICIAL_TEAMS = [
        'CBA' => 'CBA Lions',
        'COE-CTED' => 'COE Stallions & CTED Dragons',
        'CCS-CAF' => 'CCS Panthers & CAF Buffalo',
        'SCJE-CLAMS' => 'SCJE Eagles & CLAMS Phoenix',
    ];

    public function run()
    {
        $allowProductionReset = filter_var(
            (string) env('TALLYTECH_ALLOW_DATA_RESET', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (ENVIRONMENT === 'production' && ! $allowProductionReset) {
            throw new RuntimeException(
                'Canonical team cleanup is blocked in production. Set TALLYTECH_ALLOW_DATA_RESET = true in .env only for the intended cleanup, then run this seeder again.'
            );
        }

        foreach (['teams', 'schedules', 'results', 'result_entries'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before cleaning up teams.');
            }
        }

        $officialCodes = array_keys(self::OFFICIAL_TEAMS);
        $obsoleteTeams = $this->db->table('teams')
            ->select('id, name, code')
            ->whereNotIn('code', $officialCodes)
            ->get()
            ->getResultArray();
        $obsoleteTeamIds = array_map(static fn (array $team): int => (int) $team['id'], $obsoleteTeams);

        $deletedEntries = 0;
        $deletedResults = 0;
        $deletedSchedules = 0;
        $deletedTeams = 0;
        $insertedTeams = 0;
        $updatedTeams = 0;

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start canonical team cleanup transaction.');
        }

        try {
            if ($obsoleteTeamIds !== []) {
                $scheduleRows = $this->db->table('schedules')
                    ->select('id')
                    ->groupStart()
                        ->whereIn('team_a_id', $obsoleteTeamIds)
                        ->orWhereIn('team_b_id', $obsoleteTeamIds)
                    ->groupEnd()
                    ->get()
                    ->getResultArray();
                $scheduleIds = array_map(static fn (array $row): int => (int) $row['id'], $scheduleRows);

                $resultIds = [];
                if ($scheduleIds !== []) {
                    $resultRows = $this->db->table('results')
                        ->select('id')
                        ->whereIn('schedule_id', $scheduleIds)
                        ->get()
                        ->getResultArray();
                    $resultIds = array_map(static fn (array $row): int => (int) $row['id'], $resultRows);
                }

                if ($resultIds !== []) {
                    $this->db->table('result_entries')->whereIn('result_id', $resultIds)->delete();
                    $deletedEntries += $this->db->affectedRows();
                }

                $this->db->table('result_entries')->whereIn('team_id', $obsoleteTeamIds)->delete();
                $deletedEntries += $this->db->affectedRows();

                if ($resultIds !== []) {
                    $this->db->table('results')->whereIn('id', $resultIds)->delete();
                    $deletedResults += $this->db->affectedRows();
                }

                if ($scheduleIds !== []) {
                    $this->db->table('schedules')->whereIn('id', $scheduleIds)->delete();
                    $deletedSchedules += $this->db->affectedRows();
                }

                $this->db->table('teams')->whereIn('id', $obsoleteTeamIds)->delete();
                $deletedTeams += $this->db->affectedRows();
            }

            $now = date('Y-m-d H:i:s');
            foreach (self::OFFICIAL_TEAMS as $code => $name) {
                $existing = $this->db->table('teams')
                    ->select('id, name, code')
                    ->where('code', $code)
                    ->get()
                    ->getRowArray();

                if ($existing === null) {
                    $this->db->table('teams')->insert([
                        'name' => $name,
                        'code' => $code,
                        'created_at' => $now,
                    ]);
                    $insertedTeams++;
                    continue;
                }

                if ((string) $existing['name'] !== $name) {
                    $this->db->table('teams')->where('id', (int) $existing['id'])->update([
                        'name' => $name,
                    ]);
                    $updatedTeams++;
                }
            }

            $finalTeams = $this->db->table('teams')
                ->select('name, code')
                ->orderBy('code', 'ASC')
                ->get()
                ->getResultArray();

            if (count($finalTeams) !== count(self::OFFICIAL_TEAMS)) {
                throw new RuntimeException('Canonical team cleanup failed: the teams table does not contain exactly four teams.');
            }

            foreach ($finalTeams as $team) {
                $code = (string) $team['code'];
                if (! isset(self::OFFICIAL_TEAMS[$code]) || self::OFFICIAL_TEAMS[$code] !== (string) $team['name']) {
                    throw new RuntimeException('Canonical team cleanup failed: an unexpected team remains in the teams table.');
                }
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit canonical team cleanup.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $changes = $deletedEntries
            + $deletedResults
            + $deletedSchedules
            + $deletedTeams
            + $insertedTeams
            + $updatedTeams;

        if ($changes === 0) {
            CLI::write('ℹ️  No pending canonical team seed data; exactly four official teams are already synchronized.', 'green');
            return;
        }

        CLI::write('✅ Canonical team seed data synchronized successfully.', 'green');
        CLI::write('  result entries deleted: ' . $deletedEntries);
        CLI::write('  results deleted:        ' . $deletedResults);
        CLI::write('  schedules deleted:      ' . $deletedSchedules);
        CLI::write('  teams deleted:          ' . $deletedTeams);
        CLI::write('  teams inserted:         ' . $insertedTeams);
        CLI::write('  teams renamed:          ' . $updatedTeams);
        CLI::write('  canonical teams:        4');
    }
}
