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
    public static function get_qr_review_url($class_id)
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

    public static function render_pagination($current_page, $total_pages)
    {
        if ($total_pages <= 1) {
            return;
        }
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        echo '<span class="pagination-links">';
        
        $base_url = remove_query_arg('paged');
        
        if ($current_page > 1) {
            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '"><span class="screen-reader-text">Trang trước</span><span aria-hidden="true">‹</span></a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
        }

        echo '<span class="paging-input"><span class="tablenav-paging-text"> ' . $current_page . ' của <span class="total-pages">' . $total_pages . '</span></span></span>';

        if ($current_page < $total_pages) {
            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '"><span class="screen-reader-text">Trang sau</span><span aria-hidden="true">›</span></a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
        }
        
        echo '</span></div></div>';
    }
}
