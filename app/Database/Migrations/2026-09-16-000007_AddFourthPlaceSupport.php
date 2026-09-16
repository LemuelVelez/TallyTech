<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFourthPlaceSupport extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE `weighted_points` ADD `fourth_points` DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER `third_points`"
        );
        $this->db->query(
            "ALTER TABLE `schedules` MODIFY `phase` ENUM('playoff','lower_r1','quarter','semi','third_place','final','tiebreaker') NULL"
        );
    }

    public function down()
    {
        $this->db->table('schedules')->where('phase', 'third_place')->update(['phase' => 'playoff']);
        $this->db->query(
            "ALTER TABLE `schedules` MODIFY `phase` ENUM('playoff','lower_r1','quarter','semi','final','tiebreaker') NULL"
        );
        $this->db->query("ALTER TABLE `weighted_points` DROP COLUMN `fourth_points`");
    }
}
