<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Stats
{
    public static function register()
    {
        add_action('wp_ajax_azac_student_stats', [__CLASS__, 'ajax_student_stats']);
    }
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
    public static function ajax_student_stats()
    {
        check_ajax_referer('azac_student_stats', 'nonce');
        $user = wp_get_current_user();
        if (!$user || !in_array('az_student', $user->roles, true)) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
        if (!$student_post_id) {
            wp_send_json_success(['classes' => []]);
        }
        global $wpdb;
        $att_table = $wpdb->prefix . 'az_attendance';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT class_id, attendance_type, status, COUNT(*) as c FROM {$att_table} WHERE student_id=%d GROUP BY class_id, attendance_type, status", $student_post_id), ARRAY_A);
        $map = [];
        foreach ($rows as $r) {
            $cid = intval($r['class_id']);
            if (!isset($map[$cid])) {
                $map[$cid] = [
                    'checkin_present' => 0,
                    'checkin_absent' => 0,
                    'mid_present' => 0,
                    'mid_absent' => 0,
                ];
            }
            if ($r['attendance_type'] === 'check-in') {
                if (intval($r['status']) === 1)
                    $map[$cid]['checkin_present'] += intval($r['c']);
                else
                    $map[$cid]['checkin_absent'] += intval($r['c']);
            } else {
                if (intval($r['status']) === 1)
                    $map[$cid]['mid_present'] += intval($r['c']);
                else
                    $map[$cid]['mid_absent'] += intval($r['c']);
            }
        }
        $classes = [];
        foreach ($map as $cid => $st) {
            $post = get_post($cid);
            if (!$post || $post->post_type !== 'az_class' || get_post_status($cid) !== 'publish')
                continue;
            $sess_table = $wpdb->prefix . 'az_sessions';
            $sess_rows = $wpdb->get_results($wpdb->prepare("SELECT session_date FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC", $cid), ARRAY_A);
            $att_rows = $wpdb->get_results($wpdb->prepare("SELECT session_date, attendance_type, status FROM {$att_table} WHERE class_id=%d AND student_id=%d", $cid, $student_post_id), ARRAY_A);
            $att_map = [];
            foreach ($att_rows as $ar) {
                $d = sanitize_text_field($ar['session_date']);
                if (!isset($att_map[$d])) {
                    $att_map[$d] = ['checkin' => null, 'mid' => null];
                }
                if ($ar['attendance_type'] === 'check-in') {
                    $att_map[$d]['checkin'] = intval($ar['status']) === 1 ? 1 : 0;
                } else {
                    $att_map[$d]['mid'] = intval($ar['status']) === 1 ? 1 : 0;
                }
            }
            $sessions_detail = [];
            foreach ($sess_rows as $sr) {
                $d = sanitize_text_field($sr['session_date']);
                $row = isset($att_map[$d]) ? $att_map[$d] : ['checkin' => null, 'mid' => null];
                $sessions_detail[] = [
                    'date' => $d,
                    'checkin' => $row['checkin'],
                    'mid' => $row['mid'],
                    'link' => admin_url('admin.php?page=azac-classes-list&class_id=' . $cid . '&session_date=' . urlencode($d)),
                ];
            }
            $classes[] = [
                'id' => $cid,
                'title' => get_the_title($cid),
                'link' => get_permalink($cid),
                'checkin' => ['present' => $st['checkin_present'], 'absent' => $st['checkin_absent']],
                'mid' => ['present' => $st['mid_present'], 'absent' => $st['mid_absent']],
                'sessions' => $sessions_detail,
            ];
        }
        wp_send_json_success(['classes' => $classes]);
    }
}
