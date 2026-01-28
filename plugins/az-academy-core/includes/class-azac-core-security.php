<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Security
{
    public static function init()
    {
        // Task 1: UI Login (Standard WP Login)
        add_action('login_head', [__CLASS__, 'hide_register_link']);
        add_action('login_form', [__CLASS__, 'add_back_to_home_link']);

        // Task 2: Backend Security
        add_filter('option_users_can_register', [__CLASS__, 'block_registration_option']);
        add_action('signup_header', [__CLASS__, 'redirect_signup']);
        add_action('init', [__CLASS__, 'block_register_page_access']); // Block /register/ page EARLY

        // Task 3: Homepage UI & Custom Hooks
        add_shortcode('azac_home_ui', [__CLASS__, 'render_home_ui']);
        add_action('azac_home_buttons', [__CLASS__, 'render_home_buttons']);

        // Task 4: Ensure Manager Capability
        add_action('init', [__CLASS__, 'ensure_manager_capabilities']);
    }

    public static function ensure_manager_capabilities()
    {
        $manager = get_role('az_manager');
        if ($manager && !$manager->has_cap('edit_users')) {
            $manager->add_cap('edit_users');
        }
    }

    // Render Home Buttons (Action Hook)
    public static function render_home_buttons()
    {
        if (is_user_logged_in()) {
            echo '<a href="' . admin_url() . '" class="az-btn az-btn-primary az-btn-lg">Vào trang quản lý</a> ';
            if (current_user_can('edit_users')) {
                echo '<a href="' . admin_url('admin.php?page=azac-classes-list') . '" class="az-btn az-btn-outline az-btn-lg" style="margin-left: 10px;">Thêm học viên</a>';
            }
        } else {
            echo '<a href="' . wp_login_url() . '" class="az-btn az-btn-primary az-btn-lg">Đăng nhập</a>';
        }
    }

    // Task 1: Hide Register Link
    public static function hide_register_link()
    {
        echo '<style>
            #login #nav a[href*="action=register"], 
            #login #nav a[href*="registration"] { display: none !important; }
            /* Backup: hide by selector if standard */
            .login-action-register #login { display: none; }
        </style>';
    }

    // Task 1: Add Back to Home Link
    public static function add_back_to_home_link()
    {
        $home_url = home_url();
        echo '<div style="margin-top: 20px; text-align: center;">
            <a href="' . esc_url($home_url) . '" style="text-decoration: none;">&larr; Quay lại Trang chủ</a>
        </div>';
    }

    // Task 2: Block Registration Option
    public static function block_registration_option($value)
    {
        return 0; // Always false
    }

    // Task 2: Redirect Signup Page (Multisite/Standard)
    public static function redirect_signup()
    {
        wp_redirect(wp_login_url());
        exit;
    }

    // Task 2: Block Access to Register Page (Strict & Early)
    public static function block_register_page_access()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if URL contains /register/ or action=register
        if (strpos($request_uri, '/register') !== false || (isset($_GET['action']) && $_GET['action'] === 'register')) {

            // Debug: Force stop to verify execution
            // wp_die('Da vao ham block: ' . esc_html($request_uri));

            error_log('AzAC Security: Blocking attempt to access ' . $request_uri);

            // Strict Check
            if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('az_manager'))) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    // Task 3: Homepage UI Shortcode
    public static function render_home_ui()
    {
        $output = '<div class="azac-home-actions" style="text-align: center; margin: 20px 0;">';

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            // Button: Vào trang quản lý
            $dashboard_url = admin_url(); // Or specific custom dashboard
            $output .= '<a href="' . esc_url($dashboard_url) . '" class="button button-primary button-large" style="margin-right: 10px;">Vào trang quản lý</a>';

            // Button: Thêm học viên (Admin/Manager only)
            if (current_user_can('edit_users') || in_array('az_manager', (array) $user->roles)) {
                // Assuming link to Class List or a dedicated Add Student page. 
                // Since there isn't a dedicated "Add Student" page mentioned, we'll link to Class List where they can manage students.
                $add_student_url = admin_url('admin.php?page=azac-classes-list');
                $output .= '<a href="' . esc_url($add_student_url) . '" class="button button-secondary button-large">Thêm học viên mới</a>';
            }
        } else {
            // Button: Đăng nhập
            $login_url = wp_login_url();
            $output .= '<a href="' . esc_url($login_url) . '" class="button button-primary button-large">Đăng nhập</a>';
        }

        $output .= '</div>';
        return $output;
    }
}
