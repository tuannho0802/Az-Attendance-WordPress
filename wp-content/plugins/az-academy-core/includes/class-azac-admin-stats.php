<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Stats
{
    public static function get_attendance_stats($class_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT attendance_type, status, COUNT(*) as c FROM {$table} WHERE class_id=%d GROUP BY attendance_type, status", $class_id), ARRAY_A);
        $stats = [
            'checkin_present' => 0,
            'checkin_absent' => 0,
            'mid_present' => 0,
            'mid_absent' => 0,
            'sessions' => 0,
            'total_logs' => 0,
        ];
        foreach ($rows as $r) {
            $stats['total_logs'] += intval($r['c']);
            if ($r['attendance_type'] === 'check-in') {
                if (intval($r['status']) === 1)
                    $stats['checkin_present'] += intval($r['c']);
                else
                    $stats['checkin_absent'] += intval($r['c']);
            } else {
                if (intval($r['status']) === 1)
                    $stats['mid_present'] += intval($r['c']);
                else
                    $stats['mid_absent'] += intval($r['c']);
            }
        }
        $sess_table = $wpdb->prefix . 'az_sessions';
        $sessions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sess_table} WHERE class_id=%d", $class_id));
        $stats['sessions'] = intval($sessions);
        return $stats;
    }
}

