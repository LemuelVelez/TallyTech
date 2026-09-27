<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FirstCorrectionRound extends Migration
{
    public function up()
    {
        $admin = $this->db->table('users')->select('id')->where('role', 'admin')->orderBy('id')->get()->getRowArray();
        $adminId = (int) ($admin['id'] ?? 0);
        $validatorIds = array_map('intval', array_column($this->db->table('users')->select('id')->where('role', 'validator')->get()->getResultArray(), 'id'));
        if ($validatorIds !== []) {
            foreach (['results' => ['submitted_by', 'validated_by'], 'weighted_points' => ['submitted_by', 'validated_by'], 'notifications' => ['actor_user_id']] as $table => $columns) {
                foreach ($columns as $column) {
                    if ($this->db->fieldExists($column, $table)) {
                        $builder = $this->db->table($table)->whereIn($column, $validatorIds);
                        $builder->update([$column => $adminId > 0 ? $adminId : null]);
                    }
                }
            }
            $this->db->table('user_sports')->whereIn('user_id', $validatorIds)->delete();
            $this->db->table('user_settings')->whereIn('user_id', $validatorIds)->delete();
            $this->db->table('users')->whereIn('id', $validatorIds)->delete();
        }

        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','facilitator') NOT NULL");

        // Existing Tournament Managers are normalized to one sport assignment.
        foreach ($this->db->table('users')->select('id')->where('role', 'manager')->get()->getResultArray() as $manager) {
            $rows = $this->db->table('user_sports')->select('sport_id')->where('user_id', (int) $manager['id'])->orderBy('sport_id')->get()->getResultArray();
            if (count($rows) > 1) {
                $keep = (int) $rows[0]['sport_id'];
                $this->db->table('user_sports')->where('user_id', (int) $manager['id'])->where('sport_id !=', $keep)->delete();
            }
        }
        $this->forge->addColumn('users', [
            'generated_password' => ['type' => 'TEXT', 'null' => true, 'after' => 'password_hash'],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'status'],
        ]);
        $this->db->query('ALTER TABLE users ADD CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->db->query("ALTER TABLE results MODIFY status ENUM('pending','approved','validated') NOT NULL DEFAULT 'pending'");
        $this->forge->addColumn('results', [
            'approved_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'submitted_by'],
            'approved_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'submitted_at'],
            'return_note' => ['type' => 'TEXT', 'null' => true, 'after' => 'notes'],
        ]);
        $this->db->query('ALTER TABLE results ADD CONSTRAINT fk_results_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->forge->addColumn('notifications', [
            'recipient_role' => ['type' => 'ENUM', 'constraint' => ['admin','manager','facilitator'], 'null' => true, 'after' => 'actor_user_id'],
            'recipient_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'recipient_role'],
            'sport_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'recipient_user_id'],
            'is_read' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'sport_id'],
        ]);
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT fk_notifications_recipient_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT fk_notifications_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'selector' => ['type' => 'VARCHAR', 'constraint' => 64],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('selector');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('auth_remember_tokens', true);

        $this->db->table('schedules')->like('scheduling_note', 'winner/loser bracket')->set('scheduling_note', "Upper-bracket champion vs lower-bracket champion.")->update();
        $this->db->query("UPDATE schedules SET scheduling_note = REPLACE(scheduling_note, 'loser-bracket', 'lower-bracket') WHERE scheduling_note LIKE '%loser-bracket%'");
    }

    public function down()
    {
        $this->forge->dropTable('auth_remember_tokens', true);
        foreach ([['notifications','fk_notifications_recipient_user'],['notifications','fk_notifications_sport'],['results','fk_results_approved_by'],['users','fk_users_created_by']] as [$table,$constraint]) {
            try { $this->forge->dropForeignKey($table, $constraint); } catch (\Throwable $e) { /* rollback stays tolerant */ }
        }
        foreach (['notifications' => ['recipient_role','recipient_user_id','sport_id','is_read'], 'results' => ['approved_by','approved_at','return_note'], 'users' => ['generated_password','created_by']] as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->fieldExists($column, $table)) $this->forge->dropColumn($table, $column);
            }
        }
        $this->db->query("ALTER TABLE results MODIFY status ENUM('pending','validated') NOT NULL DEFAULT 'pending'");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','validator','facilitator') NOT NULL");
    }
}
