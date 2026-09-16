<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

class FourthPlacePointsSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('weighted_points')
            || ! $this->db->fieldExists('fourth_points', 'weighted_points')) {
            throw new RuntimeException('Fourth-place schema is not ready. Run "php spark migrate" before seeding fourth-place points.');
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start fourth-place points seed transaction.');
        }

        try {
            $this->db->table('weighted_points')
                ->where('first_points', 10)
                ->where('second_points', 7)
                ->where('third_points', 5)
                ->where('participation_points', 2)
                ->where('fourth_points', 0)
                ->update(['fourth_points' => 3]);

            $changes = $this->db->affectedRows();

            if (! $this->db->transComplete()) {
                throw new RuntimeException('Unable to complete fourth-place points seed transaction.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        CLI::write(
            $changes > 0
                ? sprintf('Updated %d weighted-points row(s) with a 4th-place value of 3.', $changes)
                : 'No weighted-points rows required a 4th-place seed update.',
            'green'
        );
    }
}
