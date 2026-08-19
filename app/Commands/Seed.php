<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class Seed extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'seed';
    protected $description = 'Seeds the TallyTech database with the default application data.';
    protected $usage = 'seed';

    public function run(array $params)
    {
        return $this->call('db:seed', ['TallyTechSeeder']);
    }
}
