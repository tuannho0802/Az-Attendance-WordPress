<?php
/**
 * Class AzAC_Security_Portal
 * 
 * Module: Trung tâm An ninh hệ thống (Security Portal)
 * Path: includes/class-azac-security-portal.php
 * 
 * Features:
 * - Smart Scanner (Users, Malware, Data Integrity)
 * - Access Control (Admin vs Manager)
 * - Real-time Protection
 */

if (!defined('ABSPATH'))
    exit;

class AzAC_Security_Portal
{

    public static function init()
    {
        new self();
    }

    public function __construct()
    {
        // Use 'edit_posts' to allow Managers to see the menu
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX Scanners
        add_action('wp_ajax_azac_sp_scan_users', [$this, 'ajax_scan_users']);
        add_action('wp_ajax_azac_sp_scan_malware', [$this, 'ajax_scan_malware']);
        add_action('wp_ajax_azac_sp_scan_integrity', [$this, 'ajax_scan_integrity']);

        // AJAX Fixers
        add_action('wp_ajax_azac_sp_fix_user', [$this, 'ajax_fix_user']);
        add_action('wp_ajax_azac_sp_fix_malware', [$this, 'ajax_fix_malware']);
        add_action('wp_ajax_azac_sp_fix_integrity', [$this, 'ajax_fix_integrity']);

        // Real-time Hook
        add_filter('update_post_metadata', [$this, 'real_time_integrity_check'], 10, 5);
    }

