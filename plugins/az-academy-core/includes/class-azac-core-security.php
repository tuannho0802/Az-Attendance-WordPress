<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Security
{
    const SUPER_ADMIN_ID = 1;

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

        // Task 5: Lock Admin Privileges
        add_filter('editable_roles', [__CLASS__, 'restrict_editable_roles']);
        add_action('profile_update', [__CLASS__, 'prevent_admin_escalation'], 10, 2);
        add_action('user_register', [__CLASS__, 'prevent_admin_creation'], 10, 1);
        add_action('pre_get_users', [__CLASS__, 'hide_super_admin']);

        // Task 6: Super Admin Self-Protection
        add_filter('map_meta_cap', [__CLASS__, 'protect_super_admin_capabilities'], 10, 4);
        add_action('user_profile_update_errors', [__CLASS__, 'prevent_super_admin_role_change_error'], 10, 3);
        // add_action('admin_head', [__CLASS__, 'hide_role_selector_for_super_admin']);
        add_action('admin_footer', [__CLASS__, 'remove_admin_role_option_js']);
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
            // Removed "Thêm học viên" button as requested
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

            // Mở quyền cho Nội bộ: Admin hoặc Manager
            if (is_user_logged_in() && (current_user_can('administrator') || current_user_can('az_manager'))) {
                return; // Cho phép truy cập
            }

            // Chặn các đối tượng khác (Khách, Học viên...) -> Về trang chủ
            wp_redirect(home_url());
            exit;
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

            // Button: Thêm học viên (Removed per request)
        } else {
            // Button: Đăng nhập
            $login_url = wp_login_url();
            $output .= '<a href="' . esc_url($login_url) . '" class="button button-primary button-large">Đăng nhập</a>';
        }

        $output .= '</div>';
        return $output;
    }

    // Task 5: Lock Admin Privileges Logic
    public static function restrict_editable_roles($roles)
    {
        if (get_current_user_id() !== self::SUPER_ADMIN_ID) {
            if (isset($roles['administrator'])) {
                unset($roles['administrator']);
            }
        }
        return $roles;
    }

    public static function prevent_admin_escalation($user_id, $old_user_data = null)
    {
        // If target user is NOT Super Admin
        if ($user_id !== self::SUPER_ADMIN_ID) {
            // Check if they are being assigned Administrator
            $user = get_userdata($user_id);
            if (in_array('administrator', (array) $user->roles)) {
                // Downgrade to Manager
                $user->set_role('az_manager');
            }
        }
    }

    public static function prevent_admin_creation($user_id)
    {
        self::prevent_admin_escalation($user_id);
    }

    public static function hide_super_admin($query)
    {
        if (is_admin() && get_current_user_id() !== self::SUPER_ADMIN_ID) {
            $query->set('exclude', [self::SUPER_ADMIN_ID]);
        }
    }

    // Task 6: Protect Super Admin Role & Capabilities
    public static function protect_super_admin_capabilities($caps, $cap, $user_id, $args)
    {
        // $user_id ở đây là người đang thực hiện hành động (Bạn)
        // $args[0] thường là ID của người bị tác động (Target User)

        $target_user_id = isset($args[0]) ? (int) $args[0] : 0;

        // CHỈ chặn nếu ai đó (kể cả chính mình) cố gắng đổi quyền hoặc xóa Super Admin (ID 1)
        if ($target_user_id === self::SUPER_ADMIN_ID) {
            if (in_array($cap, ['promote_user', 'remove_user', 'delete_user'])) {
                $caps[] = 'do_not_allow';
            }
        }

        return $caps;
    }

    public static function prevent_super_admin_role_change_error(&$errors, $update, $user)
    {
        if ($user->ID === self::SUPER_ADMIN_ID) {
            if (isset($_POST['role']) && $_POST['role'] !== 'administrator') {
                $errors->add('super_admin_protected', '<strong>ERROR</strong>: Không thể thay đổi quyền hạn của tài khoản Super Admin tối cao.');
            }
        }
    }

    public static function remove_admin_role_option_js()
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['user-edit', 'user-new', 'profile', 'users_page_azac-add-student'])) {
            return;
        }

        global $user_id;
        $target_id = $user_id ? intval($user_id) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);
        $current_user_id = get_current_user_id();
        $is_self_editing_admin = ($current_user_id === self::SUPER_ADMIN_ID && $target_id === self::SUPER_ADMIN_ID);

        // Dùng CSS Force để hiện hàng Role ngay lập tức trước khi JS chạy
        echo '<style>
        .user-role-wrap, tr.user-role-wrap, #role { 
            display: table-row !important; 
            visibility: visible !important; 
            opacity: 1 !important; 
        }
    </style>';

        ?>
            <script type="text/javascript">
                (function ($) {
                    $(document).ready(function () {
                        // Đảm bảo hàng Role hiện ra
                        $('.user-role-wrap, tr.user-role-wrap').attr('style', 'display: table-row !important');

                        // Xóa option Administrator nếu không phải đang sửa chính mình
                        <?php if (!$is_self_editing_admin): ?>
                            $('#role option[value="administrator"]').remove();
                            $('select[name="role"] option[value="administrator"]').remove();
                        <?php endif; ?>
                    });
                })(jQuery);
            </script>
            <?php
    }
}
