<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\SignalTrait;
use Throwable;

class Migrate extends BaseCommand
{
    use SignalTrait;

    protected $group = 'Database';
    protected $name = 'migrate';
    protected $description = 'Locates and runs all new migrations against the database.';
    protected $usage = 'migrate [options]';
    protected $options = [
        '-n'    => 'Set migration namespace',
        '-g'    => 'Set database group',
        '--all' => 'Set for all namespaces, will ignore (-n) option',
    ];

    public function run(array $params)
    {
        $runner = service('migrations');
        $runner->clearCliMessages();

        CLI::newLine();
        CLI::write('🗄️  TALLYTECH DATABASE MIGRATIONS', 'cyan');
        CLI::write(str_repeat('─', 38), 'light_gray');
        CLI::write('🔎 Checking for pending migrations...', 'yellow');

        $namespace = $params['n'] ?? CLI::getOption('n');
        $group = $params['g'] ?? CLI::getOption('g');

        try {
            if (array_key_exists('all', $params) || CLI::getOption('all')) {
                $runner->setNamespace(null);
            } elseif ($namespace) {
                $runner->setNamespace($namespace);
            }

            $migrationSucceeded = true;
            $this->withSignalsBlocked(static function () use ($runner, $group, &$migrationSucceeded): void {
                $migrationSucceeded = $runner->latest($group);
            });

            if (! $migrationSucceeded) {
                CLI::error('❌ ' . lang('Migrations.generalFault'), 'white', 'red');
                return;
            }

            $messages = $runner->getCliMessages();

            if ($messages === []) {
                CLI::write('ℹ️  No pending migrations; database is already up to date.', 'green');
                CLI::newLine();
                return;
            }

            CLI::write('🔄 Applying migrations...', 'yellow');
            foreach ($messages as $message) {
                CLI::write('   • ' . $message, 'light_gray');
            }

            CLI::write('✅ Migrations completed successfully.', 'green');
            CLI::newLine();
        } catch (Throwable $e) {
            CLI::error('❌ Migration failed.', 'white', 'red');
            $this->showError($e);
        }
    }
}
