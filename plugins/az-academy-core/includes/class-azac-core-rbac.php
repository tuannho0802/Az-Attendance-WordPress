<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_RBAC
{
    public static function register()
    {
        add_action('admin_bar_menu', [__CLASS__, 'cleanup_admin_bar'], 999);
        add_filter('map_meta_cap', [__CLASS__, 'map_meta_cap_for_class'], 10, 4);
        add_action('admin_init', [__CLASS__, 'redirect_cpt_list_to_custom']);
        add_action('init', [__CLASS__, 'ensure_teacher_caps'], 2);
        add_action('init', [__CLASS__, 'ensure_manager_caps'], 2);
        add_filter('wp_insert_post_data', [__CLASS__, 'prevent_teacher_pending'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'remove_default_menus'], 99);
    }
    public static function cleanup_admin_bar($wp_admin_bar)
    {
        $user = wp_get_current_user();
        if (in_array('administrator', $user->roles, true)) {
            return;
        }
        $nodes = ['wp-logo', 'about', 'wporg', 'documentation', 'support-forums', 'feedback', 'updates', 'comments', 'new-content', 'customize', 'appearance', 'themes', 'users', 'search', 'site-name'];
        foreach ($nodes as $n) {
            $wp_admin_bar->remove_node($n);
        }
    }
    public static function map_meta_cap_for_class($caps, $cap, $user_id, $args)
    {
        if (in_array($cap, ['edit_post', 'read_post', 'delete_post', 'publish_post'], true) && !empty($args[0])) {
            $post_id = absint($args[0]);
            $post = get_post($post_id);
            if ($post && $post->post_type === 'az_class') {
                $user = get_user_by('id', $user_id);
                if ($user && in_array('az_teacher', $user->roles, true)) {
                    $assigned_teacher = intval(get_post_meta($post_id, 'az_teacher_user', true));
                    if ($assigned_teacher === $user_id) {
                        if ($cap === 'read_post') {
                            return ['read'];
                        } elseif ($cap === 'edit_post') {
                            return ['edit_posts'];
                        } elseif ($cap === 'delete_post') {
                            return ['delete_posts'];
                        } elseif ($cap === 'publish_post') {
                            return ['edit_posts'];
                        }
                    }
                }
            }
        }
        return $caps;
    }
    public static function redirect_cpt_list_to_custom()
    {
        if (!is_admin())
            return;
        $page = basename($_SERVER['PHP_SELF']);
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        if ($page === 'edit.php' && in_array($post_type, ['az_class', 'az_student'], true)) {
            if ($post_type === 'az_class') {
                wp_redirect(admin_url('admin.php?page=azac-classes-list'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=azac-students-list'));
                exit;
            }
        }
        if ($page === 'post-new.php' && $post_type === 'az_class') {
            $user = wp_get_current_user();
            if ($user && in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
                wp_redirect(admin_url('admin.php?page=azac-classes-list'));
                exit;
            }
        }
    }
    public static function ensure_teacher_caps()
    {
        $role = get_role('az_teacher');
        if ($role) {
            if (!$role->has_cap('publish_posts')) {
                $role->add_cap('publish_posts');
            }
            if (!$role->has_cap('edit_published_posts')) {
                $role->add_cap('edit_published_posts');
            }
        }
    }
    public static function ensure_manager_caps()
    {
        $role = get_role('az_manager');
        if (!$role) {
            add_role('az_manager', 'Quản lý đào tạo', [
                'read' => true,
                'level_7' => true,
            ]);
            $role = get_role('az_manager');
        }

        if ($role) {
            $caps = [
                'edit_posts',
                'edit_others_posts',
                'publish_posts',
                'read_private_posts',
                'delete_posts',
                'delete_others_posts',
                'delete_published_posts',
                'edit_published_posts',
                'manage_categories',
                'upload_files',
                'list_users',
                'create_users',
                'edit_users',
                'delete_users',
                'promote_users'
            ];
            foreach ($caps as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    public static function prevent_teacher_pending($data, $postarr)
    {
        if (!is_admin())
            return $data;
        $post_type = isset($postarr['post_type']) ? $postarr['post_type'] : '';
        $user = wp_get_current_user();
        if (!$user || !in_array('az_teacher', $user->roles, true))
            return $data;
        $new_status = isset($data['post_status']) ? $data['post_status'] : '';
        if ($new_status !== 'pending')
            return $data;
        $old_status = '';
        $post_id = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
        if ($post_id) {
            $old = get_post($post_id);
            if ($old && $old->post_type === 'az_class') {
                $old_status = $old->post_status;
            }
        }
        if ($old_status === 'publish') {
            $data['post_status'] = 'publish';
        }
        return $data;
    }
    public static function remove_default_menus()
    {
        $user = wp_get_current_user();
        if (in_array('administrator', $user->roles, true)) {
            return;
        }
        if (in_array('az_teacher', $user->roles, true)) {
            remove_menu_page('index.php');
            remove_menu_page('index.php');
            remove_menu_page('edit.php');
            remove_menu_page('edit-comments.php');
            remove_menu_page('plugins.php');
            remove_menu_page('tools.php');
            remove_menu_page('options-general.php');
            remove_menu_page('themes.php');
            remove_menu_page('edit.php?post_type=az_class');
            remove_menu_page('edit.php?post_type=az_student');
            remove_menu_page('users.php');
        } elseif (in_array('az_student', $user->roles, true)) {
            remove_menu_page('index.php');
            remove_menu_page('edit.php');
            remove_menu_page('upload.php');
            remove_menu_page('plugins.php');
            remove_menu_page('tools.php');
            remove_menu_page('options-general.php');
            remove_menu_page('themes.php');
            remove_menu_page('users.php');
            remove_menu_page('edit.php?post_type=az_class');
            remove_menu_page('edit.php?post_type=az_student');
        }
    }
}
