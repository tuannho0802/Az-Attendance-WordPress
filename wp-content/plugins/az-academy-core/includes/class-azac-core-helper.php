<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Helper
{
    public static function get_class_students($class_id)
    {
        $ids = get_post_meta($class_id, 'az_students', true);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
        if (!$ids) {
            return [];
        }
        $posts = get_posts([
            'post_type' => 'az_student',
            'post__in' => $ids,
            'numberposts' => -1,
            'orderby' => 'post__in',
        ]);
        return $posts;
    }
    public static function get_current_student_post_id()
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return 0;
        $posts = get_posts([
            'post_type' => 'az_student',
            'numberposts' => 1,
            'meta_key' => 'az_user_id',
            'meta_value' => $user_id,
        ]);
        return $posts ? $posts[0]->ID : 0;
    }
    public static function is_student_in_class($student_post_id, $class_id)
    {
        $ids = get_post_meta($class_id, 'az_students', true);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
        return $student_post_id && in_array($student_post_id, $ids, true);
    }
    public static function get_qr_checkin_url($class_id)
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
}
