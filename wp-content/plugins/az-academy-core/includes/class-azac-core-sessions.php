<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Sessions
{
    public static function register()
    {
        add_action('init', [__CLASS__, 'ensure_sessions_table'], 1);
        add_action('wp_ajax_azac_add_session', [__CLASS__, 'ajax_add_session']);
        add_action('wp_ajax_azac_update_session', [__CLASS__, 'ajax_update_session']);
        add_action('wp_ajax_azac_list_sessions', [__CLASS__, 'ajax_list_sessions']);
    }
    public static function ensure_sessions_table()
    {
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sess_table));
        if ($exists !== $sess_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql_sessions = "CREATE TABLE {$sess_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                class_id bigint(20) unsigned NOT NULL,
                session_date date NOT NULL,
                session_time time NULL,
                title varchar(191) NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY class_id (class_id),
                KEY session_date (session_date),
                UNIQUE KEY uniq_class_date (class_id, session_date)
            ) {$charset_collate};";
            dbDelta($sql_sessions);
        }
    }
    public static function get_class_sessions($class_id)
    {
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT session_date, session_time FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC", $class_id), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $d = isset($r['session_date']) ? sanitize_text_field($r['session_date']) : '';
            $t = isset($r['session_time']) ? sanitize_text_field($r['session_time']) : '';
            if ($d) {
                $out[] = ['date' => $d, 'time' => $t];
            }
        }
        return $out;
    }
    public static function upsert_class_session($class_id, $date, $time)
    {
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $wpdb->replace(
            $sess_table,
            [
                'class_id' => $class_id,
                'session_date' => $date,
                'session_time' => $time,
            ],
            ['%d', '%s', '%s']
        );
        return self::get_class_sessions($class_id);
    }
    public static function update_class_session($class_id, $old_date, $new_date, $new_time)
    {
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $wpdb->update(
            $sess_table,
            ['session_date' => $new_date, 'session_time' => $new_time],
            ['class_id' => $class_id, 'session_date' => $old_date],
            ['%s', '%s'],
            ['%d', '%s']
        );
        $att_table = $wpdb->prefix . 'az_attendance';
        $wpdb->update(
            $att_table,
            ['session_date' => $new_date],
            ['class_id' => $class_id, 'session_date' => $old_date],
            ['%s'],
            ['%d', '%s']
        );
        return self::get_class_sessions($class_id);
    }
    public static function ajax_add_session()
    {
        check_ajax_referer('azac_session', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        if (!$class_id || !$date) {
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
        $sessions = self::upsert_class_session($class_id, $date, $time);
        wp_send_json_success(['sessions' => $sessions, 'selected' => $date]);
    }
    public static function ajax_update_session()
    {
        check_ajax_referer('azac_session', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
        $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';
        if (!$class_id || !$date || !$new_date) {
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
        $sessions = self::update_class_session($class_id, $date, $new_date, $new_time);
        wp_send_json_success(['sessions' => $sessions, 'selected' => $new_date]);
    }
    public static function ajax_list_sessions()
    {
        check_ajax_referer('azac_list_sessions', 'nonce');
        global $wpdb;
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $is_student = in_array('az_student', $user->roles, true);
        if (!$is_admin && !$is_teacher && !$is_student) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        $classes = [];
        if ($is_admin || $is_teacher) {
            $args = [
                'post_type' => 'az_class',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => $is_admin ? ['publish', 'pending'] : ['publish'],
            ];
            if ($is_teacher && !$is_admin) {
                $args['meta_key'] = 'az_teacher_user';
                $args['meta_value'] = intval($user->ID);
            }
            $classes = get_posts($args);
        } else {
            $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => ['publish'],
            ]);
            $classes = array_filter($classes, function ($c) use ($student_post_id) {
                $ids = get_post_meta($c->ID, 'az_students', true);
                $ids = is_array($ids) ? array_map('absint', $ids) : [];
                return in_array($student_post_id, $ids, true);
            });
        }
        $out = [];
        foreach ($classes as $c) {
            $sessions = self::get_class_sessions($c->ID);
            foreach ($sessions as $s) {
                $att_table = $wpdb->prefix . 'az_attendance';
                $att_rows = $wpdb->get_results($wpdb->prepare("SELECT attendance_type, status, COUNT(*) as c FROM {$att_table} WHERE class_id=%d AND session_date=%s GROUP BY attendance_type, status", $c->ID, $s['date']), ARRAY_A);
                $checkin_present = 0;
                $checkin_absent = 0;
                $mid_present = 0;
                $mid_absent = 0;
                foreach ($att_rows as $ar) {
                    if ($ar['attendance_type'] === 'check-in') {
                        if (intval($ar['status']) === 1)
                            $checkin_present += intval($ar['c']);
                        else
                            $checkin_absent += intval($ar['c']);
                    } else {
                        if (intval($ar['status']) === 1)
                            $mid_present += intval($ar['c']);
                        else
                            $mid_absent += intval($ar['c']);
                    }
                }
                $rate_checkin = ($checkin_present + $checkin_absent) > 0 ? round(($checkin_present / ($checkin_present + $checkin_absent)) * 100) : 0;
                $rate_mid = ($mid_present + $mid_absent) > 0 ? round(($mid_present / ($mid_present + $mid_absent)) * 100) : 0;
                $rate_overall = round(($rate_checkin + $rate_mid) / 2);
                $out[] = [
                    'class_id' => $c->ID,
                    'class_title' => $c->post_title,
                    'date' => $s['date'],
                    'time' => $s['time'],
                    'link' => admin_url('admin.php?page=azac-class-dashboard&class_id=' . $c->ID . '&session_date=' . urlencode($s['date'])),
                    'checkin' => ['present' => $checkin_present, 'absent' => $checkin_absent],
                    'mid' => ['present' => $mid_present, 'absent' => $mid_absent],
                    'rate' => ['checkin' => $rate_checkin, 'mid' => $rate_mid, 'overall' => $rate_overall],
                ];
            }
        }
        wp_send_json_success(['sessions' => $out]);
    }
}
