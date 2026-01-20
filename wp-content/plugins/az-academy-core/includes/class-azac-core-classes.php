<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Classes
{
    public static function register()
    {
        add_action('wp_ajax_azac_update_class_status', [__CLASS__, 'ajax_update_class_status']);
        add_action('wp_ajax_azac_delete_class', [__CLASS__, 'ajax_delete_class']);
        add_action('wp_ajax_azac_create_class', [__CLASS__, 'ajax_create_class']);
    }
    public static function ajax_update_class_status()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_update_class_status')) {
            wp_send_json_error(['message' => 'Nonce'], 403);
        }
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        if (!$class_id || !in_array($status, ['publish', 'pending'], true)) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'az_class') {
            wp_send_json_error(['message' => 'Not found'], 404);
        }
        $res = wp_update_post(['ID' => $class_id, 'post_status' => $status], true);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => 'Update failed'], 500);
        }
        wp_send_json_success(['id' => $class_id, 'status' => $status]);
    }
    public static function ajax_delete_class()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_delete_class')) {
            wp_send_json_error(['message' => 'Nonce'], 403);
        }
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!$class_id) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'az_class') {
            wp_send_json_error(['message' => 'Not found'], 404);
        }
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $att_table = $wpdb->prefix . 'az_attendance';
        $wpdb->delete($sess_table, ['class_id' => $class_id], ['%d']);
        $wpdb->delete($att_table, ['class_id' => $class_id], ['%d']);
        $res = wp_delete_post($class_id, true);
        if (!$res) {
            wp_send_json_error(['message' => 'Delete failed'], 500);
        }
        wp_send_json_success(['id' => $class_id]);
    }
    public static function ajax_create_class()
    {
        check_ajax_referer('azac_create_class', 'nonce');
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $teacher_id = isset($_POST['teacher_id']) ? absint($_POST['teacher_id']) : 0;
        $teacher = isset($_POST['teacher']) ? sanitize_text_field($_POST['teacher']) : '';
        $sessions = isset($_POST['sessions']) ? absint($_POST['sessions']) : 0;
        if (!$title) {
            wp_send_json_error(['message' => 'Thiếu tên lớp'], 400);
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            wp_send_json_error(['message' => 'Không đủ quyền'], 403);
        }
        $post_id = wp_insert_post([
            'post_type' => 'az_class',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);
        if (is_wp_error($post_id) || !$post_id) {
            wp_send_json_error(['message' => 'Tạo lớp thất bại'], 500);
        }
        update_post_meta($post_id, 'az_tong_so_buoi', $sessions ?: 0);
        update_post_meta($post_id, 'az_so_hoc_vien', 0);
        if ($teacher_id) {
            $u = get_userdata($teacher_id);
            if ($u && in_array('az_teacher', $u->roles, true)) {
                update_post_meta($post_id, 'az_teacher_user', $teacher_id);
                update_post_meta($post_id, 'az_giang_vien', $u->display_name ?: $u->user_login);
            }
        } else {
            if ($teacher) {
                update_post_meta($post_id, 'az_giang_vien', $teacher);
            }
        }
        wp_send_json_success([
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'link' => admin_url('admin.php?page=azac-classes-list&class_id=' . $post_id),
        ]);
    }
}
