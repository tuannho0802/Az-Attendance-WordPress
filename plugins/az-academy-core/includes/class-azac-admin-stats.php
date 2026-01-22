<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Stats
{
    public static function register()
    {
        add_action('wp_ajax_azac_student_stats', [__CLASS__, 'ajax_student_stats']);
        add_action('wp_ajax_azac_get_reviews', [__CLASS__, 'ajax_get_reviews']);
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
    public static function ajax_get_reviews()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_get_reviews')) {
            wp_send_json_error(['message' => 'Nonce'], 403);
        }
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 20;

        if (!$class_id) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        if (!$is_admin) {
            if ($is_teacher) {
                $teacher_user = intval(get_post_meta($class_id, 'az_teacher_user', true));
                if ($teacher_user !== intval($user->ID)) {
                    wp_send_json_error(['message' => 'Capability'], 403);
                }
            } else {
                wp_send_json_error(['message' => 'Capability'], 403);
            }
        }
        global $wpdb;
        $feedback_table = $wpdb->prefix . 'az_feedback';
        $stars_raw = isset($_POST['stars']) ? sanitize_text_field($_POST['stars']) : '';
        $stars_arr = array_filter(array_map('absint', explode(',', $stars_raw)));
        $where = $wpdb->prepare("class_id=%d", $class_id);
        if ($stars_arr) {
            $stars_arr = array_values(array_intersect($stars_arr, [1,2,3,4,5]));
            if ($stars_arr) {
                $in = implode(',', array_map('intval', $stars_arr));
                $where .= " AND rating IN ({$in})";
            }
        }
        $counts_rows = $wpdb->get_results("SELECT rating, COUNT(*) as c FROM {$feedback_table} WHERE {$where} GROUP BY rating", ARRAY_A);
        $counts = [1=>0,2=>0,3=>0,4=>0,5=>0];
        $total = 0;
        foreach ($counts_rows as $r) {
            $rt = max(1, min(5, intval($r['rating'])));
            $c = intval($r['c']);
            $counts[$rt] += $c;
            $total += $c;
        }
        $avg = floatval($wpdb->get_var("SELECT AVG(rating) FROM {$feedback_table} WHERE {$where}"));
        $pm = $wpdb->postmeta;
        $users = $wpdb->users;

        $offset = ($paged - 1) * $per_page;

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT f.student_id, f.rating, f.comment, f.session_date, COALESCE(u.display_name, p.post_title) AS name, pm.meta_value AS user_id
            FROM {$feedback_table} f
            LEFT JOIN {$pm} pm ON pm.post_id = f.student_id AND pm.meta_key = 'az_user_id'
            LEFT JOIN {$users} u ON u.ID = pm.meta_value
            LEFT JOIN {$wpdb->posts} p ON p.ID = f.student_id
            WHERE {$where}
            ORDER BY f.session_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset), ARRAY_A);

        $list = [];
        foreach ($items as $it) {
            $uid = intval($it['user_id']);
            $avatar = $uid ? get_avatar_url($uid, ['size' => 48]) : '';
            $list[] = [
                'name' => sanitize_text_field($it['name']),
                'rating' => max(1, min(5, intval($it['rating']))),
                'comment' => sanitize_textarea_field($it['comment']),
                'date' => sanitize_text_field($it['session_date']),
                'avatar' => esc_url_raw($avatar),
            ];
        }
        $sess_table = $wpdb->prefix . 'az_sessions';
        $sess_rows = $wpdb->get_col($wpdb->prepare("SELECT session_date FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC", $class_id));
        wp_send_json_success([
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'current_page' => (int) $paged,
            'average' => round($avg, 1),
            'counts' => $counts,
            'items' => $list,
            'sessions' => array_map('sanitize_text_field', (array) $sess_rows),
        ]);
    }
}
