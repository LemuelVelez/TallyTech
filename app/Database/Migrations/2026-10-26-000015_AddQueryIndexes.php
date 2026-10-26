<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddQueryIndexes extends Migration
{
    private const INDEXES = [
        'results' => ['tt_results_event_status' => ['event_id', 'status']],
        'schedules' => ['tt_schedules_event_sport_date' => ['event_id','sport_id','match_date']],
        'notifications' => [
            'tt_notifications_user_read' => ['recipient_user_id','is_read'],
            'tt_notifications_role_read' => ['recipient_role','is_read'],
        ],
        'notification_reads' => ['tt_notification_reads_user_notification' => ['user_id','notification_id']],
    ];
    private function indexExists(string $table,string $index): bool
    {
        foreach ($this->db->query('SHOW INDEX FROM `'.$table.'`')->getResultArray() as $row) {
            if ($row['Key_name'] === $index) return true;
        }
        return false;
    }
    public function up()
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! $this->db->tableExists($table)) continue;
            foreach ($indexes as $name => $cols) {
                if ($this->indexExists($table,$name)) continue;
                $present = true;
                foreach ($cols as $col) if (! $this->db->fieldExists($col,$table)) $present=false;
                if (! $present) continue;
                $fields = implode(',',array_map(static fn($c): string => '`'.$c.'`',$cols));
                $this->db->query('CREATE INDEX `'.$name.'` ON `'.$table.'` ('.$fields.')');
            }
        }
    }
    public function down()
    {
        foreach (self::INDEXES as $table=>$indexes) {
            if (! $this->db->tableExists($table)) continue;
            foreach ($indexes as $name=>$columns) {
                if ($this->indexExists($table,$name)) $this->db->query('DROP INDEX `'.$name.'` ON `'.$table.'`');
            }
        }
    }
}
