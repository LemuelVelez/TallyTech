<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddReferenceDataManagement extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_active', 'locations')) {
            $this->forge->addColumn('locations', [
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'name'],
                'created_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_active'],
                'updated_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'created_at'],
            ]);
        }

        if (! $this->db->tableExists('sport_categories')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 80],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('name');
            $this->forge->createTable('sport_categories', true);
        }

        $now = date('Y-m-d H:i:s');
        $categories = $this->db->table('sports')->select('category')->distinct()->get()->getResultArray();
        foreach ($categories as $category) {
            $name = trim((string) ($category['category'] ?? ''));
            if ($name === '') {
                continue;
            }
            $exists = $this->db->table('sport_categories')->where('name', $name)->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('sport_categories')->insert([
                    'name' => $name,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->forge->modifyColumn('sports', [
            'category' => ['name' => 'category', 'type' => 'VARCHAR', 'constraint' => 80],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('sport_categories')) {
            $allowed = ['Men', 'Women', 'Mixed'];
            $categories = array_column(
                $this->db->table('sports')->select('category')->distinct()->get()->getResultArray(),
                'category'
            );
            foreach ($categories as $category) {
                if (! in_array($category, $allowed, true)) {
                    throw new RuntimeException('Cannot roll back reference-data migration while custom sport categories are in use.');
                }
            }

            $this->forge->modifyColumn('sports', [
                'category' => ['name' => 'category', 'type' => 'ENUM', 'constraint' => $allowed],
            ]);
            $this->forge->dropTable('sport_categories', true);
        }

        if ($this->db->fieldExists('updated_at', 'locations')) {
            $this->forge->dropColumn('locations', 'updated_at');
        }
        if ($this->db->fieldExists('created_at', 'locations')) {
            $this->forge->dropColumn('locations', 'created_at');
        }
        if ($this->db->fieldExists('is_active', 'locations')) {
            $this->forge->dropColumn('locations', 'is_active');
        }
    }
}
