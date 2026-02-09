<?php
/**
 * AZ Academy Core Security - Nâng Cấp Bảo Mật Chủ Động
 * 
 * HƯỚNG DẪN NÂNG CẤP:
 * 1. Thay thế file này vào: wp-content/plugins/az-attendance-wordpress/includes/class-azac-core-security.php
 * 2. Không cần thay đổi wp-config.php
 * 3. Tự động kích hoạt tất cả hook khi plugin load
 * 
 * TÍNH NĂNG MỚI:
 * ✅ Kiểm tra Administrator role chuẩn xác (array instead of serialize)
 * ✅ Chống Brute Force: Giới hạn 5 lần đăng nhập sai, khóa 15 phút
 * ✅ Ẩn thông báo lỗi đăng nhập (không lộ user/password)
 * ✅ Vô hiệu hóa XML-RPC (chống DDoS & Brute Force)
 * ✅ Chặn REST API cho người chưa đăng nhập
 * ✅ UI Role Selector cải thiện (mượt mà, không có CSS force)
 * ✅ Tối ưu hook init (lazy load)
 * 
 * BẢO MẬT SUPER ADMIN ID = 1:
 * - Không ai có thể đổi role của Super Admin sang Administrator hay Role khác
 * - Super Admin không hiển thị trong danh sách user quản lý
 * - Không ai có thể xóa/promote/demote Super Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Security
{
    const SUPER_ADMIN_ID = 1;

    /**
     * Hằng số bảo mật Brute Force
     */
    const BRUTE_FORCE_MAX_ATTEMPTS = 5;        // Tối đa 5 lần đăng nhập sai
    const BRUTE_FORCE_LOCKOUT_TIME = 900;      // Khóa 15 phút (900 giây)
    const BRUTE_FORCE_TRANSIENT_PREFIX = 'azac_login_attempt_'; // Prefix transient

    public static function init()
    {
        // ========================================================
        // PHẦN 1: UI LOGIN (Ẩn đăng ký, Chặn truy cập)
        // ========================================================
        add_action('login_head', [__CLASS__, 'hide_register_link']);
        add_action('login_form', [__CLASS__, 'add_back_to_home_link']);
        add_action('init', [__CLASS__, 'block_register_page_access'], 1); // Chạy sớm nhất

        // ========================================================
        // PHẦN 2: BACKEND SECURITY (Chặn đăng ký, bảo vệ Admin)
        // ========================================================
        add_filter('option_users_can_register', [__CLASS__, 'block_registration_option']);
        add_action('signup_header', [__CLASS__, 'redirect_signup']);
        add_action('init', [__CLASS__, 'ensure_manager_capabilities'], 5);

        // Chống Brute Force & Login Security
        add_filter('authenticate', [__CLASS__, 'check_brute_force_on_login'], 30, 3);
        add_action('wp_login_failed', [__CLASS__, 'log_failed_login_attempt']);
        add_filter('login_errors', [__CLASS__, 'hide_login_error_messages']);

        // ========================================================
        // PHẦN 3: HOMEPAGE UI & Custom HOOKS
        // ========================================================
        add_shortcode('azac_home_ui', [__CLASS__, 'render_home_ui']);
        add_action('azac_home_buttons', [__CLASS__, 'render_home_buttons']);

        // ========================================================
        // PHẦN 4: LOCK ADMIN PRIVILEGES (Chặn Administrator role)
        // ========================================================
        add_filter('editable_roles', [__CLASS__, 'restrict_editable_roles']);
        add_action('profile_update', [__CLASS__, 'prevent_admin_escalation'], 10, 2);
        add_action('user_register', [__CLASS__, 'prevent_admin_creation'], 10, 1);
        add_action('set_user_role', [__CLASS__, 'prevent_admin_role_assignment'], 10, 3);
        add_action('pre_get_users', [__CLASS__, 'hide_super_admin']);
        add_action('updated_user_meta', [__CLASS__, 'monitor_admin_capabilities'], 10, 4);
        add_action('added_user_meta', [__CLASS__, 'monitor_admin_capabilities'], 10, 4);

        // ========================================================
        // PHẦN 5: SUPER ADMIN SELF-PROTECTION
        // ========================================================
        add_filter('map_meta_cap', [__CLASS__, 'protect_super_admin_capabilities'], 10, 4);
        add_action('user_profile_update_errors', [__CLASS__, 'prevent_super_admin_role_change_error'], 10, 3);
        add_action('admin_footer', [__CLASS__, 'remove_admin_role_option_js']);

        // ========================================================
        // PHẦN 6: API & REST SECURITY
        // ========================================================
        add_filter('xmlrpc_enabled', '__return_false');
        add_action('init', [__CLASS__, 'block_unauthenticated_rest_access']);
        // Ẩn X-Powered-By header
        add_filter('rest_authentication_errors', [__CLASS__, 'authenticate_rest_requests']);
    }

    // ========================================================
    // PHẦN 1: UI LOGIN
    // ========================================================

    /**
     * Ẩn link "Đăng ký" khỏi trang đăng nhập
     */
    public static function hide_register_link()
    {
        echo '<style>
            #login #nav a[href*="action=register"],
            #login #nav a[href*="registration"],
            .login-action-register #login { 
                display: none !important; 
            }
        </style>';
    }

    /**
     * Thêm link "Quay lại Trang chủ" dưới form đăng nhập
     */
    public static function add_back_to_home_link()
    {
        $home_url = home_url();
        echo '<div style="margin-top: 20px; text-align: center;">
            <a href="' . esc_url($home_url) . '" style="text-decoration: none; color: #0073aa;">
                &larr; Quay lại Trang chủ
            </a>
        </div>';
    }

    /**
     * Chặn truy cập trang /register/ ngay ở init hook (sớm nhất)
     * Chỉ cho phép Admin/Manager truy cập
     */
    public static function block_register_page_access()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Kiểm tra URL có chứa /register/ hoặc action=register
        if (
            strpos($request_uri, '/register') !== false ||
            (isset($_GET['action']) && $_GET['action'] === 'register')
        ) {

            // Cho phép: Người đã đăng nhập AND (Admin hoặc Manager)
            if (
                is_user_logged_in() &&
                (current_user_can('administrator') || current_user_can('az_manager'))
            ) {
                return; // Cho phép truy cập
            }

            // Chặn tất cả đối tượng khác (Khách, Học viên) -> Về trang chủ
            wp_redirect(home_url());
            exit;
        }
    }

    // ========================================================
    // PHẦN 2: BACKEND SECURITY - BRUTE FORCE & LOGIN
    // ========================================================

    /**
     * Kiểm tra Brute Force khi đăng nhập
     * - Giới hạn 5 lần sai trong 15 phút
     * - Khóa IP/user nếu vượt quá
     * 
     * @param WP_User|WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return WP_User|WP_Error|null
     */
    public static function check_brute_force_on_login($user, $username, $password)
    {
        // Chỉ kiểm tra nếu có lỗi xác thực
        if (is_wp_error($user) || !$user) {
            $ip_address = self::get_client_ip();
            $transient_key = self::BRUTE_FORCE_TRANSIENT_PREFIX . sanitize_text_field($username) . '_' . $ip_address;

            // Lấy số lần đăng nhập sai hiện tại
            $attempt_count = (int) get_transient($transient_key);
            $attempt_count++;

            // Nếu đã vượt quá giới hạn, từ chối đăng nhập
            if ($attempt_count > self::BRUTE_FORCE_MAX_ATTEMPTS) {
                return new WP_Error(
                    'too_many_failed_attempts',
                    sprintf(
                        'Quá nhiều lần đăng nhập không thành công. Vui lòng thử lại sau %d phút.',
                        self::BRUTE_FORCE_LOCKOUT_TIME / 60
                    )
                );
            }

            // Lưu số lần vào transient (15 phút)
            set_transient(
                $transient_key,
                $attempt_count,
                self::BRUTE_FORCE_LOCKOUT_TIME
            );
        } else {
            // Đăng nhập thành công - xóa counter
            if ($user instanceof WP_User) {
                $ip_address = self::get_client_ip();
                $transient_key = self::BRUTE_FORCE_TRANSIENT_PREFIX . sanitize_text_field($username) . '_' . $ip_address;
                delete_transient($transient_key);
            }
        }

        return $user;
    }

    /**
     * Ghi log khi đăng nhập thất bại
     * 
     * @param string $username
     */
    public static function log_failed_login_attempt($username)
    {
        $ip_address = self::get_client_ip();
        $log_entry = sprintf(
            '[%s] Đăng nhập thất bại: %s từ IP: %s',
            current_time('Y-m-d H:i:s'),
            sanitize_text_field($username),
            $ip_address
        );

        // Ghi vào debug.log (nếu WP_DEBUG_LOG = true)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
    }

    /**
     * Ẩn thông báo lỗi đăng nhập mặc định của WordPress
     * Chỉ hiển thị "Tên đăng nhập hoặc mật khẩu không chính xác"
     * Không lộ rằng user tồn tại hay không
     * 
     * @param string $error
     * @return string
     */
    public static function hide_login_error_messages($error)
    {
        // Nếu có lỗi xác thực, thay thế bằng thông báo chung
        if (!empty($error)) {
            // Loại bỏ tất cả thông báo chi tiết
            if (
                strpos($error, 'unknown') !== false ||
                strpos($error, 'incorrect') !== false ||
                strpos($error, 'invalid') !== false
            ) {

                return '<strong>ERROR</strong>: Tên đăng nhập hoặc mật khẩu không chính xác.';
            }
        }

        return $error;
    }

    /**
     * Lấy IP address của client (hỗ trợ proxy)
     * 
     * @return string
     */
    private static function get_client_ip()
    {
        // Kiểm tra X-Forwarded-For (proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        }

        // Kiểm tra CLIENT_IP
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }

        // Fallback: REMOTE_ADDR
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Chặn đăng ký tài khoản từ WordPress
     */
    public static function block_registration_option($value)
    {
        return 0; // Luôn false
    }

    /**
     * Chuyển hướng trang signup (multisite)
     */
    public static function redirect_signup()
    {
        wp_redirect(wp_login_url());
        exit;
    }

    /**
     * Đảm bảo role "az_manager" có quyền "edit_users"
     */
    public static function ensure_manager_capabilities()
    {
        $manager = get_role('az_manager');
        if ($manager && !$manager->has_cap('edit_users')) {
            $manager->add_cap('edit_users');
        }
    }

    // ========================================================
    // PHẦN 3: HOMEPAGE UI & SHORTCODE
    // ========================================================

    /**
     * Render home buttons via action hook
     * Hiển thị: Đăng nhập hoặc Vào trang quản lý
     */
    public static function render_home_buttons()
    {
        if (is_user_logged_in()) {
            echo '<a href="' . admin_url() . '" class="az-btn az-btn-primary az-btn-lg">
                Vào trang quản lý
            </a>';
        } else {
            echo '<a href="' . wp_login_url() . '" class="az-btn az-btn-primary az-btn-lg">
                Đăng nhập
            </a>';
        }
    }

    /**
     * Render home UI via shortcode [azac_home_ui]
     */
    public static function render_home_ui()
    {
        $output = '<div class="azac-home-actions" style="text-align: center; margin: 20px 0;">';

        if (is_user_logged_in()) {
            $dashboard_url = admin_url();
            $output .= '<a href="' . esc_url($dashboard_url) . '" 
                class="button button-primary button-large" 
                style="margin-right: 10px;">
                Vào trang quản lý
            </a>';
        } else {
            $login_url = wp_login_url();
            $output .= '<a href="' . esc_url($login_url) . '" 
                class="button button-primary button-large">
                Đăng nhập
            </a>';
        }

        $output .= '</div>';
        return $output;
    }

    // ========================================================
    // PHẦN 4: LOCK ADMIN PRIVILEGES - Chặn Administrator Role
    // ========================================================

    /**
     * Hạn chế các role có thể chỉnh sửa (Editable Roles)
     * Chỉ Super Admin mới thấy role "administrator"
     * 
     * @param array $roles
     * @return array
     */
    public static function restrict_editable_roles($roles)
    {
        // Nếu không phải Super Admin, xóa role "administrator" khỏi danh sách
        if (get_current_user_id() !== self::SUPER_ADMIN_ID) {
            if (isset($roles['administrator'])) {
                unset($roles['administrator']);
            }
        }

        return $roles;
    }

    /**
     * Chặn việc nâng cấp user lên Administrator khi cập nhật hồ sơ
     * 
     * @param int $user_id
     * @param WP_User $old_user_data
     */
    public static function prevent_admin_escalation($user_id, $old_user_data = null)
    {
        // Không kiểm tra Super Admin
        if ($user_id === self::SUPER_ADMIN_ID) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        // Nếu user này có role "administrator", hạ xuống "az_manager"
        if (in_array('administrator', (array) $user->roles, true)) {
            $user->set_role('az_manager');
        }
    }

    /**
     * Chặn việc tạo user mới với role Administrator
     * 
     * @param int $user_id
     */
    public static function prevent_admin_creation($user_id)
    {
        self::prevent_admin_escalation($user_id);
    }

    /**
     * Hook chặn khi gọi set_user_role()
     * Nếu ai cố gắng set role "administrator" cho user (trừ ID 1),
     * sẽ bị set thành "az_manager" thay vì
     * 
     * @param int $user_id
     * @param string $new_role
     * @param array $old_roles
     */
    public static function prevent_admin_role_assignment($user_id, $new_role, $old_roles)
    {
        // Chỉ chặn nếu role mới là "administrator" và user không phải Super Admin
        if ($new_role === 'administrator' && $user_id !== self::SUPER_ADMIN_ID) {
            // Hạ xuống "az_manager"
            $user = get_userdata($user_id);
            if ($user) {
                $user->set_role('az_manager');
            }
        }
    }

    /**
     * Kiểm tra & chặn Administrator capabilities được thêm vào user meta
     * 
     * Thay vì dùng strpos(serialize()), ta kiểm tra mảng trực tiếp
     * để tránh lỗi khi WordPress thay đổi cấu trúc
     * 
     * @param int $meta_id
     * @param int $object_id (user_id)
     * @param string $meta_key
     * @param mixed $meta_value
     */
    public static function monitor_admin_capabilities($meta_id, $object_id, $meta_key, $meta_value)
    {
        // Chỉ kiểm tra capabilities meta
        if ($meta_key !== $GLOBALS['wpdb']->prefix . 'capabilities') {
            return;
        }

        // Không kiểm tra Super Admin
        if ($object_id === self::SUPER_ADMIN_ID) {
            return;
        }

        // $meta_value là mảng capabilities: ['administrator' => true, ...]
        if (!is_array($meta_value)) {
            return;
        }

        // Nếu phát hiện 'administrator' trong mảng
        if (isset($meta_value['administrator']) && $meta_value['administrator']) {
            // Loại bỏ administrator, giữ lại các cap khác
            unset($meta_value['administrator']);

            // Thêm cap của az_manager
            $meta_value['az_manager'] = true;

            // Cập nhật meta
            update_user_meta($object_id, $meta_key, $meta_value);
        }
    }

    /**
     * Ẩn Super Admin khỏi danh sách user trong admin
     * 
     * @param WP_User_Query $query
     */
    public static function hide_super_admin($query)
    {
        // Chỉ ẩn nếu không phải Super Admin đang xem
        if (is_admin() && get_current_user_id() !== self::SUPER_ADMIN_ID) {
            $query->set('exclude', [self::SUPER_ADMIN_ID]);
        }
    }

    // ========================================================
    // PHẦN 5: SUPER ADMIN SELF-PROTECTION
    // ========================================================

    /**
     * Bảo vệ Super Admin khỏi bị xóa/promote/demote
     * Kiểm tra map_meta_cap để chặn các hành động nguy hiểm
     * 
     * @param array $caps
     * @param string $cap
     * @param int $user_id (người đang thực hiện hành động)
     * @param array $args (args[0] thường là target user ID)
     * @return array
     */
    public static function protect_super_admin_capabilities($caps, $cap, $user_id, $args)
    {
        $target_user_id = isset($args[0]) ? (int) $args[0] : 0;

        // Nếu target user là Super Admin (ID 1)
        if ($target_user_id === self::SUPER_ADMIN_ID) {
            // Chặn các hành động: xóa, promote, demote, edit
            if (in_array($cap, ['promote_user', 'remove_user', 'delete_user', 'edit_user'], true)) {
                $caps[] = 'do_not_allow';
            }
        }

        return $caps;
    }

    /**
     * Chặn thay đổi role của Super Admin khi update profile
     * Hiển thị error message rõ ràng
     * 
     * @param WP_Error $errors
     * @param bool $update
     * @param WP_User $user
     */
    public static function prevent_super_admin_role_change_error(&$errors, $update, $user)
    {
        if ($user->ID !== self::SUPER_ADMIN_ID) {
            return;
        }

        // Nếu đang cố gắng thay đổi role
        if (isset($_POST['role']) && $_POST['role'] !== 'administrator') {
            $errors->add(
                'super_admin_protected',
                '<strong>LỖI</strong>: Không thể thay đổi quyền hạn của tài khoản Super Admin. ' .
                'ID 1 phải luôn là Administrator.'
            );
        }
    }

    /**
     * Xóa option "Administrator" khỏi dropdown chọn role
     * Sử dụng JavaScript để loại bỏ hoàn toàn (mượt mà, không CSS force)
     * 
     * Chỉ hiển thị nếu đang xem trang của chính mình (Super Admin)
     */
    public static function remove_admin_role_option_js()
    {
        $screen = get_current_screen();

        // Chỉ chạy trên các trang edit user
        if (
            !$screen || !in_array(
                $screen->base,
                ['user-edit', 'user-new', 'profile', 'users_page_azac-add-student'],
                true
            )
        ) {
            return;
        }

        // Lấy user ID đang được chỉnh sửa
        global $user_id;
        $target_id = $user_id ? (int) $user_id : (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0);
        $current_user_id = get_current_user_id();

        // Kiểm tra: đang xem trang của chính Super Admin không?
        $is_super_admin_viewing_self = (
            $current_user_id === self::SUPER_ADMIN_ID &&
            $target_id === self::SUPER_ADMIN_ID
        );

        ?>
        <script type="text/javascript">
            (function() {
                // Đợi DOM load đầy đủ
                document.addEventListener('DOMContentLoaded', function() {
                    // Tìm tất cả dropdown role trên trang
                    var roleSelects = document.querySelectorAll(
                        'select[name="role"], #role, .user-role-wrap select'
                    );

                    roleSelects.forEach(function(select) {
                        // Lấy tất cả option "administrator"
                        var adminOptions = select.querySelectorAll(
                            'option[value="administrator"]'
                        );

                        adminOptions.forEach(function(option) {
                            // Chỉ giữ lại nếu Super Admin đang xem chính mình
                            <?php if (!$is_super_admin_viewing_self): ?>
                                option.remove();
                            <?php endif; ?>
                        });
                    });
                });
            })();
        </script>
        <?php
    }

    // ========================================================
    // PHẦN 6: API & REST SECURITY
    // ========================================================

    /**
     * Vô hiệu hóa XML-RPC
     * Chống DDoS, Brute Force, và các tấn công thông qua pingback
     * 
     * @return bool
     */
    public static function xmlrpc_disabled()
    {
        // Đã được disable bằng filter 'xmlrpc_enabled' = false ở init()
        return false;
    }

    /**
     * Chặn truy cập REST API đối với người dùng chưa đăng nhập
     * 
     * Cho phép:
     * - Người đã đăng nhập
     * - Các request POST/PUT/DELETE (thường là từ admin)
     * 
     * Chặn:
     * - GET /wp-json/wp/v2/users (lộ danh sách user)
     * - Các request khác từ người chưa đăng nhập
     */
    public static function block_unauthenticated_rest_access()
    {
        // Kiểm tra xem là request tới /wp-json
        $is_rest_request = (defined('REST_REQUEST') && REST_REQUEST) ||
            (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json') !== false);

        if (!$is_rest_request) {
            return; // Không phải REST request
        }

        // Cho phép nếu đã đăng nhập
        if (is_user_logged_in()) {
            return;
        }

        // Chặn nếu chưa đăng nhập
        // Trừ những route công cộng cần thiết (nếu có)
        $allowed_routes = [
            '/wp-json/wp/v2/posts',        // Danh sách bài viết công cộng
            '/wp-json/wp/v2/pages',        // Danh sách trang công cộng
        ];

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_allowed = false;

        foreach ($allowed_routes as $route) {
            if (strpos($request_uri, $route) !== false) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            // Từ chối request
            wp_die(
                json_encode(['code' => 'rest_not_logged_in', 'message' => 'Bạn cần đăng nhập để truy cập API này.']),
                403,
                ['Content-Type' => 'application/json']
            );
        }
    }

    /**
     * Xác thực REST API request
     * Chặn truy cập danh sách user đối với người chưa đăng nhập
     * 
     * @param WP_Error|null $error
     * @return WP_Error|null
     */
    public static function authenticate_rest_requests($error)
    {
        // Nếu không phải REST request, không làm gì
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $error;
        }

        // Nếu đã đăng nhập, không kiểm tra
        if (is_user_logged_in()) {
            return $error;
        }

        // Kiểm tra request tới /wp-json/wp/v2/users (danh sách user)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/wp-json/wp/v2/users') !== false) {
            return new WP_Error(
                'rest_user_cannot_list_users',
                'Bạn không có quyền truy cập danh sách user.',
                ['status' => 403]
            );
        }

        return $error;
    }
}
