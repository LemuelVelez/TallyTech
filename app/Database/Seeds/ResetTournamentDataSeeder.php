<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class ResetTournamentDataSeeder extends Seeder
{
    private const RESET_TABLES = [
        'result_entries',
        'results',
        'schedules',
        'weighted_points',
        'notifications',
        'teams',
    ];

    private const OFFICIAL_TEAMS = [
        ['name' => 'CBA Lions', 'code' => 'CBA'],
        ['name' => 'COE Stallions & CTED Dragons', 'code' => 'COE-CTED'],
        ['name' => 'CCS Panthers & CAF Buffalo', 'code' => 'CCS-CAF'],
        ['name' => 'SCJE Eagles & CLAMS Phoenix', 'code' => 'SCJE-CLAMS'],
    ];

    public function run()
    {
        $allowProductionReset = filter_var(
            (string) env('TALLYTECH_ALLOW_DATA_RESET', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (ENVIRONMENT === 'production' && ! $allowProductionReset) {
            throw new RuntimeException(
                'Tournament data reset is blocked in production. Set TALLYTECH_ALLOW_DATA_RESET = true in .env only for the intended reset, then run this seeder again.'
            );
        }

        foreach (self::RESET_TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Database schema is not ready. Run "php spark migrate" before resetting tournament data.');
            }
        }

        $deleted = array_fill_keys(self::RESET_TABLES, 0);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start tournament reset transaction.');
        }

        try {
            foreach (self::RESET_TABLES as $table) {
                $this->db->query('DELETE FROM `' . $table . '`');
                $deleted[$table] = $this->db->affectedRows();
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit tournament data reset.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        foreach (self::RESET_TABLES as $table) {
            $this->db->query('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start official-team seed transaction.');
        }

        try {
            $now = date('Y-m-d H:i:s');
            foreach (self::OFFICIAL_TEAMS as $team) {
                $this->db->table('teams')->insert([
                    'name' => $team['name'],
                    'code' => $team['code'],
                    'created_at' => $now,
                ]);
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException('Unable to commit official-team seed data.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $avatarFilesDeleted = $this->deleteOrphanedTeamAvatars();

        CLI::write('Tournament data reset complete.', 'green');
        foreach (self::RESET_TABLES as $table) {
            CLI::write(sprintf('  %-16s %d row(s) deleted', $table . ':', $deleted[$table]));
        }
        CLI::write('  team avatars:    ' . $avatarFilesDeleted . ' file(s) deleted');
        CLI::write('  teams seeded:    ' . count(self::OFFICIAL_TEAMS));
    }

    private function deleteOrphanedTeamAvatars(): int
    {
        $directory = rtrim(FCPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'team-avatars';

        if (! is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $preserve = ['.gitkeep', 'index.html'];
        $iterator = new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isDot() || ! $file->isFile() || in_array($file->getFilename(), $preserve, true)) {
                continue;
            }

            if (! @unlink($file->getPathname())) {
                throw new RuntimeException('Unable to delete orphaned team avatar: ' . $file->getFilename());
            }
            $deleted++;
        }

        return $deleted;
    }
}
