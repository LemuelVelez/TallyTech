<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

class Seed extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'seed';
    protected $description = 'Seeds the TallyTech database with the default application data.';
    protected $usage = 'seed';

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write('🌱 TALLYTECH DATABASE SEEDER', 'cyan');
        CLI::write(str_repeat('─', 34), 'light_gray');
        CLI::write('🔎 Checking for pending seed data...', 'yellow');

        try {
            $seeder = Database::seeder();
            $seeder->call('TallyTechSeeder');
            CLI::newLine();
        } catch (Throwable $e) {
            CLI::error('❌ Seeding failed.', 'white', 'red');
            $this->showError($e);
        }
    }
}
