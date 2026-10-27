<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameUserRoles extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','facilitator','sports_coordinator','tournament_manager') NOT NULL");
        $this->db->query("UPDATE users SET role = 'sports_coordinator' WHERE role = 'manager'");
        $this->db->query("UPDATE users SET role = 'tournament_manager' WHERE role = 'facilitator'");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','sports_coordinator','tournament_manager') NOT NULL");

        $this->db->query("ALTER TABLE notifications MODIFY recipient_role ENUM('admin','manager','facilitator','sports_coordinator','tournament_manager') NULL");
        $this->db->query("UPDATE notifications SET recipient_role = 'sports_coordinator' WHERE recipient_role = 'manager'");
        $this->db->query("UPDATE notifications SET recipient_role = 'tournament_manager' WHERE recipient_role = 'facilitator'");
        $this->db->query("ALTER TABLE notifications MODIFY recipient_role ENUM('admin','sports_coordinator','tournament_manager') NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','facilitator','sports_coordinator','tournament_manager') NOT NULL");
        $this->db->query("UPDATE users SET role = 'manager' WHERE role = 'sports_coordinator'");
        $this->db->query("UPDATE users SET role = 'facilitator' WHERE role = 'tournament_manager'");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','facilitator') NOT NULL");

        $this->db->query("ALTER TABLE notifications MODIFY recipient_role ENUM('admin','manager','facilitator','sports_coordinator','tournament_manager') NULL");
        $this->db->query("UPDATE notifications SET recipient_role = 'manager' WHERE recipient_role = 'sports_coordinator'");
        $this->db->query("UPDATE notifications SET recipient_role = 'facilitator' WHERE recipient_role = 'tournament_manager'");
        $this->db->query("ALTER TABLE notifications MODIFY recipient_role ENUM('admin','manager','facilitator') NULL");
    }
}
