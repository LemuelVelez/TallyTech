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
    protected $description = 'Seeds the TallyTech database with all normal application seed data.';
    protected $usage = 'seed';

    private const SEEDERS = [
        'CanonicalTeamsSeeder',
        'TallyTechSeeder',
        'BracketSeeder',
        'FourthPlacePointsSeeder',
        'OfficialScoreboardSeeder',
        'UnofficialScoreboardSeeder',
    ];

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write('🌱 TALLYTECH DATABASE SEEDER', 'cyan');
        CLI::write(str_repeat('─', 34), 'light_gray');
        CLI::write('🔎 Checking for pending seed data...', 'yellow');

        try {
            $seeder = Database::seeder();

            foreach (self::SEEDERS as $seederClass) {
                $seeder->call($seederClass);
            }

            CLI::newLine();
        } catch (Throwable $e) {
            CLI::error('❌ Seeding failed.', 'white', 'red');
            $this->showError($e);
        }
    }
}