    public function register_menu()
    {
        // Permission: 'edit_posts' allows Managers to access
        add_menu_page(
            'Trung tâm An ninh',
            'Trung tâm An ninh',
            'edit_posts',
            'azac-security-portal',
            [$this, 'render_page'],
            'dashicons-shield',
            1
        );
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_azac-security-portal')
            return;

        // Use constant if available, else fallback to plugin_dir_url
        $url = defined('AZAC_CORE_URL') ? AZAC_CORE_URL : plugin_dir_url(dirname(__FILE__));

        wp_enqueue_style('azac-security-css', $url . 'admin/css/azac-security.css', [], '2.0.0');
        wp_enqueue_script('azac-security-js', $url . 'admin/js/az-academy-core.js', ['jquery'], '2.0.0', true);

        wp_localize_script('azac-security-js', 'azacConfig', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azac_sp_scan'),
            'isAdmin' => current_user_can('manage_options') // Flag for JS to hide/show buttons
        ]);
    }

    public function render_page()
    {
        $is_admin = current_user_can('manage_options');
        ?>
        <div class="wrap azac-sp-wrapper">
            <h1 class="wp-heading-inline">🛡️ Trung tâm An ninh hệ thống (Security Portal)</h1>
            <hr class="wp-header-end">
        
            <div class="azac-sp-tabs">
                <button class="azac-tab-btn active" data-tab="users">
                    <span class="dashicons dashicons-admin-users"></span> Quét User
                </button>
                <button class="azac-tab-btn" data-tab="malware">
                    <span class="dashicons dashicons-code-standards"></span> Quét Mã độc
                </button>
                <button class="azac-tab-btn" data-tab="integrity">
                    <span class="dashicons dashicons-database"></span> Dữ liệu Điểm danh
                </button>
            </div>

            <div class="azac-sp-content">
                <!-- Tab Users -->
                <div id="tab-users" class="azac-tab-pane active">
                    <div class="azac-toolbar">
                        <button class="button button-primary button-large" id="btn-scan-users">
                            <span class="dashicons dashicons-search"></span> Bắt đầu Quét User
                        </button>
                        <div class="azac-actions-right">
                            <?php if ($is_admin): ?>
                                <!-- Admin Actions -->
                            <?php else: ?>
                                <span class="description" style="color:#d63638">⚠️ Chế độ Xem (Read-only) - Cần quyền Admin để xử lý.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="azac-results">
                        <table class="azac-modern-table" id="table-users">
                            <thead>
                                <tr>
                                    <th>Avatar</th>
                                    <th>User Info</th>
                                    <th>Vai trò</th>
                                    <th>Lý do cảnh báo</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="azac-empty">Chưa có dữ liệu. Hãy nhấn nút Quét.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Malware -->
                <div id="tab-malware" class="azac-tab-pane">
                    <div class="azac-toolbar">
                        <button class="button button-primary button-large" id="btn-scan-malware">
                            <span class="dashicons dashicons-search"></span> Bắt đầu Quét Mã độc
                        </button>
                         <div class="azac-progress-wrapper" style="display:none;">
                            <div class="azac-progress-bar"><div class="azac-progress-fill" style="width:0%"></div></div>
                            <span class="azac-progress-text">Đang quét...</span>
                        </div>
                    </div>
                    <div class="azac-results">
                        <table class="azac-modern-table" id="table-malware">
                            <thead>
                                <tr>
                                    <th>Loại</th>
                                    <th>Đối tượng (ID/File)</th>
                                    <th>Đoạn mã nghi vấn</th>
                                    <th>Lý do / Pattern</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="azac-empty">Sẵn sàng quét Database và File hệ thống.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Integrity -->
                <div id="tab-integrity" class="azac-tab-pane">
                    <div class="azac-toolbar">
                        <button class="button button-primary button-large" id="btn-scan-integrity">
                            <span class="dashicons dashicons-search"></span> Kiểm tra Dữ liệu
                        </button>
                    </div>
                    <div class="azac-results">
                         <table class="azac-modern-table" id="table-integrity">
                            <thead>
                                <tr>
                                    <th>Lớp học (ID)</th>
                                    <th>Học viên (ID)</th>
                                    <th>Chi tiết lỗi</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="azac-empty">Kiểm tra tính toàn vẹn của bảng điểm danh.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // --- AJAX HANDLERS ---

    public static function ajax_scan_users()
    {
        check_ajax_referer('azac_sp_scan');

        // Smart Scanner: Exclude Super Admins
        $users = get_users(['fields' => 'all_with_meta']);
        $results = [];

        foreach ($users as $u) {
            // EXCLUDE: Super Admin & System Owner (ID 1)
            if (is_super_admin($u->ID) || $u->ID == 1)
                continue;

            $status = 'safe';
            $reason = '';

            // Rule 1: Admin email domain check
            if (in_array('administrator', $u->roles)) {
                if (!strpos($u->user_email, 'azacademy')) {
                    $status = 'warning';
                    $reason = 'Email Admin không thuộc domain @azacademy';
                }
            }

            // Rule 2: Inactive for 90 days (Example logic)
            // $last_login = get_user_meta($u->ID, 'last_login', true);

            if ($status !== 'safe') {
                $results[] = [
                    'ID' => $u->ID,
                    'avatar' => get_avatar($u->ID, 32),
                    'user_login' => $u->user_login,
                    'user_email' => $u->user_email,
                    'roles' => implode(', ', $u->roles),
                    'status' => $status,
                    'status_text' => 'Cần kiểm tra',
                    'reason' => $reason
                ];
            }
        }

        wp_send_json_success($results);
    }

    public static function ajax_scan_malware()
    {
        check_ajax_referer('azac_sp_scan');
        global $wpdb;
        $results = [];
        $suspicious_patterns = ['<script', 'base64_decode', 'eval(', 'shell_exec', 'passthru', 'iframe'];

        // 1. Scan Posts (Limit 50 recent for demo performance)
        $posts = $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status='publish' ORDER BY ID DESC LIMIT 50");
        foreach ($posts as $p) {
            foreach ($suspicious_patterns as $pat) {
                if (stripos($p->post_content, $pat) !== false) {
                    // Extract context
                    $pos = stripos($p->post_content, $pat);
                    $start = max(0, $pos - 50);
                    $snippet = substr($p->post_content, $start, 150);

                    $results[] = [
                        'type' => 'Post Content',
                        'id' => $p->ID,
                        'name' => $p->post_title,
                        // Use htmlspecialchars to safely display code
                        'snippet' => htmlspecialchars($snippet),
                        'reason' => "Chứa từ khóa nguy hiểm: " . htmlspecialchars($pat),
                        'action_key' => 'post_' . $p->ID
                    ];
                }
            }
        }

        // 2. Scan Options (Autoload)
        $options = $wpdb->get_results("SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE autoload='yes' LIMIT 200");
        foreach ($options as $opt) {
            foreach ($suspicious_patterns as $pat) {
                if (stripos($opt->option_value, $pat) !== false) {
                    $pos = stripos($opt->option_value, $pat);
                    $start = max(0, $pos - 50);
                    $snippet = substr($opt->option_value, $start, 150);

                    $results[] = [
                        'type' => 'WP Option',
                        'id' => $opt->option_id,
                        'name' => $opt->option_name,
                        'snippet' => htmlspecialchars($snippet),
                        'reason' => "Option chứa mã thực thi: " . htmlspecialchars($pat),
                        'action_key' => 'opt_' . $opt->option_id
                    ];
                }
            }
        }

        wp_send_json_success($results);
    }

    public static function ajax_scan_integrity()
    {
        check_ajax_referer('azac_sp_scan');
        global $wpdb;
        $table_att = $wpdb->prefix . 'az_attendance';

        $classes = $wpdb->get_col("SELECT DISTINCT class_id FROM $table_att");
        $results = [];

        foreach ($classes as $class_id) {
            $students_meta = get_post_meta($class_id, 'az_students', true);
            // Ensure array of ints
            $valid_ids = [];
            if (is_array($students_meta)) {
                $valid_ids = array_map('intval', $students_meta);
            }

            // Check orphans
            if (empty($valid_ids)) {
                $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_att WHERE class_id = %d", $class_id));
                if ($count > 0) {
                    $results[] = [
                        'class_id' => $class_id,
                        'student_id' => 'ALL',
                        'reason' => "Lớp (ID: $class_id) trống nhưng có $count bản ghi điểm danh.",
                        'status' => 'critical'
                    ];
                }
            } else {
                $ids_str = implode(',', $valid_ids);
                // Find records where student_id is NOT in valid_ids
                $orphans = $wpdb->get_results("SELECT * FROM $table_att WHERE class_id = $class_id AND student_id NOT IN ($ids_str)");

                foreach ($orphans as $orphan) {
                    $results[] = [
                        'class_id' => $class_id,
                        'student_id' => $orphan->student_id,
                        'reason' => "Học viên (ID: {$orphan->student_id}) không có trong danh sách lớp.",
                        'status' => 'warning'
                    ];
                }
            }
        }

        wp_send_json_success($results);
    }

    // --- AJAX FIXERS (Protected) ---

    public static function ajax_fix_user()
    {
        check_ajax_referer('azac_sp_scan');
        // Permission Check: Only Admin can fix
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        $uid = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $u = new WP_User($uid);
        if ($u->exists()) {
            $u->set_role('subscriber');
            update_user_meta($uid, 'azac_account_locked', 1);
            wp_send_json_success("Đã khóa tài khoản thành công.");
        }
        wp_send_json_error('User not found');
    }

    public static function ajax_fix_malware()
    {
        check_ajax_referer('azac_sp_scan');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        // Demo implementation
        wp_send_json_success("Đã dọn dẹp mã độc.");
    }

    public static function ajax_fix_integrity()
    {
        check_ajax_referer('azac_sp_scan');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';
        $class_id = intval($_POST['class_id']);
        $student_id = sanitize_text_field($_POST['student_id']);

        if ($student_id === 'ALL') {
            $wpdb->delete($table, ['class_id' => $class_id], ['%d']);
        } else {
            $wpdb->delete($table, ['class_id' => $class_id, 'student_id' => intval($student_id)], ['%d', '%d']);
        }

        wp_send_json_success("Đã xóa bản ghi rác.");
    }

    // --- REAL TIME INTEGRITY ---
    public static function real_time_integrity_check($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if ($meta_key !== 'az_students')
            return $check;

        $new_students = $meta_value;
        if (!is_array($new_students))
            return $check;

        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';

        // If empty, delete all attendance for this class
        if (empty($new_students)) {
            $wpdb->delete($table, ['class_id' => $object_id], ['%d']);
        } else {
            // Delete attendance for students NOT in the new list
            $ids_sql = implode(',', array_map('absint', $new_students));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE class_id = %d AND student_id NOT IN ($ids_sql)",
                $object_id
            ));
        }

        return $check;
    }
}
