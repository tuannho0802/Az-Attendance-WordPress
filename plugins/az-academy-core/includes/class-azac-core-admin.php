<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Admin
{
    // Standard Pastel Palette for Unified Hash Logic
    public static $palette = [
        "#2ecc71",
        "#3498db",
        "#9b59b6",
        "#e67e22",
        "#1abc9c",
        "#e74c3c",
        "#16a085",
        "#2980b9",
        "#8e44ad",
        "#d35400",
        "#27ae60",
        "#f39c12",
        "#34495e",
        "#7f8c8d",
        "#e84393",
        "#00cec9",
        "#6c5ce7",
        "#fdcb6e",
        "#00b894",
        "#0984e3",
        "#d63031",
        "#ff7675",
        "#636e72",
        "#55efc4",
        "#a29bfe",
        "#fab1a0",
        "#74b9ff",
        "#b2bec3",
        "#ff6b6b",
        "#4dabf7",
        "#f4a261",
        "#2a9d8f",
        "#e76f51",
        "#264653"
    ];

    public static function register()
    {
        add_action('admin_menu', ['AzAC_Admin_Pages', 'register_admin_pages']);
        add_action('admin_enqueue_scripts', ['AzAC_Admin_Assets', 'enqueue_admin_assets']);

        // Hide Menus & Actions logic
        add_action('admin_menu', [__CLASS__, 'hide_admin_menus'], 999);

        // Redirect Login for Manager/Teacher
        add_filter('login_redirect', [__CLASS__, 'custom_login_redirect'], 9999, 3);

        // Row Actions & Bulk Actions Filters
        add_filter('post_row_actions', [__CLASS__, 'remove_row_actions'], 10, 2);
        add_filter('page_row_actions', [__CLASS__, 'remove_row_actions'], 10, 2);
        add_filter('media_row_actions', [__CLASS__, 'remove_row_actions'], 10, 2); // Media
        add_filter('user_row_actions', [__CLASS__, 'remove_user_row_actions'], 10, 2); // Users

        // Apply bulk action filters for common and custom post types
        $post_types = ['post', 'page', 'az_class', 'az_student', 'az_lesson', 'attachment', 'user']; // Added attachment, user
        foreach ($post_types as $pt) {
            add_filter("bulk_actions-edit-{$pt}", [__CLASS__, 'remove_bulk_actions']);
        }

        // Hotfix: Ensure Manager Role is updated (Run once)
        add_action('admin_init', [__CLASS__, 'ensure_manager_capabilities']);
        add_action('admin_init', ['AzAC_Admin_Pages', 'early_redirect_back_to_home']);
        add_action('admin_init', ['AzAC_Admin_Pages', 'handle_export_students_xlsx']);
        add_action('admin_init', ['AzAC_Admin_Pages', 'handle_export_classes_excel']);

        // Hide "Delete Permanently" in Media Grid via CSS
        add_action('admin_head', [__CLASS__, 'hide_delete_ui_css']);

        // Remove Welcome Panel
        add_action('admin_init', [__CLASS__, 'remove_welcome_panel']);

        // Force redirect from Dashboard to Attendance page
        add_action('load-index.php', [__CLASS__, 'force_dashboard_redirect']);
    }

    public static function force_dashboard_redirect()
    {
        $user = wp_get_current_user();

        // Ensure user is logged in
        if (!$user->exists()) {
            return;
        }

        // Target: Attendance Page
        $target_url = admin_url('admin.php?page=azac-attendance');

        // Check if user is student and lacks admin access
        if (in_array('az_student', (array) $user->roles) && !$user->has_cap('read')) {
            wp_safe_redirect(home_url());
            exit;
        }

        // Redirect all users (Admin, Manager, Teacher, Student) from Dashboard to Attendance
        // Check if we are already on the target page (load-index.php means we are on dashboard, so we are NOT on target page)
        wp_safe_redirect($target_url);
        exit;
    }

    public static function remove_welcome_panel()
    {
        remove_action('welcome_panel', 'wp_welcome_panel');
    }

    public static function ensure_manager_capabilities()
    {
        if (!get_option('azac_manager_role_v7_updated')) {
            $role = get_role('az_manager');
            if ($role) {
                // Core
                $role->add_cap('manage_options');
                $role->add_cap('edit_theme_options');
                $role->add_cap('upload_files');
                // Content
                $caps = [
                    'edit_posts',
                    'edit_others_posts',
                    'edit_published_posts',
                    'publish_posts',
                    'edit_pages',
                    'edit_others_pages',
                    'edit_published_pages',
                    'publish_pages',
                    'list_users',
                    'create_users',
                    'edit_users',
                    'promote_users',
                    'manage_categories',
                    'az_manage_attendance',
                    'az_view_system',
                    'az_manage_classes',
                    'az_manage_students',
                    'az_manage_teachers',
                    'az_take_attendance'
                ];
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
                // Explicitly remove delete
                $role->remove_cap('delete_posts');
                $role->remove_cap('delete_others_posts');
                $role->remove_cap('delete_published_posts');
                $role->remove_cap('delete_private_posts');
                $role->remove_cap('delete_pages');
                $role->remove_cap('delete_others_pages');
                $role->remove_cap('delete_published_pages');
                $role->remove_cap('delete_private_pages');
                $role->remove_cap('delete_users');
                $role->remove_cap('delete_attachments');
            }
            update_option('azac_manager_role_v7_updated', 1);
        }
    }

    public static function hide_admin_menus()
    {
        // Admin (can delete users) sees everything
        if (current_user_can('delete_users')) {
            return;
        }

        // For Managers (and others): Hide sensitive system menus
        // REMOVED: Dashboard, Posts, Pages, Comments are now ALLOWED for Manager

        remove_menu_page('themes.php');                 // Appearance
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('tools.php');                  // Tools
        remove_menu_page('options-general.php');        // Settings

        // Note: Media, Users, LMS menus are KEPT visible.
    }

    public static function custom_login_redirect($redirect_to, $request, $user)
    {
        // Check for login errors
        if (is_wp_error($user)) {
            return $redirect_to;
        }

        // Ensure we have a user object
        if (!($user instanceof WP_User)) {
            return $redirect_to;
        }

        // Target: Attendance Page
        $target_url = admin_url('admin.php?page=azac-attendance');

        // Check if user is student and lacks admin access
        if (in_array('az_student', (array) $user->roles) && !$user->has_cap('read')) {
            return home_url();
        }

        // All other users (Admin, Manager, Teacher, Student with access) go to Attendance
        return $target_url;
    }

    public static function hide_delete_ui_css()
    {
        if (!current_user_can('delete_users')) {
            echo '<style>
                .submitdelete, .button-link-delete, .delete-permanently, .delete { display: none !important; }
                /* Hide Delete in Bulk Actions */
                option[value="delete"], option[value="trash"] { display: none !important; }
                /* Hide System Cleanup Bulk Actions */
                #azac-do-system-bulk, #azac-bulk-action-system, 
                #azac-log-bulk-action, #azac-log-do-bulk,
                .azac-delete-system-item { display: none !important; }
                /* Hide Media Grid Delete */
                .media-modal .delete-attachment, .media-modal .trash-attachment { display: none !important; }
                /* Hide User Delete */
                .users-php .submitdelete { display: none !important; }
            </style>';
        }
    }

    public static function remove_row_actions($actions, $post)
    {
        if (current_user_can('delete_users')) {
            return $actions;
        }
        if (isset($actions['trash']))
            unset($actions['trash']);
        if (isset($actions['delete']))
            unset($actions['delete']);
        return $actions;
    }

    public static function remove_user_row_actions($actions, $user)
    {
        if (current_user_can('delete_users')) {
            return $actions;
        }
        if (isset($actions['delete']))
            unset($actions['delete']);
        return $actions;
    }

    public static function remove_bulk_actions($actions)
    {
        if (current_user_can('delete_users')) {
            return $actions;
        }
        if (isset($actions['trash']))
            unset($actions['trash']);
        if (isset($actions['delete']))
            unset($actions['delete']);
        return $actions;
    }

    public static function get_students_admin_summary($class_id = 0)
    {
        global $wpdb;
        $att = $wpdb->prefix . 'az_attendance';
        $rows = [];
        $format_date = function ($d) {
            $parts = explode('-', sanitize_text_field($d));
            if (count($parts) === 3) {
                return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
            }
            return sanitize_text_field($d);
        };
        if ($class_id) {
            $students = AzAC_Core_Helper::get_class_students($class_id);
            $ids = [];
            foreach ($students as $p) {
                $uid = intval(get_post_meta($p->ID, 'az_user_id', true));
                if ($uid) {
                    $u = get_userdata($uid);
                    if ($u && in_array('az_student', $u->roles, true)) {
                        $ids[] = intval($p->ID);
                    }
                }
            }
            foreach ($ids as $sid) {
                $agg = $wpdb->get_results($wpdb->prepare("
                    SELECT session_date,
                        MAX(CASE WHEN attendance_type='check-in' AND status=1 THEN 1 ELSE 0 END) AS ch_present,
                        MAX(CASE WHEN attendance_type!='check-in' AND status=1 THEN 1 ELSE 0 END) AS mid_present
                    FROM {$att}
                    WHERE class_id=%d AND student_id=%d
                    GROUP BY session_date
                ", $class_id, $sid), ARRAY_A);
                $joined = 0;
                $issues = [];
                foreach ($agg as $a) {
                    $ch = intval($a['ch_present']);
                    $mid = intval($a['mid_present']);
                    if ($ch || $mid)
                        $joined++;
                    $type = ($ch === 1 && $mid === 1) ? 'ok' : (($ch + $mid) === 0 ? 'absent' : 'half');
                    $missing = [];
                    if ($ch === 0)
                        $missing[] = 'checkin';
                    if ($mid === 0)
                        $missing[] = 'mid';
                    $issues[] = [
                        'date' => $format_date($a['session_date']),
                        'type' => $type,
                        'missing' => $missing,
                        'ch' => $ch,
                        'mid' => $mid,
                    ];
                }
                $rows[] = [
                    'student_id' => $sid,
                    'name' => get_the_title($sid),
                    'classes' => [get_the_title($class_id)],
                    'joined' => $joined,
                    'issues' => $issues,
                ];
            }
            return $rows;
        }
        $users = get_users(['role' => 'az_student']);
        $current_user = wp_get_current_user();
        $allowed_class_ids = null;
        if (in_array('az_teacher', $current_user->roles, true) && !in_array('administrator', $current_user->roles, true)) {
            $allowed_class_ids = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => $current_user->ID,
                'fields' => 'ids'
            ]);
            $teacher_classes = $allowed_class_ids;
            $student_post_ids = [];
            foreach ($teacher_classes as $cid) {
                $s_ids = get_post_meta($cid, 'az_students', true);
                if (is_array($s_ids)) {
                    $student_post_ids = array_merge($student_post_ids, $s_ids);
                }
            }
            $student_post_ids = array_unique($student_post_ids);
            $allowed_user_ids = [];
            foreach ($student_post_ids as $spid) {
                $uid = get_post_meta($spid, 'az_user_id', true);
                if ($uid)
                    $allowed_user_ids[] = intval($uid);
            }
            $users = array_filter($users, function ($u) use ($allowed_user_ids) {
                return in_array($u->ID, $allowed_user_ids);
            });
        }
        foreach ($users as $u) {
            $sid = 0;
            $posts = get_posts([
                'post_type' => 'az_student',
                'numberposts' => 1,
                'meta_key' => 'az_user_id',
                'meta_value' => intval($u->ID),
            ]);
            if ($posts) {
                $sid = intval($posts[0]->ID);
            }
            if (!$sid)
                continue;
            $classes = [];
            $agg = $wpdb->get_results($wpdb->prepare("
                SELECT class_id, session_date,
                    MAX(CASE WHEN attendance_type='check-in' AND status=1 THEN 1 ELSE 0 END) AS ch_present,
                    MAX(CASE WHEN attendance_type!='check-in' AND status=1 THEN 1 ELSE 0 END) AS mid_present
                FROM {$att}
                WHERE student_id=%d
                GROUP BY class_id, session_date
            ", $sid), ARRAY_A);
            $joined = 0;
            $issues = [];
            foreach ($agg as $a) {
                $ch = intval($a['ch_present']);
                $mid = intval($a['mid_present']);
                $cid = intval($a['class_id']);
                if ($cid) {
                    if (is_array($allowed_class_ids) && !in_array($cid, $allowed_class_ids)) {
                        continue;
                    }
                    $classes[$cid] = get_the_title($cid);
                    if ($ch || $mid)
                        $joined++;
                    $type = ($ch === 1 && $mid === 1) ? 'ok' : (($ch + $mid) === 0 ? 'absent' : 'half');
                    $missing = [];
                    if ($ch === 0)
                        $missing[] = 'checkin';
                    if ($mid === 0)
                        $missing[] = 'mid';
                    $issues[] = [
                        'date' => $format_date($a['session_date']),
                        'type' => $type,
                        'missing' => $missing,
                        'ch' => $ch,
                        'mid' => $mid,
                    ];
                }
            }
            $rows[] = [
                'student_id' => $sid,
                'name' => $u->display_name ?: $u->user_login,
                'classes' => array_values($classes),
                'joined' => $joined,
                'issues' => $issues,
            ];
        }
        return $rows;
    }
    public static function get_teachers_admin_summary($month_filter = '')
    {
        $users = get_users(['role' => 'az_teacher']);
        $rows = [];
        $palette = self::$palette;

        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';

        foreach ($users as $u) {
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => intval($u->ID),
                'post_status' => ['publish', 'pending'],
            ]);

            foreach ($classes as $c) {
                // Name-based ASCII Hash (Standardized)
                $hash = 0;
                $c_name = $c->post_title;
                for ($i = 0; $i < strlen($c_name); $i++) {
                    $hash += ord($c_name[$i]);
                }
                $color = $palette[$hash % count($palette)];

                // Student Count
                $ids = get_post_meta($c->ID, 'az_students', true);
                $ids = is_array($ids) ? array_map('absint', $ids) : [];
                $user_ids = [];
                foreach ($ids as $sid) {
                    $uid = intval(get_post_meta($sid, 'az_user_id', true));
                    if ($uid)
                        $user_ids[$uid] = true;
                }
                $total_students = count($user_ids);

                // Stats per class
                $total_sessions = 0;
                $checked_sessions = 0;
                
                // Query sessions for THIS class only
                $sql = "SELECT teacher_checkin FROM {$sess_table} WHERE class_id = %d";
                $params = [$c->ID];

                if ($month_filter) {
                    $sql .= " AND DATE_FORMAT(session_date, '%Y-%m') = %s";
                    $params[] = $month_filter;
                }

                $sessions = $wpdb->get_results($wpdb->prepare($sql, $params));
                $total_sessions = count($sessions);
                foreach ($sessions as $s) {
                    if (intval($s->teacher_checkin) === 1) {
                        $checked_sessions++;
                    }
                }

                // If filtering by month, skip classes with no sessions in that month
                if ($month_filter && $total_sessions === 0) {
                    continue;
                }

                $missing = $total_sessions - $checked_sessions;
                
                $rows[] = [
                    'id' => $u->ID,
                    'name' => $u->display_name ?: $u->user_login,
                    'class_name' => $c->post_title,
                    'class_link' => admin_url('admin.php?page=azac-classes-list&class_id=' . intval($c->ID)),
                    'class_color' => $color,
                    'students_total' => $total_students,
                    'stats' => [
                        'total' => $total_sessions,
                        'checked' => $checked_sessions,
                        'missing' => $missing
                    ],
                    'detail_link' => admin_url('admin.php?page=azac-classes-list&class_id=' . intval($c->ID))
                ];
            }
        }
        return $rows;
    }
    public static function register_admin_pages()
    {
        add_menu_page(
            'Quản lý điểm danh',
            'Quản lý điểm danh',
            'read',
            'azac-attendance',
            [__CLASS__, 'render_attendance_list_page'],
            'dashicons-yes',
            3
        );
        add_submenu_page(
            'azac-attendance',
            'Lớp học',
            'Lớp học',
            'edit_posts',
            'azac-classes-list',
            [__CLASS__, 'render_classes_list_page']
        );
        add_submenu_page(
            'azac-attendance',
            'Học viên',
            'Học viên',
            'edit_posts',
            'azac-students-list',
            [__CLASS__, 'render_students_list_page']
        );
        
    }
    public static function enqueue_admin_assets($hook)
    {
        wp_enqueue_style('azac-admin-style', AZAC_CORE_URL . 'admin/css/admin-style.css', [], AZAC_CORE_VERSION);
        wp_enqueue_script('azac-admin-js', AZAC_CORE_URL . 'admin/js/admin.js', ['jquery'], AZAC_CORE_VERSION, true);
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'az_class' && in_array($hook, ['post.php', 'post-new.php'], true)) {
            wp_enqueue_script('azac-class-edit-js', AZAC_CORE_URL . 'admin/js/class-edit.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_style('azac-class-edit-style', AZAC_CORE_URL . 'admin/css/class-edit.css', [], AZAC_CORE_VERSION);
            $user = wp_get_current_user();
            wp_localize_script('azac-class-edit-js', 'AZAC_CLASS_EDIT', [
                'isTeacher' => in_array('az_teacher', $user->roles, true),
            ]);
        }
        if ($hook === 'toplevel_page_azac-attendance') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-stats-js', AZAC_CORE_URL . 'admin/js/attendance-list-stats.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            $user = wp_get_current_user();
            $azac_list = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isManager' => in_array('az_manager', (array) $user->roles),
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'studentStatsNonce' => wp_create_nonce('azac_student_stats'),
                'palette' => self::$palette, // Master Palette
            ];
            wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $azac_list);
            wp_localize_script('azac-attendance-list-stats-js', 'AZAC_LIST', $azac_list);
        }
        
        if ($hook === 'azac-attendance_page_azac-classes-list') {
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-stats-js', AZAC_CORE_URL . 'admin/js/attendance-list-stats.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            $user = wp_get_current_user();
            $azac_list_cls = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isManager' => in_array('az_manager', (array) $user->roles),
                'isStudent' => in_array('az_student', $user->roles, true),
                'studentStatsNonce' => wp_create_nonce('azac_student_stats'),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
            ];
            wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $azac_list_cls);
            wp_localize_script('azac-attendance-list-stats-js', 'AZAC_LIST', $azac_list_cls);
        }
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page === 'azac-classes-list') {
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-stats-js', AZAC_CORE_URL . 'admin/js/attendance-list-stats.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            $user = wp_get_current_user();
            $azac_list_cls2 = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isManager' => in_array('az_manager', (array) $user->roles),
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
            ];
            wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $azac_list_cls2);
            wp_localize_script('azac-attendance-list-stats-js', 'AZAC_LIST', $azac_list_cls2);
        }
    }
    private static function get_attendance_stats($class_id)
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
    public static function render_attendance_list_page()
    {
        echo '<div class="wrap"><h1>Quản lý điểm danh</h1>';
        $user = wp_get_current_user();
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $is_admin = in_array('administrator', $user->roles, true);
        $is_student = in_array('az_student', $user->roles, true);
        echo '<div class="azac-tabs" style="margin-bottom:10px;">';
        echo '<button class="button azac-tab-btn" data-target="#azac-tab-sessions">Buổi học</button>';
        if ($is_student) {
            echo ' <button class="button azac-tab-btn" data-target="#azac-tab-stats">Thống kê điểm danh</button>';
        }
        echo '</div>';
        if ($is_teacher || $is_admin || $is_student) {
            echo '<div id="azac-tab-sessions" class="azac-tab active">';
            echo '<div class="azac-session-filters">';
            echo '<label>Nhóm ';
            echo '<select id="azac-filter-group"><option value="session">Buổi học</option><option value="class">Lớp học</option></select>';
            echo '</label> ';
            echo '<label>Sắp xếp ';
            echo '<select id="azac-filter-sort"><option value="date_desc">Ngày mới nhất</option><option value="date_asc">Ngày cũ nhất</option><option value="rate_desc">Tỉ lệ có mặt cao→thấp</option><option value="rate_asc">Tỉ lệ có mặt thấp→cao</option></select>';
            echo '</label> ';
            echo '<label>Lọc lớp ';
            echo '<select id="azac-filter-class"><option value="">Tất cả</option></select>';
            echo '</label>';
            echo '</div>';
            echo '<div id="azac-sessions-grid" class="azac-grid">';
            echo '<div class="azac-card"><div class="azac-card-title">Đang tải danh sách buổi học...</div></div>';
            echo '</div>';
            echo '</div>';
            if ($is_student) {
                echo '<div id="azac-tab-stats" class="azac-tab">';
                echo '<div id="azac-stats-grid" class="azac-grid">';
                echo '<div class="azac-card"><div class="azac-card-title">Đang tải thống kê...</div></div>';
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    public static function render_classes_list_page()
    {
        echo '<div class="wrap"><h1>Lớp học</h1>';
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $args = [
            'post_type' => 'az_class',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($is_admin || $is_teacher) {
            $args['post_status'] = ['publish', 'pending'];
        }
        $classes = get_posts($args);
        if ($is_teacher) {
            $classes = array_filter($classes, function ($c) use ($user) {
                $teacher_user = intval(get_post_meta($c->ID, 'az_teacher_user', true));
                return $teacher_user === intval($user->ID);
            });
        } elseif (in_array('az_student', $user->roles, true)) {
            $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
            $classes = array_filter($classes, function ($c) use ($student_post_id) {
                $ids = get_post_meta($c->ID, 'az_students', true);
                $ids = is_array($ids) ? array_map('absint', $ids) : [];
                return in_array($student_post_id, $ids, true);
            });
        }
        $is_admin = in_array('administrator', $user->roles, true);
        if ($is_admin) {
            echo '<div class="azac-inline-create">';
            echo '<input type="text" id="azac_new_class_title" class="regular-text" placeholder="Tên lớp học" />';
            echo '<input type="text" id="azac_new_class_teacher" class="regular-text" placeholder="Giảng viên (chuỗi)" />';
            echo '<input type="number" id="azac_new_class_sessions" class="small-text" min="0" value="0" placeholder="Tổng số buổi" />';
            echo '<button class="button button-primary" id="azac_create_class_btn">Tạo lớp</button>';
            echo '</div>';
        }
        echo '<div class="azac-grid">';
        foreach ($classes as $c) {
            $gv = get_post_meta($c->ID, 'az_giang_vien', true);
            $tsb = intval(get_post_meta($c->ID, 'az_tong_so_buoi', true));
            $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
            $status = get_post_status($c->ID);
            $is_pending = ($status === 'pending');
            $link_dashboard = admin_url('admin.php?page=azac-classes-list&class_id=' . $c->ID);
            $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
            $link_view = get_permalink($c->ID);
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
            echo '<div class="azac-card-body">';
            echo '<div>Giảng viên: ' . esc_html($gv ?: 'Chưa gán') . '</div>';
            echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
            echo '<div>Số học viên: ' . esc_html($shv) . '</div>';
            echo '</div>';
            echo '<div class="azac-card-actions azac-actions--classes">';
            if ($is_admin) {
                if ($is_pending) {
                    echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                } else {
                    echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                }
                echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
            } elseif ($is_teacher) {
                if (!$is_pending) {
                    echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a>';
                }
            } else {
                echo '<a class="button button-primary" href="' . esc_url($link_view) . '">Xem lớp</a>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div></div>';
    }
    public static function render_students_list_page()
    {
        echo '<div class="wrap"><h1>Học viên</h1>';
        $user = wp_get_current_user();
        $students = get_posts([
            'post_type' => 'az_student',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (in_array('az_teacher', $user->roles, true)) {
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => intval($user->ID),
            ]);
            $ids = [];
            foreach ($classes as $c) {
                $list = get_post_meta($c->ID, 'az_students', true);
                $list = is_array($list) ? array_map('absint', $list) : [];
                $ids = array_merge($ids, $list);
            }
            $ids = array_values(array_unique($ids));
            $students = array_filter($students, function ($s) use ($ids) {
                return in_array($s->ID, $ids, true);
            });
        }
        echo '<div class="azac-grid">';
        foreach ($students as $s) {
            $uid = intval(get_post_meta($s->ID, 'az_user_id', true));
            $name = $s->post_title;
            $email = '';
            if ($uid) {
                $u = get_userdata($uid);
                if ($u)
                    $email = $u->user_email ?: '';
            }
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($name) . '</div>';
            echo '<div class="azac-card-body">';
            echo '<div>Tài khoản: ' . esc_html($email ?: 'Chưa liên kết') . '</div>';
            echo '</div>';
            if (in_array('administrator', $user->roles, true)) {
                $link_edit = admin_url('post.php?post=' . $s->ID . '&action=edit');
                echo '<div class="azac-card-actions azac-actions--single"><a class="button button-primary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }
    public static function render_class_dashboard_page()
    {
        $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        if (!$class_id) {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1>';
            $user = wp_get_current_user();
            $is_admin = in_array('administrator', $user->roles, true);
            $is_teacher = in_array('az_teacher', $user->roles, true);
            $args = [
                'post_type' => 'az_class',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ];
            if ($is_admin || $is_teacher) {
                $args['post_status'] = ['publish', 'pending'];
            }
            $classes = get_posts($args);
            if (in_array('az_student', $user->roles, true)) {
                $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
                $classes = array_filter($classes, function ($c) use ($student_post_id) {
                    $ids = get_post_meta($c->ID, 'az_students', true);
                    $ids = is_array($ids) ? array_map('absint', $ids) : [];
                    return in_array($student_post_id, $ids, true);
                });
            } elseif (in_array('az_teacher', $user->roles, true)) {
                $classes = array_filter($classes, function ($c) use ($user) {
                    $teacher_user = intval(get_post_meta($c->ID, 'az_teacher_user', true));
                    return $teacher_user === intval($user->ID);
                });
            }
            if (!$classes) {
                echo '<p>Chưa có lớp nào để hiển thị.</p></div>';
                return;
            }
            echo '<div class="azac-grid">';
            foreach ($classes as $c) {
                $gv = get_post_meta($c->ID, 'az_giang_vien', true);
                $tsb = get_post_meta($c->ID, 'az_tong_so_buoi', true);
                $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
                $status = get_post_status($c->ID);
                $is_pending = ($status === 'pending');
                $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
                $link_view = get_permalink($c->ID);
                $link_dashboard = admin_url('admin.php?page=azac-classes-list&class_id=' . $c->ID);
                echo '<div class="azac-card">';
                echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
                echo '<div class="azac-card-body">';
                echo '<div>Giảng viên: ' . esc_html($gv) . '</div>';
                echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
                echo '<div>Sĩ số: ' . esc_html($shv) . '</div>';
                echo '</div>';
                if (in_array('administrator', $user->roles, true)) {
                    echo '<div class="azac-card-actions azac-actions--classes">';
                    if ($is_pending) {
                        echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                    } else {
                        echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                    }
                    echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                    echo '<a class="button" href="' . esc_url($link_view) . '">Vào lớp</a> ';
                    echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
                    echo '</div>';
                } elseif (in_array('az_teacher', $user->roles, true)) {
                    echo '<div class="azac-card-actions azac-actions--classes">';
                    if ($is_pending) {
                        echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                    } else {
                        echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> <a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> <a class="button" href="' . esc_url($link_view) . '">Vào lớp</a>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="azac-card-actions azac-actions--single"><a class="button button-primary" href="' . esc_url($link_view) . '">Vào lớp</a></div>';
                }
                echo '</div>';
            }
            echo '</div></div>';
            return;
        }
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'az_class') {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1><p>Lớp không hợp lệ.</p></div>';
            return;
        }
        $user = wp_get_current_user();
        $is_student = in_array('az_student', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $assigned_teacher = intval(get_post_meta($class_id, 'az_teacher_user', true));
        $can_access = current_user_can('edit_post', $class_id) || $is_student || ($is_teacher && $assigned_teacher === intval($user->ID));
        if (!$can_access) {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1><p>Không đủ quyền.</p></div>';
            return;
        }
        $students = AzAC_Core_Helper::get_class_students($class_id);
        $stats = self::get_attendance_stats($class_id);
        $teacher_name = get_post_meta($class_id, 'az_giang_vien', true);
        $teacher_user_id = intval(get_post_meta($class_id, 'az_teacher_user', true));
        if (!$teacher_name && $teacher_user_id) {
            $u = get_userdata($teacher_user_id);
            if ($u) {
                $teacher_name = $u->display_name ?: $u->user_login;
            }
        }
        $nonce = wp_create_nonce('azac_attendance_save');
        $today = current_time('Y-m-d');
        $sessions_meta = AzAC_Core_Sessions::get_class_sessions($class_id);
        $selected_date = isset($_GET['session_date']) ? sanitize_text_field($_GET['session_date']) : '';
        if (!$selected_date) {
            $selected_date = $sessions_meta ? $sessions_meta[count($sessions_meta) - 1]['date'] : $today;
        }
        $sessions_count = 1;
        foreach ($sessions_meta as $idx => $s) {
            if (($s['date'] ?? '') === $selected_date) {
                $sessions_count = $idx + 1;
                break;
            }
        }
        echo '<div class="wrap">';
        echo '<h1>Điểm danh lớp: ' . esc_html($post->post_title) . '</h1>';
        echo '<div class="azac-stats">';
        echo '<div class="azac-stat azac-info-card"><div class="az-info-header">Thông tin lớp</div><div class="az-info-grid">';
        echo '<div class="az-info-item"><span class="az-info-label">Lớp</span><span class="az-info-value">' . esc_html($post->post_title) . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Giảng viên</span><span class="az-info-value">' . esc_html($teacher_name ?: 'Chưa gán') . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Sĩ số</span><span class="az-info-value">' . esc_html(count($students)) . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Tổng buổi</span><span class="az-info-value">' . esc_html(get_post_meta($class_id, 'az_tong_so_buoi', true)) . '</span></div>';
        echo '</div></div>';
        $checkin_total = max(1, $stats['checkin_present'] + $stats['checkin_absent']);
        $mid_total = max(1, $stats['mid_present'] + $stats['mid_absent']);
        $checkin_rate = round(($stats['checkin_present'] / $checkin_total) * 100);
        $mid_rate = round(($stats['mid_present'] / $mid_total) * 100);
        echo '<div class="azac-stat"><div class="azac-chart-row"><div class="azac-chart-box"><canvas id="azacChartCheckin"></canvas></div><div class="azac-chart-box"><canvas id="azacChartMid"></canvas></div></div></div>';
        echo '</div>';
        echo '<div class="azac-session-bar">';
        echo '<select id="azac_session_select">';
        foreach ($sessions_meta as $s) {
            $label = ($s['date'] ?? '') . (($s['time'] ?? '') ? (' ' . $s['time']) : '');
            $sel = (($s['date'] ?? '') === $selected_date) ? ' selected' : '';
            echo '<option value="' . esc_attr($s['date'] ?? '') . '"' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        if (!$is_student) {
            echo '<input type="date" id="azac_session_date" value="' . esc_attr($selected_date) . '" /> ';
            echo '<input type="time" id="azac_session_time" value="" /> ';
            echo '<button class="button" id="azac_add_session_btn">Thêm buổi</button> ';
            echo '<button class="button" id="azac_update_session_btn">Cập nhật buổi</button>';
            echo ' <button class="button button-success" id="azac_start_mid_btn">Hiện mã QR Review</button>';
        }
        echo '</div>';
        echo '<h2 id="azac_session_title">Buổi học thứ: ' . esc_html($sessions_count) . ' • Ngày: ' . esc_html(date_i18n('d/m/Y', strtotime($selected_date))) . '</h2>';
        echo '<div class="azac-tabs">';
        echo '<button class="button button-primary azac-tab-btn" data-target="#azac-checkin">Điểm danh đầu giờ</button> ';
        echo '<button class="button azac-tab-btn" data-target="#azac-mid">Điểm danh giữa giờ</button>';
        echo '</div>';
        echo '<div id="azac-checkin" class="azac-tab active">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td>' . esc_html($i++) . '</td>';
            echo '<td>' . esc_html($s->post_title) . '</td>';
            echo '<td><label class="azac-switch' . ($is_student ? ' azac-disabled' : '') . '"' . ($is_student ? ' title="Đã bị vô hiệu hóa"' : '') . '><input type="checkbox" class="azac-status" data-student="' . esc_attr($s->ID) . '"' . ($is_student ? ' disabled' : '') . ' /><span class="azac-slider"></span></label></td>';
            echo '<td><input type="text" class="regular-text azac-note" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . ($is_student ? ' readonly' : '') . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            echo '<p><button class="button button-primary" id="azac-submit-checkin" data-type="check-in">Xác nhận điểm danh đầu giờ</button></p>';
        }
        echo '</div>';
        echo '<div id="azac-mid" class="azac-tab">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td>' . esc_html($i++) . '</td>';
            echo '<td>' . esc_html($s->post_title) . '</td>';
            $disable_mid = ($is_student) ? ' disabled' : '';
            $disable_cls = ($is_student) ? ' azac-disabled' : '';
            $disable_tip = ($is_student) ? ' title="Chỉ Admin/Giảng viên có thể chỉnh sửa"' : '';
            echo '<td><label class="azac-switch' . $disable_cls . '"' . $disable_tip . '><input type="checkbox" class="azac-status-mid" data-student="' . esc_attr($s->ID) . '"' . $disable_mid . ' /><span class="azac-slider"></span></label></td>';
            echo '<td><input type="text" class="regular-text azac-note-mid" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . ($is_student ? ' readonly' : '') . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            echo '<p><button class="button button-primary" id="azac-submit-mid" data-type="mid-session">Xác nhận điểm danh giữa giờ</button></p>';
        }
        echo '</div>';
        echo '<div id="azac-mid-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">';
        echo '<div style="background:#fff;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.2);padding:16px;max-width:640px;width:90%;display:flex;flex-direction:column;gap:12px">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between"><div style="font-weight:700;color:#0f6d5e">Công cụ Review buổi học</div><button type="button" class="button" id="azac_mid_close_modal">Đóng</button></div>';
        echo '<div style="font-weight:600;color:#333">Mời học viên quét mã để đánh giá buổi học</div>';
        echo '<div style="display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap">';
        echo '<div style="flex:1;min-width:240px;display:flex;align-items:center;justify-content:center"><img id="azac_mid_qr" alt="QR" style="width:280px;height:280px;border:1px solid #e2e2e2;border-radius:12px"/></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<script>window.azacData=' . wp_json_encode([
            'classId' => $class_id,
            'nonce' => $nonce,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'today' => $today,
            'sessionDate' => $selected_date,
            'sessionNonce' => wp_create_nonce('azac_session'),
            'sessions' => $sessions_meta,
            'stats' => [
                'checkin' => [
                    'present' => $stats['checkin_present'],
                    'absent' => $stats['checkin_absent'],
                ],
                'mid' => [
                    'present' => $stats['mid_present'],
                    'absent' => $stats['mid_absent'],
                ],
            ],
        ]) . ';</script>';
        echo '<script>(function(){function a(t,items){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var d=items&&items[id];if(d){el.checked=!!d.status;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');if(ne){ne.value=d.note||"";}}});}function f(t){var fd=new FormData();fd.append("action","azac_get_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){a(t,res.data.items||{});}}).catch(function(){});}function s(t){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";var items=[];document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var st=el.checked?1:0;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');var nt=ne?String(ne.value||""):"";items.push({id:id,status:st,note:nt});});var fd=new FormData();fd.append("action","azac_save_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);items.forEach(function(it,i){fd.append("items["+i+"][id]",it.id);fd.append("items["+i+"][status]",it.status);fd.append("items["+i+"][note]",it.note);});fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){alert("Đã lưu "+res.data.inserted+" bản ghi");f(t);}else{alert("Lỗi lưu");}}).catch(function(){alert("Lỗi mạng");});}var b1=document.getElementById("azac-submit-checkin");if(b1){b1.addEventListener("click",function(){s("check-in");});}var b2=document.getElementById("azac-submit-mid");if(b2){b2.addEventListener("click",function(){s("mid-session");});}var sel=document.getElementById("azac_session_select");if(sel){sel.addEventListener("change",function(){var v=sel.value||"";window.azacData.sessionDate=v;document.getElementById("azac_session_date").value=v;f("check-in");f("mid-session");});}f("check-in");f("mid-session");})();</script>';
        echo '</div>';
    }
}
