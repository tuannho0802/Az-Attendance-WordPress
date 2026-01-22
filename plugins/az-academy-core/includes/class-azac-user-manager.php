<?php

class AzAC_User_Manager
{

    public static function register()
    {
        // Clean up previous logic as we move to theme functions.php
        // AJAX Handlers
        add_action('wp_ajax_nopriv_azac_ajax_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_azac_ajax_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_nopriv_azac_ajax_register', [__CLASS__, 'ajax_register']);

        // User Meta Fields in Admin
        add_filter('manage_users_columns', [__CLASS__, 'add_user_columns']);
        add_filter('manage_users_custom_column', [__CLASS__, 'show_user_column_content'], 10, 3);
        add_action('show_user_profile', [__CLASS__, 'add_custom_user_profile_fields']);
        add_action('edit_user_profile', [__CLASS__, 'add_custom_user_profile_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_custom_user_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_custom_user_profile_fields']);
    }

    /**
     * Force load custom templates for login/register pages
     */
    public static function force_auth_templates($template)
    {
        if (is_page('login')) {
            $new_template = get_template_directory() . '/page-login.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        if (is_page('register')) {
            $new_template = get_template_directory() . '/page-register.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Change default login URL
     */
    public static function custom_login_url($login_url, $redirect, $force_reauth)
    {
        return home_url('/login');
    }

    /**
     * Change default register URL
     */
    public static function custom_register_url($url)
    {
        return home_url('/register');
    }


    /**
     * Create Login and Register pages if they don't exist
     */
    public static function init_auth_pages()
    {
        // Create Login Page
        if (!get_page_by_path('login')) {
            $login_page_id = wp_insert_post([
                'post_title' => 'Đăng nhập',
                'post_name' => 'login',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '',
            ]);

            if ($login_page_id && !is_wp_error($login_page_id)) {
                update_post_meta($login_page_id, '_wp_page_template', 'page-login.php');
            }
        }

        // Create Register Page
        if (!get_page_by_path('register')) {
            $register_page_id = wp_insert_post([
                'post_title' => 'Đăng ký',
                'post_name' => 'register',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '',
            ]);

            if ($register_page_id && !is_wp_error($register_page_id)) {
                update_post_meta($register_page_id, '_wp_page_template', 'page-register.php');
            }
        }
    }

    /**
     * Redirect default wp-login.php to custom login page
     */
    public static function redirect_wp_login()
    {
        global $pagenow;
        if ($pagenow == 'wp-login.php' && $_SERVER['REQUEST_METHOD'] == 'GET') {
            if (isset($_GET['action']) && $_GET['action'] == 'logout') {
                return;
            }
            // Avoid redirect loop if we are already handling login request or if it's an AJAX request
            if (!isset($_REQUEST['interim-login'])) {
                wp_redirect(home_url('/login'));
                exit;
            }
        }
    }

    /**
     * Handle AJAX Login
     */
    public static function ajax_login()
    {
        check_ajax_referer('azac-login-nonce', 'security');

        $info = [
            'user_login' => sanitize_text_field($_POST['user_login']),
            'user_password' => $_POST['user_pass'], // Do not sanitize password
            'remember' => isset($_POST['rememberme']) && $_POST['rememberme'] == 'forever',
        ];

        $user_signon = wp_signon($info, is_ssl());

        if (is_wp_error($user_signon)) {
            wp_send_json_error(['message' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        } else {
            wp_send_json_success([
                'message' => 'Đăng nhập thành công! Đang chuyển hướng...',
                'redirect_url' => home_url('/dashboard') // Default to dashboard or home
            ]);
        }
    }

    /**
     * Handle AJAX Register
     */
    public static function ajax_register()
    {
        check_ajax_referer('azac-register-nonce', 'security');

        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $phone = sanitize_text_field($_POST['az_phone']);
        $password = $_POST['user_pass'];
        $business = sanitize_text_field($_POST['az_business_field']);

        // Validation
        if (empty($username) || empty($email) || empty($phone) || empty($password)) {
            wp_send_json_error(['message' => 'Vui lòng điền đầy đủ các trường bắt buộc.']);
        }

        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Tên đăng nhập đã tồn tại.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email này đã được sử dụng.']);
        }

        // Create User
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        // Update Meta
        update_user_meta($user_id, 'az_phone', $phone);
        update_user_meta($user_id, 'az_business_field', $business);

        // Auto Login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success([
            'message' => 'Đăng ký thành công! Đang đăng nhập...',
            'redirect_url' => home_url('/dashboard')
        ]);
    }

    /**
     * Add Custom Columns to Admin User List
     */
    public static function add_user_columns($columns)
    {
        $columns['az_phone'] = 'Số điện thoại';
        $columns['az_business_field'] = 'Lĩnh vực';
        return $columns;
    }

    public static function show_user_column_content($value, $column_name, $user_id)
    {
        if ($column_name == 'az_phone') {
            return get_user_meta($user_id, 'az_phone', true);
        }
        if ($column_name == 'az_business_field') {
            return get_user_meta($user_id, 'az_business_field', true);
        }
        return $value;
    }

    /**
     * Add Custom Fields to User Profile
     */
    public static function add_custom_user_profile_fields($user)
    {
        ?>
        <h3>Thông tin bổ sung Az Academy</h3>
        <table class="form-table">
            <tr>
                <th><label for="az_phone">Số điện thoại</label></th>
                <td>
                    <input type="text" name="az_phone" id="az_phone"
                        value="<?php echo esc_attr(get_user_meta($user->ID, 'az_phone', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="az_business_field">Lĩnh vực kinh doanh</label></th>
                <td>
                    <input type="text" name="az_business_field" id="az_business_field"
                        value="<?php echo esc_attr(get_user_meta($user->ID, 'az_business_field', true)); ?>"
                        class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_custom_user_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        update_user_meta($user_id, 'az_phone', sanitize_text_field($_POST['az_phone']));
        update_user_meta($user_id, 'az_business_field', sanitize_text_field($_POST['az_business_field']));
    }
}
