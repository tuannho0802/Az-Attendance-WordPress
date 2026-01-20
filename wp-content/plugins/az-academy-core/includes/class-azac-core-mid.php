<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Mid
{
    public static function register()
    {
        add_action('wp_ajax_azac_feedback_submit', [__CLASS__, 'ajax_feedback_submit']);
        add_action('wp_ajax_nopriv_azac_feedback_submit', [__CLASS__, 'ajax_feedback_submit']);
    }
    private static function get_qr_review_url($class_id)
    {
        $pages = get_posts([
            'post_type' => 'page',
            'numberposts' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-qr-review.php',
            'post_status' => 'publish',
        ]);
        if (!$pages) {
            $pages = get_posts([
                'post_type' => 'page',
                'numberposts' => 1,
                'meta_key' => '_wp_page_template',
                'meta_value' => 'page-qr-checkin.php',
                'post_status' => 'publish',
            ]);
        }
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
    public static function ajax_feedback_submit()
    {
        check_ajax_referer('azac_feedback_submit', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 5;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        if (!$class_id) {
            wp_send_json_error(['message' => 'Thiếu thông tin lớp học'], 400);
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
        $feedback_table = $wpdb->prefix . 'az_feedback';
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$feedback_table} WHERE student_id=%d AND class_id=%d AND session_date=%s",
                $student_post_id,
                $class_id,
                $today
            )
        );
        if (intval($exists) > 0) {
            wp_send_json_error(['message' => 'Bạn đã gửi đánh giá buổi này'], 400);
        }
        $ok = $wpdb->insert(
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
        if (!$ok) {
            wp_send_json_error(['message' => 'Không thể lưu đánh giá'], 500);
        }
        $fid = intval($wpdb->insert_id);
        wp_send_json_success(['success' => 1, 'feedback_id' => $fid]);
    }
}
