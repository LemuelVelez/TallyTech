<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateUserProfiles extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('user_profiles')) return;
        $this->forge->addField([
            'user_id' => ['type'=>'INT','unsigned'=>true],
            'photo_path' => ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'address' => ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'phone' => ['type'=>'VARCHAR','constraint'=>16,'null'=>true],
            'facebook_url' => ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'email' => ['type'=>'VARCHAR','constraint'=>254,'null'=>true],
            'college_office' => ['type'=>'VARCHAR','constraint'=>180,'null'=>true],
            'position' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true],
            'emergency_contact_name' => ['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            'emergency_contact_phone' => ['type'=>'VARCHAR','constraint'=>16,'null'=>true],
            'bio' => ['type'=>'TEXT','null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('user_id',true);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('user_profiles',true);
    }
    public function down()
    {
        if ($this->db->tableExists('user_profiles')) $this->forge->dropTable('user_profiles',true);
    }
}
