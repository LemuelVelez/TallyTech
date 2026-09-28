<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class EnableMultipleTournamentManagerSports extends Migration
{
    public function up()
    {
        // The existing user_sports table already supports multiple sports per user
        // through its (user_id, sport_id) composite primary key. This migration is
        // intentionally non-destructive and marks the rollout of multi-sport
        // Tournament Manager assignments without changing an already-applied migration.
        if (! $this->db->tableExists('user_sports')) {
            throw new RuntimeException('The user_sports table is required for multi-sport Tournament Manager assignments.');
        }

        foreach (['user_id', 'sport_id'] as $column) {
            if (! $this->db->fieldExists($column, 'user_sports')) {
                throw new RuntimeException('The user_sports.' . $column . ' column is required for multi-sport Tournament Manager assignments.');
            }
        }
    }

    public function down()
    {
        // No schema changes were required. The existing user_sports relationship
        // remains valid when rolling this migration back.
    }
}
