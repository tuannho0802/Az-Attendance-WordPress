<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'register_cpt_class']);
        add_action('init', [$this, 'register_cpt_student']);
        add_action('init', [$this, 'ensure_sessions_table'], 1);
        add_action('admin_init', [$this, 'redirect_cpt_list_to_custom']);
        add_action('wp_ajax_azac_student_stats', [$this, 'ajax_student_stats']);
        add_action('init', [$this, 'ensure_teacher_caps'], 2);
        add_action('add_meta_boxes', [$this, 'add_class_meta_boxes']);
        add_action('add_meta_boxes', [$this, 'add_class_students_meta_box']);
        add_action('save_post_az_class', [$this, 'save_class_meta'], 10, 2);
        add_action('save_post_az_class', [$this, 'save_class_students_meta'], 10, 2);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_menu', [$this, 'remove_default_menus'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('user_register', [$this, 'on_user_register']);
        add_action('set_user_role', [$this, 'on_set_user_role'], 10, 3);
        add_action('wp_ajax_azac_save_attendance', [$this, 'ajax_save_attendance']);
        add_action('wp_ajax_azac_add_student', [$this, 'ajax_add_student']);
        add_action('wp_ajax_azac_create_class', [$this, 'ajax_create_class']);
        add_action('wp_ajax_azac_get_attendance', [$this, 'ajax_get_attendance']);
        add_action('admin_bar_menu', [$this, 'cleanup_admin_bar'], 999);
        add_filter('map_meta_cap', [$this, 'map_meta_cap_for_class'], 10, 4);
        add_action('wp_ajax_azac_add_session', [$this, 'ajax_add_session']);
        add_action('wp_ajax_azac_update_session', [$this, 'ajax_update_session']);
        add_action('wp_ajax_azac_list_sessions', [$this, 'ajax_list_sessions']);
        add_action('wp_ajax_azac_update_class_status', [$this, 'ajax_update_class_status']);
        add_action('wp_ajax_azac_delete_class', [$this, 'ajax_delete_class']);
        add_filter('manage_az_class_posts_columns', [$this, 'columns_az_class']);
        add_action('manage_az_class_posts_custom_column', [$this, 'column_content_az_class'], 10, 2);
        add_filter('manage_az_student_posts_columns', [$this, 'columns_az_student']);
        add_action('manage_az_student_posts_custom_column', [$this, 'column_content_az_student'], 10, 2);
        add_filter('wp_insert_post_data', [$this, 'prevent_teacher_pending'], 10, 2);
    }
    public function ensure_sessions_table()
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
    public function register_cpt_class()
    {
        $labels = [
            'name' => 'Lớp học',
            'singular_name' => 'Lớp học',
            'menu_name' => 'Lớp học',
            'name_admin_bar' => 'Lớp học',
            'add_new' => 'Thêm lớp',
            'add_new_item' => 'Thêm lớp học mới',
            'new_item' => 'Lớp học mới',
            'edit_item' => 'Chỉnh sửa lớp học',
            'view_item' => 'Xem lớp học',
            'all_items' => 'Tất cả lớp học',
            'search_items' => 'Tìm lớp học',
            'not_found' => 'Không tìm thấy',
            'not_found_in_trash' => 'Không có trong thùng rác',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'capability_type' => 'post',
            'rewrite' => ['slug' => 'lop-hoc'],
        ];

        register_post_type('az_class', $args);

        register_post_meta('az_class', 'az_giang_vien', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('az_class', 'az_tong_so_buoi', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('az_class', 'az_so_hoc_vien', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('az_class', 'az_students', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function ($value) {
                $ids = is_array($value) ? array_map('absint', $value) : [];
                return array_values(array_filter($ids));
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('az_class', 'az_teacher_user', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_sessions', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => function ($value) {
                $items = is_array($value) ? $value : [];
                $out = [];
                foreach ($items as $it) {
                    $d = isset($it['date']) ? sanitize_text_field($it['date']) : '';
                    $t = isset($it['time']) ? sanitize_text_field($it['time']) : '';
                    if ($d)
                        $out[] = ['date' => $d, 'time' => $t];
                }
                usort($out, function ($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });
                return $out;
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function register_cpt_student()
    {
        $labels = [
            'name' => 'Học viên',
            'singular_name' => 'Học viên',
            'menu_name' => 'Học viên',
            'add_new_item' => 'Thêm học viên',
            'edit_item' => 'Chỉnh sửa học viên',
        ];
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-id',
        ];
        register_post_meta('az_student', 'az_user_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_type('az_student', $args);
    }

    public function add_class_meta_boxes()
    {
        add_meta_box(
            'azac_class_meta',
            'Thông tin lớp học',
            [$this, 'render_class_meta_box'],
            'az_class',
            'side',
            'default'
        );
    }

    public function render_class_meta_box($post)
    {
        wp_nonce_field('azac_class_meta_box', 'azac_class_meta_nonce');

        $giang_vien = get_post_meta($post->ID, 'az_giang_vien', true);
        $tong_so_buoi = get_post_meta($post->ID, 'az_tong_so_buoi', true);
        $so_hoc_vien = get_post_meta($post->ID, 'az_so_hoc_vien', true);
        $teacher_user = get_post_meta($post->ID, 'az_teacher_user', true);
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $disabled = $is_admin ? '' : ' disabled';

        echo '<p><label for="az_giang_vien">Giảng viên</label><br />';
        echo '<input type="text" id="az_giang_vien" name="az_giang_vien" class="regular-text" value="' . esc_attr($giang_vien) . '"' . $disabled . ' /></p>';

        echo '<p><label for="az_tong_so_buoi">Tổng số buổi</label><br />';
        echo '<input type="number" id="az_tong_so_buoi" name="az_tong_so_buoi" min="0" value="' . esc_attr($tong_so_buoi) . '"' . $disabled . ' /></p>';

        echo '<p><label for="az_so_hoc_vien">Số học viên</label><br />';
        echo '<input type="number" id="az_so_hoc_vien" name="az_so_hoc_vien" min="0" value="' . esc_attr($so_hoc_vien) . '"' . $disabled . ' /></p>';

        $teachers = get_users(['role' => 'az_teacher']);
        echo '<p><label for="az_teacher_user">Giảng viên (User)</label><br />';
        echo '<select id="az_teacher_user" name="az_teacher_user"' . $disabled . '>';
        echo '<option value="">-- Chọn giảng viên --</option>';
        foreach ($teachers as $t) {
            $selected = selected(intval($teacher_user), intval($t->ID), false);
            echo '<option value="' . esc_attr($t->ID) . '" ' . $selected . '>' . esc_html($t->display_name) . '</option>';
        }
        echo '</select></p>';
    }

    public function add_class_students_meta_box()
    {
        add_meta_box(
            'azac_class_students',
            'Danh sách học viên',
            [$this, 'render_class_students_meta_box'],
            'az_class',
            'normal',
            'default'
        );
    }

    public function render_class_students_meta_box($post)
    {
        wp_nonce_field('azac_class_students_meta_box', 'azac_class_students_meta_nonce');
        $selected = get_post_meta($post->ID, 'az_students', true);
        $selected = is_array($selected) ? array_map('absint', $selected) : [];
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);

        $students = get_posts([
            'post_type' => 'az_student',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if ($is_admin) {
            echo '<div style="margin-bottom:12px;"><input type="text" id="azac_new_student_name" class="regular-text" placeholder="Họ và Tên học viên" /> ';
            echo '<input type="email" id="azac_new_student_email" class="regular-text" placeholder="Email (tùy chọn)" /> ';
            echo '<button type="button" class="button" id="azac_add_student_btn" data-class="' . esc_attr($post->ID) . '">Thêm học viên</button></div>';
            echo '<div id="azac_students_grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
            foreach ($students as $s) {
                $checked = in_array($s->ID, $selected, true) ? 'checked' : '';
                echo '<label><input type="checkbox" name="az_students[]" value="' . esc_attr($s->ID) . '" ' . $checked . ' /> ' . esc_html($s->post_title) . '</label>';
            }
            echo '</div>';
            $nonce = wp_create_nonce('azac_add_student');
            echo '<script>window.azacClassEditData=' . wp_json_encode([
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
            ]) . ';</script>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
            foreach ($students as $s) {
                $checked = in_array($s->ID, $selected, true) ? '✓ ' : '';
                echo '<div>' . esc_html($checked . $s->post_title) . '</div>';
            }
            echo '</div>';
        }
    }

    public function save_class_meta($post_id, $post)
    {
        if (!isset($_POST['azac_class_meta_nonce']) || !wp_verify_nonce($_POST['azac_class_meta_nonce'], 'azac_class_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'az_class') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            return;
        }

        if (isset($_POST['az_giang_vien'])) {
            update_post_meta($post_id, 'az_giang_vien', sanitize_text_field($_POST['az_giang_vien']));
        }

        if (isset($_POST['az_tong_so_buoi'])) {
            update_post_meta($post_id, 'az_tong_so_buoi', absint($_POST['az_tong_so_buoi']));
        }

        if (isset($_POST['az_so_hoc_vien'])) {
            update_post_meta($post_id, 'az_so_hoc_vien', absint($_POST['az_so_hoc_vien']));
        }
        if (isset($_POST['az_teacher_user'])) {
            update_post_meta($post_id, 'az_teacher_user', absint($_POST['az_teacher_user']));
        }
    }

    public function save_class_students_meta($post_id, $post)
    {
        if (!isset($_POST['azac_class_students_meta_nonce']) || !wp_verify_nonce($_POST['azac_class_students_meta_nonce'], 'azac_class_students_meta_box')) {
            return;
        }
        if ($post->post_type !== 'az_class') {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            return;
        }
        $ids = isset($_POST['az_students']) && is_array($_POST['az_students']) ? array_map('absint', $_POST['az_students']) : [];
        update_post_meta($post_id, 'az_students', array_values(array_filter($ids)));
        update_post_meta($post_id, 'az_so_hoc_vien', count($ids));
    }

    public function register_admin_pages()
    {
        add_menu_page(
            'Quản lý điểm danh',
            'Quản lý điểm danh',
            'read',
            'azac-attendance',
            [$this, 'render_attendance_list_page'],
            'dashicons-yes',
            3
        );

        add_submenu_page(
            'azac-attendance',
            'Lớp học',
            'Lớp học',
            'edit_posts',
            'azac-classes-list',
            [$this, 'render_classes_list_page']
        );
        add_submenu_page(
            'azac-attendance',
            'Học viên',
            'Học viên',
            'edit_posts',
            'azac-students-list',
            [$this, 'render_students_list_page']
        );
        add_submenu_page(
            'azac-attendance',
            'Chi tiết lớp',
            'Chi tiết lớp',
            'read',
            'azac-class-dashboard',
            [$this, 'render_class_dashboard_page']
        );
    }

    public function remove_default_menus()
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
    public function redirect_cpt_list_to_custom()
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
    public function cleanup_admin_bar($wp_admin_bar)
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
    public function ensure_teacher_caps()
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

    public function enqueue_admin_assets($hook)
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
            wp_enqueue_script('azac-attendance-list-js', AZAC_CORE_URL . 'admin/js/attendance-list.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            $user = wp_get_current_user();
            wp_localize_script('azac-attendance-list-js', 'AZAC_LIST', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'studentStatsNonce' => wp_create_nonce('azac_student_stats'),
            ]);
        }
        if ($hook === 'azac-attendance_page_azac-class-dashboard') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            wp_enqueue_script('azac-attendance-js', AZAC_CORE_URL . 'admin/js/attendance.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
            $list_view = !(isset($_GET['class_id']) && absint($_GET['class_id']));
            if ($list_view) {
                wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
                wp_enqueue_script('azac-attendance-list-js', AZAC_CORE_URL . 'admin/js/attendance-list.js', ['jquery'], AZAC_CORE_VERSION, true);
                $user = wp_get_current_user();
                wp_localize_script('azac-attendance-list-js', 'AZAC_LIST', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'isTeacher' => in_array('az_teacher', $user->roles, true),
                    'isAdmin' => in_array('administrator', $user->roles, true),
                    'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                    'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
                ]);
            }
        }
        if ($hook === 'azac-attendance_page_azac-classes-list') {
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-list-js', AZAC_CORE_URL . 'admin/js/attendance-list.js', ['jquery'], AZAC_CORE_VERSION, true);
            $user = wp_get_current_user();
            wp_localize_script('azac-attendance-list-js', 'AZAC_LIST', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
            ]);
        }
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page === 'azac-class-dashboard') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            if (!wp_script_is('chartjs', 'enqueued')) {
                wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            }
            if (!wp_script_is('azac-attendance-js', 'enqueued')) {
                wp_enqueue_script('azac-attendance-js', AZAC_CORE_URL . 'admin/js/attendance.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
            }
        } elseif ($page === 'azac-classes-list') {
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-list-js', AZAC_CORE_URL . 'admin/js/attendance-list.js', ['jquery'], AZAC_CORE_VERSION, true);
            $user = wp_get_current_user();
            wp_localize_script('azac-attendance-list-js', 'AZAC_LIST', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => in_array('administrator', $user->roles, true),
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
            ]);
        }
    }

    private function get_class_students($class_id)
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
    private function get_current_student_post_id()
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

    private function get_attendance_stats($class_id)
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

    private function get_class_sessions($class_id)
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

    private function upsert_class_session($class_id, $date, $time)
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
        return $this->get_class_sessions($class_id);
    }

    private function update_class_session($class_id, $old_date, $new_date, $new_time)
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
        return $this->get_class_sessions($class_id);
    }

    public function render_attendance_list_page()
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
    public function render_classes_list_page()
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
            $student_post_id = $this->get_current_student_post_id();
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
            $link_dashboard = admin_url('admin.php?page=azac-class-dashboard&class_id=' . $c->ID);
            $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
            $link_view = get_permalink($c->ID);
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
            echo '<div class="azac-card-body">';
            echo '<div>Giảng viên: ' . esc_html($gv ?: 'Chưa gán') . '</div>';
            echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
            echo '<div>Số học viên: ' . esc_html($shv) . '</div>';
            echo '</div>';
            echo '<div class="azac-card-actions">';
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
                if ($is_pending) {
                    echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                } else {
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
    public function render_students_list_page()
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
                echo '<div class="azac-card-actions"><a class="button button-primary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }
    public function columns_az_class($cols)
    {
        $new = [];
        if (isset($cols['cb']))
            $new['cb'] = $cols['cb'];
        $new['title'] = 'Tên lớp';
        $new['az_teacher'] = 'Giảng viên';
        $new['az_sessions'] = 'Tổng buổi';
        $new['az_students'] = 'Sĩ số';
        $new['date'] = isset($cols['date']) ? $cols['date'] : 'Ngày';
        return $new;
    }
    public function column_content_az_class($column, $post_id)
    {
        if ($column === 'az_teacher') {
            $teacher_name = get_post_meta($post_id, 'az_giang_vien', true);
            if (!$teacher_name) {
                $uid = intval(get_post_meta($post_id, 'az_teacher_user', true));
                if ($uid) {
                    $u = get_userdata($uid);
                    if ($u)
                        $teacher_name = $u->display_name ?: $u->user_login;
                }
            }
            echo '<span class="azac-badge">' . esc_html($teacher_name ?: 'Chưa gán') . '</span>';
        } elseif ($column === 'az_sessions') {
            $tsb = intval(get_post_meta($post_id, 'az_tong_so_buoi', true));
            echo '<span class="azac-badge">' . esc_html($tsb) . '</span>';
        } elseif ($column === 'az_students') {
            $shv = intval(get_post_meta($post_id, 'az_so_hoc_vien', true));
            echo '<span class="azac-badge">' . esc_html($shv) . '</span>';
        }
    }
    public function columns_az_student($cols)
    {
        $new = [];
        if (isset($cols['cb']))
            $new['cb'] = $cols['cb'];
        $new['title'] = 'Họ và Tên';
        $new['az_user'] = 'Tài khoản';
        $new['date'] = isset($cols['date']) ? $cols['date'] : 'Ngày';
        return $new;
    }
    public function column_content_az_student($column, $post_id)
    {
        if ($column === 'az_user') {
            $uid = intval(get_post_meta($post_id, 'az_user_id', true));
            if ($uid) {
                $u = get_userdata($uid);
                if ($u) {
                    $label = ($u->display_name ?: $u->user_login);
                    $email = $u->user_email ?: '';
                    echo '<span class="azac-badge">' . esc_html($label) . '</span> ' . esc_html($email);
                    return;
                }
            }
            echo '<span class="azac-badge">Chưa liên kết</span>';
        }
    }
    public function render_class_dashboard_page()
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
                $student_post_id = $this->get_current_student_post_id();
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
                $link_dashboard = admin_url('admin.php?page=azac-class-dashboard&class_id=' . $c->ID);
                echo '<div class="azac-card">';
                echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
                echo '<div class="azac-card-body">';
                echo '<div>Giảng viên: ' . esc_html($gv) . '</div>';
                echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
                echo '<div>Sĩ số: ' . esc_html($shv) . '</div>';
                echo '</div>';
                if (in_array('administrator', $user->roles, true)) {
                    echo '<div class="azac-card-actions">';
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
                    echo '<div class="azac-card-actions">';
                    if ($is_pending) {
                        echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                    } else {
                        echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> <a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> <a class="button" href="' . esc_url($link_view) . '">Vào lớp</a>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="azac-card-actions"><a class="button button-primary" href="' . esc_url($link_view) . '">Vào lớp</a></div>';
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
        $students = $this->get_class_students($class_id);
        $stats = $this->get_attendance_stats($class_id);
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
        $sessions_meta = $this->get_class_sessions($class_id);
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
            echo '<td><label class="azac-switch' . ($is_student ? ' azac-disabled' : '') . '"' . ($is_student ? ' title="Đã bị vô hiệu hóa"' : '') . '><input type="checkbox" class="azac-status-mid" data-student="' . esc_attr($s->ID) . '"' . ($is_student ? ' disabled' : '') . ' /><span class="azac-slider"></span></label></td>';
            echo '<td><input type="text" class="regular-text azac-note-mid" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . ($is_student ? ' readonly' : '') . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            echo '<p><button class="button button-primary" id="azac-submit-mid" data-type="mid-session">Xác nhận điểm danh giữa giờ</button></p>';
        }
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
        echo '<script>(function(){function a(t,items){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var d=items&&items[id];if(d){el.checked=!!d.status;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');if(ne){ne.value=d.note||"";}}});}function f(t){var fd=new FormData();fd.append("action","azac_get_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){a(t,res.data.items||{});}}).catch(function(){});}function s(t){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";var items=[];document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var st=el.checked?1:0;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');var nt=ne?String(ne.value||""):"";items.push({id:id,status:st,note:nt});});var fd=new FormData();fd.append("action","azac_save_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);items.forEach(function(it,i){fd.append("items["+i+"][id]",it.id);fd.append("items["+i+"][status]",it.status);fd.append("items["+i+"][note]",it.note);});fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){alert("Đã lưu "+res.data.inserted+" bản ghi");f(t);}else{alert("Lỗi lưu");}}).catch(function(){alert("Lỗi mạng");});}var b1=document.getElementById("azac-submit-checkin");if(b1){b1.addEventListener("click",function(){s("check-in");});}var b2=document.getElementById("azac-submit-mid");if(b2){b2.addEventListener("click",function(){s("mid-session");});}f("check-in");f("mid-session");})();</script>';

        echo '</div>';
    }

    public function map_meta_cap_for_class($caps, $cap, $user_id, $args)
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
    public function prevent_teacher_pending($data, $postarr)
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
    public function ajax_update_class_status()
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
    public function ajax_delete_class()
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

    public function ajax_save_attendance()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_attendance_save')) {
            wp_send_json_error(['message' => 'Nonce'], 403);
        }
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $session_date = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        if (!$class_id || !in_array($type, ['check-in', 'mid-session'], true) || !$session_date) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        $user = wp_get_current_user();
        $is_admin_or_teacher = in_array('administrator', $user->roles, true) || in_array('az_teacher', $user->roles, true);
        if (!$is_admin_or_teacher) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';
        $inserted = 0;
        foreach ($items as $it) {
            $student_id = isset($it['id']) ? absint($it['id']) : 0;
            $status = isset($it['status']) ? (intval($it['status']) ? 1 : 0) : 0;
            $note = isset($it['note']) ? sanitize_text_field($it['note']) : '';
            if (!$student_id)
                continue;
            $res = $wpdb->replace(
                $table,
                [
                    'class_id' => $class_id,
                    'student_id' => $student_id,
                    'session_date' => $session_date,
                    'attendance_type' => $type,
                    'status' => $status,
                    'note' => $note,
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s']
            );
            if ($res !== false)
                $inserted++;
        }
        wp_send_json_success(['inserted' => $inserted]);
    }

    public function ajax_get_attendance()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_attendance_save')) {
            wp_send_json_error(['message' => 'Nonce'], 403);
        }
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $session_date = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';
        if (!$class_id || !in_array($type, ['check-in', 'mid-session'], true) || !$session_date) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT student_id, status, note FROM {$table} WHERE class_id=%d AND session_date=%s AND attendance_type=%s", $class_id, $session_date, $type), ARRAY_A);
        $map = [];
        foreach ($rows as $r) {
            $map[intval($r['student_id'])] = ['status' => intval($r['status']), 'note' => $r['note']];
        }
        wp_send_json_success(['items' => $map]);
    }
    public function ajax_student_stats()
    {
        check_ajax_referer('azac_student_stats', 'nonce');
        $user = wp_get_current_user();
        if (!$user || !in_array('az_student', $user->roles, true)) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }
        $student_post_id = $this->get_current_student_post_id();
        if (!$student_post_id) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT class_id, attendance_type, status, COUNT(*) as c FROM {$table} WHERE student_id=%d GROUP BY class_id, attendance_type, status", $student_post_id), ARRAY_A);
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
            $att_table = $wpdb->prefix . 'az_attendance';
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
                    'link' => admin_url('admin.php?page=azac-class-dashboard&class_id=' . $cid . '&session_date=' . urlencode($d)),
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
    public function ajax_add_session()
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
        $sessions = $this->upsert_class_session($class_id, $date, $time);
        wp_send_json_success(['sessions' => $sessions, 'selected' => $date]);
    }
    public function ajax_update_session()
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
        $sessions = $this->update_class_session($class_id, $date, $new_date, $new_time);
        wp_send_json_success(['sessions' => $sessions, 'selected' => $new_date]);
    }
    public function ajax_list_sessions()
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
            $student_post_id = $this->get_current_student_post_id();
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
            $sessions = $this->get_class_sessions($c->ID);
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
    public function ajax_add_student()
    {
        check_ajax_referer('azac_add_student', 'nonce');
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!$class_id || !$name) {
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
        $student_post_id = 0;
        if ($email) {
            $existing_user = get_user_by('email', $email);
            if (!$existing_user) {
                $username_base = sanitize_user(current(explode('@', $email)));
                $username = $username_base;
                $i = 1;
                while (username_exists($username)) {
                    $username = $username_base . $i;
                    $i++;
                }
                $password = wp_generate_password(12, false);
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $u = new WP_User($user_id);
                    $u->set_role('az_student');
                    $student_post_id = wp_insert_post([
                        'post_type' => 'az_student',
                        'post_title' => $name,
                        'post_status' => 'publish',
                    ]);
                    if (!is_wp_error($student_post_id)) {
                        update_post_meta($student_post_id, 'az_user_id', absint($user_id));
                    }
                }
            } else {
                $link_post = get_posts([
                    'post_type' => 'az_student',
                    'numberposts' => 1,
                    'meta_key' => 'az_user_id',
                    'meta_value' => $existing_user->ID,
                ]);
                if ($link_post) {
                    $student_post_id = $link_post[0]->ID;
                    if ($name && $link_post[0]->post_title !== $name) {
                        wp_update_post(['ID' => $student_post_id, 'post_title' => $name]);
                    }
                } else {
                    $student_post_id = wp_insert_post([
                        'post_type' => 'az_student',
                        'post_title' => $name,
                        'post_status' => 'publish',
                    ]);
                    if (!is_wp_error($student_post_id)) {
                        update_post_meta($student_post_id, 'az_user_id', absint($existing_user->ID));
                    }
                }
            }
        } else {
            $student_post_id = wp_insert_post([
                'post_type' => 'az_student',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);
        }
        if (is_wp_error($student_post_id) || !$student_post_id) {
            wp_send_json_error(['message' => 'Create failed'], 500);
        }
        $ids = get_post_meta($class_id, 'az_students', true);
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
        if (!in_array($student_post_id, $ids, true)) {
            $ids[] = $student_post_id;
            update_post_meta($class_id, 'az_students', array_values($ids));
            update_post_meta($class_id, 'az_so_hoc_vien', count($ids));
        }
        wp_send_json_success(['id' => $student_post_id, 'title' => get_the_title($student_post_id)]);
    }

    public function ajax_create_class()
    {
        check_ajax_referer('azac_create_class', 'nonce');
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
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
        update_post_meta($post_id, 'az_giang_vien', $teacher);
        update_post_meta($post_id, 'az_tong_so_buoi', $sessions);
        update_post_meta($post_id, 'az_so_hoc_vien', 0);
        $user = wp_get_current_user();
        if (in_array('az_teacher', $user->roles, true)) {
            update_post_meta($post_id, 'az_teacher_user', absint($user->ID));
            if (!$teacher) {
                update_post_meta($post_id, 'az_giang_vien', $user->display_name ?: $user->user_login);
            }
        }
        wp_send_json_success([
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'link' => admin_url('admin.php?page=azac-class-dashboard&class_id=' . $post_id),
        ]);
    }

    public function on_user_register($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (!$user)
            return;
        if (!in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            $user->set_role('az_student');
        }
        $this->ensure_student_post_for_user($user_id);
    }

    public function on_set_user_role($user_id, $role, $old_roles)
    {
        if ($role === 'az_student') {
            $this->ensure_student_post_for_user($user_id);
        }
    }

    private function ensure_student_post_for_user($user_id)
    {
        $existing = get_posts([
            'post_type' => 'az_student',
            'numberposts' => 1,
            'meta_key' => 'az_user_id',
            'meta_value' => $user_id,
            'post_status' => 'any',
        ]);
        if ($existing) {
            return;
        }
        $user = get_user_by('id', $user_id);
        if (!$user)
            return;
        $post_id = wp_insert_post([
            'post_type' => 'az_student',
            'post_title' => $user->display_name ?: $user->user_login,
            'post_status' => 'publish',
        ]);
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'az_user_id', absint($user_id));
        }
    }
}
