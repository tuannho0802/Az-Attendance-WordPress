<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Mid
{
    public static function register()
    {
        add_action('wp_ajax_azac_generate_mid_pin', [__CLASS__, 'ajax_generate_mid_pin']);
        add_action('wp_ajax_azac_close_mid_pin', [__CLASS__, 'ajax_close_mid_pin']);
        add_action('wp_ajax_azac_mid_session_submit', [__CLASS__, 'ajax_mid_session_submit']);
    }
    private static function get_qr_checkin_url($class_id)
    {
        $pages = get_posts([
            'post_type' => 'page',
            'numberposts' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-qr-checkin.php',
            'post_status' => 'publish',
        ]);
        if ($pages) {
            $url = get_permalink($pages[0]->ID);
            return add_query_arg(['class_id' => $class_id], $url);
        }
        return add_query_arg(['class_id' => $class_id], site_url('/'));
    }
    private static function get_current_student_post_id()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 0;
        }
        $posts = get_posts([
            'post_type' => 'az_student',
            'numberposts' => 1,
            'meta_key' => 'az_user_id',
            'meta_value' => $user_id,
        ]);
        return $posts ? $posts[0]->ID : 0;
    }
    private static function is_student_in_class($student_post_id, $class_id)
    {
        $ids = get_post_meta($class_id, 'az_students', true);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
        return $student_post_id && in_array($student_post_id, $ids, true);
    }
    public static function ajax_generate_mid_pin()
    {
        check_ajax_referer('azac_mid', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $session_date = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';
        if (!$class_id || !$session_date) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $today = current_time('Y-m-d');
        if ($session_date !== $today) {
            wp_send_json_error(['message' => 'Chỉ cho phép điểm danh trong ngày'], 403);
        }
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        if (!$is_admin && !$is_teacher) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        if ($is_teacher) {
            $assigned = intval(get_post_meta($class_id, 'az_teacher_user', true));
            if ($assigned !== intval($user->ID)) {
                wp_send_json_error(['message' => 'Capability'], 403);
            }
        }
        global $wpdb;
        $codes_table = $wpdb->prefix . 'az_session_codes';
        $pin = AzAC_Core_Activator::generate_pin_code(6);
        $wpdb->replace(
            $codes_table,
            [
                'class_id' => $class_id,
                'session_date' => $today,
                'pin_code' => $pin,
                'is_active' => 1,
            ],
            ['%d', '%s', '%s', '%d']
        );
        $url = self::get_qr_checkin_url($class_id);
        wp_send_json_success(['pin_code' => $pin, 'url' => $url]);
    }
    public static function ajax_close_mid_pin()
    {
        check_ajax_referer('azac_mid_close', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!$class_id) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        if (!$is_admin && !$is_teacher) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        if ($is_teacher) {
            $assigned = intval(get_post_meta($class_id, 'az_teacher_user', true));
            if ($assigned !== intval($user->ID)) {
                wp_send_json_error(['message' => 'Capability'], 403);
            }
        }
        global $wpdb;
        $codes_table = $wpdb->prefix . 'az_session_codes';
        $today = current_time('Y-m-d');
        $wpdb->update(
            $codes_table,
            ['is_active' => 0],
            ['class_id' => $class_id, 'session_date' => $today],
            ['%d'],
            ['%d', '%s']
        );
        wp_send_json_success(['closed' => 1]);
    }
    public static function ajax_mid_session_submit()
    {
        check_ajax_referer('azac_mid_submit', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $pin_code = isset($_POST['pin_code']) ? sanitize_text_field($_POST['pin_code']) : '';
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 5;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        if (!$class_id || !$pin_code) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        if (!$user || !in_array('az_student', $user->roles, true)) {
            wp_send_json_error(['message' => 'Yêu cầu đăng nhập bằng Học viên'], 403);
        }
        $student_post_id = self::get_current_student_post_id();
        if (!self::is_student_in_class($student_post_id, $class_id)) {
            wp_send_json_error(['message' => 'Không thuộc lớp này'], 403);
        }
        global $wpdb;
        $today = current_time('Y-m-d');
        $codes_table = $wpdb->prefix . 'az_session_codes';
        $row = $wpdb->get_row($wpdb->prepare("SELECT pin_code, is_active FROM {$codes_table} WHERE class_id=%d AND session_date=%s", $class_id, $today), ARRAY_A);
        if (!$row || intval($row['is_active']) !== 1 || $row['pin_code'] !== $pin_code) {
            wp_send_json_error(['message' => 'Mã PIN không chính xác hoặc đã hết hạn'], 400);
        }
        $att_table = $wpdb->prefix . 'az_attendance';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$att_table} WHERE class_id=%d AND student_id=%d AND session_date=%s AND attendance_type='mid-session'", $class_id, $student_post_id, $today));
        if (intval($exists) > 0) {
            wp_send_json_error(['message' => 'Bạn đã điểm danh giữa giờ buổi này'], 400);
        }
        $feedback_table = $wpdb->prefix . 'az_feedback';
        $wpdb->insert(
            $feedback_table,
            [
                'student_id' => $student_post_id,
                'class_id' => $class_id,
                'session_date' => $today,
                'rating' => max(1, min(5, $rating)),
                'comment' => $comment,
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );
        $fid = intval($wpdb->insert_id);
        $wpdb->insert(
            $att_table,
            [
                'class_id' => $class_id,
                'student_id' => $student_post_id,
                'session_date' => $today,
                'attendance_type' => 'mid-session',
                'status' => 1,
                'note' => '',
                'feedback_id' => $fid ?: null,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d']
        );
        wp_send_json_success(['success' => 1, 'feedback_id' => $fid]);
    }
}
