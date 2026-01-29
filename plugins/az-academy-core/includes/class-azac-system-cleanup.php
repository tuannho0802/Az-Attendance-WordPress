<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_System_Cleanup
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_azac_system_cleanup', [__CLASS__, 'ajax_cleanup']);

        // Check & Create Table / Migrate Logs
        add_action('admin_init', [__CLASS__, 'maybe_migrate_logs']);

        // Audit Log Hooks
        add_action('user_register', [__CLASS__, 'log_user_register']);
        add_action('profile_update', [__CLASS__, 'log_user_update']);
        add_action('wp_insert_post', [__CLASS__, 'log_post_update'], 10, 3);
        add_action('deleted_post', [__CLASS__, 'log_post_delete']);

        // Session & Attendance Hooks
        add_action('azac_session_created', [__CLASS__, 'log_session_created'], 10, 4);
        add_action('azac_session_updated', [__CLASS__, 'log_session_updated'], 10, 4);
        add_action('azac_session_deleted', [__CLASS__, 'log_session_deleted'], 10, 3);
        add_action('azac_attendance_saved', [__CLASS__, 'log_attendance_saved'], 10, 4);
        add_action('azac_teacher_checkin_event', [__CLASS__, 'log_teacher_checkin'], 10, 4);

        // Log Cleanup Hook
        add_action('wp_ajax_azac_cleanup_logs', [__CLASS__, 'ajax_cleanup_logs']);

        // AJAX Pagination Hooks
        add_action('wp_ajax_azac_get_logs', [__CLASS__, 'ajax_get_logs']);
        add_action('wp_ajax_azac_get_scan_data', [__CLASS__, 'ajax_get_scan_data']);

        // Batch Scan Hooks
        add_action('wp_ajax_azac_init_physical_scan', [__CLASS__, 'ajax_init_physical_scan']);
        add_action('wp_ajax_azac_scan_physical_folder', [__CLASS__, 'ajax_scan_physical_folder']);
    }

    public static function maybe_migrate_logs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'az_system_logs';

        // Check if table exists or needs update
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) DEFAULT 0,
            user_login varchar(60) DEFAULT '',
            action_type varchar(50) DEFAULT '',
            message text,
            PRIMARY KEY  (id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Migrate from Option if exists
        $old_logs = get_option('azac_system_logs');
        if (!empty($old_logs) && is_array($old_logs)) {
            // Reverse to insert oldest first
            $old_logs = array_reverse($old_logs);

            foreach ($old_logs as $log) {
                $wpdb->insert($table_name, [
                    'created_at' => isset($log['time']) ? $log['time'] : current_time('mysql'),
                    'user_login' => isset($log['user']) ? $log['user'] : '',
                    'action_type' => isset($log['action']) ? $log['action'] : 'LEGACY',
                    'message' => isset($log['message']) ? $log['message'] : ''
                ]);
            }
            delete_option('azac_system_logs');
        }

        // --- OPTIMIZATION: Add Indexes to Custom Tables ---
        self::ensure_custom_indexes();
    }

    private static function ensure_custom_indexes()
    {
        global $wpdb;

        // 1. az_sessions
        $table = $wpdb->prefix . 'az_sessions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            // Check & Add Index for created_at
            if (!$wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'created_at'")) {
                $wpdb->query("ALTER TABLE $table ADD INDEX created_at (created_at)");
            }
            // Check & Add Index for class_id
            if (!$wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'class_id'")) {
                $wpdb->query("ALTER TABLE $table ADD INDEX class_id (class_id)");
            }
        }

        // 2. az_attendance
        $table = $wpdb->prefix . 'az_attendance';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            // Check class_id, session_date
            if (!$wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'class_session'")) {
                $wpdb->query("ALTER TABLE $table ADD INDEX class_session (class_id, session_date)");
            }
            // Check session_id
            if (!$wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'session_id'")) {
                $wpdb->query("ALTER TABLE $table ADD INDEX session_id (session_id)");
            }
            // Check student_id
            if (!$wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'student_id'")) {
                $wpdb->query("ALTER TABLE $table ADD INDEX student_id (student_id)");
            }
        }
    }

    // --- LOGGING HELPERS ---
    public static function log($action, $message, $user_id = null)
    {
                global $wpdb;
        if (!$user_id)
            $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        $username = $user ? $user->user_login : 'System';

        $table_name = $wpdb->prefix . 'az_system_logs';

        // Ensure table exists (in case it was dropped or not created yet)
        // Optimization: Don't check every time. Assume it exists after admin_init.

        $wpdb->insert($table_name, [
            'created_at' => current_time('mysql'),
            'user_id' => $user_id,
            'user_login' => $username,
            'action_type' => $action,
            'message' => $message
        ]);
    }

    public static function log_user_register($user_id)
    {
        self::log('USER_NEW', "Đăng ký thành viên mới: ID $user_id", $user_id);
    }

    public static function log_user_update($user_id)
    {
        self::log('USER_UPDATE', "Cập nhật hồ sơ thành viên: ID $user_id", $user_id);
    }

    public static function log_post_update($post_id, $post, $update)
    {
        if ($post->post_type === 'az_class') {
            $action = $update ? 'CLASS_UPDATE' : 'CLASS_CREATE';
            self::log($action, "Lớp học: " . $post->post_title . " (ID $post_id)");
        }
    }

    public static function log_post_delete($post_id)
    {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'az_class') {
            self::log('CLASS_DELETE', "Xóa lớp học: " . $post->post_title . " (ID $post_id)");
        }
    }

    public static function log_session_created($class_id, $date, $time, $user_id)
    {
        $class_title = get_the_title($class_id);
        self::log('SESSION_CREATE', "Tạo buổi học: $class_title (Date: $date, Time: $time)", $user_id);
    }

    public static function log_session_updated($class_id, $old_date, $new_date, $user_id)
    {
        $class_title = get_the_title($class_id);
        self::log('SESSION_UPDATE', "Cập nhật buổi học: $class_title (Old: $old_date -> New: $new_date)", $user_id);
    }

    public static function log_session_content_updated($class_id, $date, $user_id)
    {
        $class_title = get_the_title($class_id);
        self::log('SESSION_CONTENT', "Cập nhật nội dung buổi học: $class_title (Date: $date)", $user_id);
    }

    public static function log_session_deleted($class_id, $date, $user_id)
    {
        $class_title = get_the_title($class_id);
        self::log('SESSION_DELETE', "Xóa buổi học: $class_title (Date: $date)", $user_id);
    }

    public static function log_attendance_saved($class_id, $date, $type, $user_id)
    {
        $class_title = get_the_title($class_id);
        self::log('ATTENDANCE_SAVE', "Lưu điểm danh ($type): $class_title (Date: $date)", $user_id);
    }

    public static function log_teacher_checkin($class_id, $date, $is_checkin, $user_id)
    {
        $class_title = get_the_title($class_id);
        $status = $is_checkin ? "Check-in" : "Hủy Check-in";
        self::log('TEACHER_CHECKIN', "Giảng viên $status: $class_title (Date: $date)", $user_id);
    }

    public static function register_menu()
    {
        add_menu_page(
            'Hệ thống',
            'Hệ thống',
            'manage_options', // Admin & Manager (both have manage_options)
            'azac-system',
            [__CLASS__, 'render_page'],
            'dashicons-admin-generic', // Icon
            1 // Position
        );
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_azac-system') {
            return;
        }

        wp_enqueue_style('azac-admin-style', AZAC_CORE_URL . 'admin/css/admin-style.css', [], AZAC_CORE_VERSION);
        // We can reuse attendance-list.css for table styles if needed, or just rely on WP core + inline
        wp_enqueue_style('azac-attendance-list', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);

        wp_enqueue_script('azac-system-cleanup', AZAC_CORE_URL . 'admin/js/system-cleanup.js', ['jquery'], AZAC_CORE_VERSION, true);

        wp_localize_script('azac-system-cleanup', 'AZAC_SYSTEM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azac_system_cleanup_nonce'),
            'cleanupLogsNonce' => wp_create_nonce('azac_cleanup_logs_nonce'),
            'confirmDelete' => 'Bạn có chắc chắn muốn xóa dữ liệu này? Hành động không thể hoàn tác.',
            'confirmBulkDelete' => 'Bạn có chắc chắn muốn dọn dẹp toàn bộ mục đã chọn?',
            'confirmLogDelete' => 'Bạn có chắc chắn muốn xóa các nhật ký đã chọn?'
        ]);
    }

    public static function render_page()
    {
        // Handle Tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'scan';

        ?>
        <div class="wrap">
            <h1>Hệ thống & Dọn dẹp</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=azac-system&tab=scan"
                    class="nav-tab <?php echo $active_tab === 'scan' ? 'nav-tab-active' : ''; ?>">Quét dọn</a>
                <a href="?page=azac-system&tab=logs"
                    class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">Nhật ký</a>
            </nav>

            <div class="azac-system-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'scan'): ?>
                    <?php self::render_scan_tab(); ?>
                <?php else: ?>
                    <?php self::render_logs_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_scan_tab()
    {
        $can_delete = current_user_can('delete_users');
        ?>
        <div class="azac-layout-wrapper"
            style="background:#fff; padding:20px; border:1px solid #c3c4c7; box-sizing:border-box;">

            <?php if ($can_delete): ?>
            <div class="azac-session-filters-toolbar"
                style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:15px; margin-left:0; padding-left:0;">
                <div style="display:flex; gap:5px; align-items:center;">
                    <select id="azac-bulk-action-system">
                        <option value="-1">Hành động hàng loạt</option>
                        <option value="delete">Dọn dẹp (Xóa vĩnh viễn)</option>
                    </select>
                    <button type="button" id="azac-do-system-bulk" class="button action">Áp dụng</button>
                </div>
                <div style="margin-left:auto;">
                    <strong id="azac-scan-total-count">Đang tải dữ liệu...</strong>
                </div>
            </div>
            <?php else: ?>
                <div class="azac-session-filters-toolbar"
                    style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:15px; margin-left:0; padding-left:0;">
                    <div style="margin-left:auto;">
                        <strong id="azac-scan-total-count">Đang tải dữ liệu...</strong>
                    </div>
                </div>
            <?php endif; ?>

            <div id="azac-scan-container">
                <p class="spinner is-active" style="float:none;"></p> Đang quét hệ thống...
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Init load
                if (typeof window.loadScanData === 'function') {
                    window.loadScanData(1);
                }
            });
        </script>
        <?php
    }

    private static function render_logs_tab($logs_ignored = [])
    {
        // $logs_ignored is ignored because we load via AJAX now
        ?>
            <div class="wrap">
                <!-- Log Filters -->
                <div class="tablenav top" style="height:auto; margin-bottom:10px;">
                    <div class="alignleft actions">
                        <select id="azac-log-filter-action">
                            <option value="">Tất cả hành động</option>
                            <option value="USER">Thành viên (USER_...)</option>
                            <option value="CLASS">Lớp học (CLASS_...)</option>
                            <option value="SESSION">Buổi học (SESSION_...)</option>
                            <option value="ATTENDANCE">Điểm danh (ATTENDANCE_...)</option>
                        </select>
                        <input type="date" id="azac-log-filter-date-start" placeholder="Từ ngày">
                        <input type="date" id="azac-log-filter-date-end" placeholder="Đến ngày">
                        <button type="button" id="azac-log-filter-submit" class="button">Lọc</button>
                    </div>
                </div>



                <div id="azac-logs-container">
                    <p class="spinner is-active" style="float:none;"></p> Đang tải nhật ký...
                </div>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    // Init load
                    if (typeof window.loadLogs === 'function') {
                        window.loadLogs(1);
                    }
                });
            </script>
            <?php
    }

    // --- AJAX HANDLERS ---

    public static function ajax_get_logs()
    {
        ob_start();
        try {
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');

            // Allow Manager to VIEW logs (az_view_system)
            if (!current_user_can('manage_options') && !current_user_can('az_view_system')) {
                throw new Exception('Bạn không có quyền xem nhật ký.');
            }

            global $wpdb;

            $page = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $table_name = $wpdb->prefix . 'az_system_logs';

            // Filters
            $where_clauses = ["1=1"];
            $params = [];

            // Action Filter
            if (!empty($_POST['filter_action'])) {
                $action = sanitize_text_field($_POST['filter_action']);
                $where_clauses[] = "action_type LIKE %s";
                $params[] = $action . '%';
            }

            // Date Filter
            if (!empty($_POST['filter_date_start'])) {
                $where_clauses[] = "created_at >= %s";
                $params[] = sanitize_text_field($_POST['filter_date_start']) . ' 00:00:00';
            }
            if (!empty($_POST['filter_date_end'])) {
                $where_clauses[] = "created_at <= %s";
                $params[] = sanitize_text_field($_POST['filter_date_end']) . ' 23:59:59';
            }

            $where_sql = implode(' AND ', $where_clauses);

            // Count Total
            $count_sql = "SELECT COUNT(id) FROM $table_name WHERE $where_sql";
            if (!empty($params)) {
                $count_sql = $wpdb->prepare($count_sql, $params);
            }
            $total_items = $wpdb->get_var($count_sql);

            // Fetch Items
            $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;

            $sql = $wpdb->prepare($sql, $params);
            $logs = $wpdb->get_results($sql);

            ob_clean(); // Clean before outputting HTML

            $can_delete = current_user_can('delete_users');

            ob_start();
            ?>
                                <div class="tablenav top">
                                    <?php if ($can_delete): ?>
                                    <div class="alignleft actions bulkactions">
                                    <select id="azac-log-bulk-action">
                                        <option value="-1">Hành động hàng loạt</option>
                                        <option value="delete_selected">Xóa đã chọn</option>
                                        <option value="older_30">Xóa cũ hơn 30 ngày</option>
                                        <option value="delete_all">Xóa toàn bộ nhật ký</option>
                                    </select>
                                    <button type="button" id="azac-log-do-bulk" class="button action">Áp dụng</button>
                                </div>
                                <?php endif; ?>
                                    <div class="alignright">
                                        <?php self::render_pagination($total_items, $page, $limit, 'logs'); ?>
                                    </div>
                                    <div class="alignleft">
                                        <strong>Tổng cộng: <?php echo number_format($total_items); ?> dòng</strong>
                                    </div>
                                </div>

                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-logs">
                                            </td>
                                            <th style="width:180px;">Thời gian</th>
                                            <th style="width:150px;">User thực hiện</th>
                                            <th style="width:150px;">Loại hành động</th>
                                            <th>Nội dung chi tiết</th>
                                        </tr>
                                    </thead>
                                    <tbody id="azac-logs-tbody">
                                        <?php if (empty($logs)): ?>
                                                                    <tr>
                                                                        <td colspan="5">Chưa có nhật ký nào.</td>
                                                                    </tr>
                                        <?php else: ?>
                                                                    <?php foreach ($logs as $log):
                                                                        $val = $log->id;
                                                                        ?>
                                                                                                <tr>
                                                                                                    <th scope="row" class="check-column">
                                                                                                        <input type="checkbox" class="cb-select-log" value="<?php echo esc_attr($val); ?>">
                                                                                                    </th>
                                                                                                    <td><?php echo date('d/m/Y h:i A', strtotime($log->created_at)); ?></td>
                                                                                                    <td><?php echo esc_html($log->user_login); ?></td>
                                                                                                    <td>
                                                                                                        <?php
                                                                                                        $action_label = $log->action_type;
                                                                                                        $bg_color = '#f0f0f1';
                                                                                                        $text_color = '#333';

                                                                                                        if (strpos($action_label, 'CREATE') !== false || strpos($action_label, 'NEW') !== false) {
                                                                                                            $bg_color = '#d4edda';
                                                                                                            $text_color = '#155724';
                                                                                                        } elseif (strpos($action_label, 'UPDATE') !== false || strpos($action_label, 'SAVE') !== false) {
                                                                                                            $bg_color = '#fff3cd';
                                                                                                            $text_color = '#856404';
                                                                                                        } elseif (strpos($action_label, 'DELETE') !== false) {
                                                                                                            $bg_color = '#f8d7da';
                                                                                                            $text_color = '#721c24';
                                                                                                        }

                                                                                                        echo '<span style="background:' . $bg_color . '; color:' . $text_color . '; padding:2px 6px; border-radius:3px; font-weight:500; font-size:11px;">' . esc_html($action_label) . '</span>';
                                                                                                        ?>
                                        </td>
                                        <td><?php echo esc_html($log->message); ?></td>
                                    </tr>
                            <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="tablenav bottom">
                <div class="alignright">
                    <?php self::render_pagination($total_items, $page, $limit, 'logs'); ?>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);

        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_get_scan_data()
    {
        ob_start();
        try {
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');

            // Allow Manager to VIEW scan data (az_view_system)
            if (!current_user_can('manage_options') && !current_user_can('az_view_system')) {
                throw new Exception('Bạn không có quyền xem dữ liệu quét.');
            }

            $page = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            // 1. Count Data Orphans (Attendance, Reviews, Teaching)
            $data_count = self::count_orphaned_data();

            // 2. Count Media Orphans (Candidates)
            // Optimization: Cache this? For now, run query.
            $media_count = self::count_orphaned_media();

            $total_items = $data_count + $media_count;
            $all_items = [];

            // Fetch Data part
            if ($offset < $data_count) {
                $data_items = self::fetch_orphaned_data($limit, $offset);
                foreach ($data_items as $item) {
                    // Normalize
                    $class_name = isset($item->class_title) && $item->class_title ? $item->class_title : 'Unknown Class';
                    $desc = '';
                    if ($item->type == 'attendance') {
                        $desc = 'Điểm danh thừa: Class <strong>' . $class_name . '</strong> (ID: ' . $item->class_id . '), Date <strong>' . $item->session_date . '</strong>';
                    } elseif ($item->type == 'review') {
                        // User Request: Detailed description for Review
                        $sess_id = isset($item->session_id) ? $item->session_id : 0;
                        $desc = 'Review thuộc buổi học #' . $sess_id . ' (Đã bị xóa)';
                    } elseif ($item->type == 'teaching_hours') {
                        $desc = 'Teaching Hour thừa: Class <strong>' . $class_name . '</strong> (ID: ' . $item->class_id . '), Date <strong>' . $item->session_date . '</strong>';
                    }

                    $all_items[] = [
                        'type' => $item->type,
                        'desc' => $desc,
                        'date' => $item->created_at,
                        'size' => '-',
                        'raw_id' => $item->id
                    ];
                }
            }

            // Fetch Media part if needed
            $fetched_count = count($all_items);
            if ($fetched_count < $limit) {
                $remaining = $limit - $fetched_count;
                $m_offset = ($offset > $data_count) ? ($offset - $data_count) : 0;

                $media_items = self::get_orphaned_media($remaining, $m_offset);
                foreach ($media_items as $item) {
                    // Normalize Media
                    $reason = isset($item->reason) ? $item->reason : 'Ảnh không có bài viết đính kèm (Parent=0)';
                    $size = isset($item->size_formatted) ? $item->size_formatted : 'N/A';
                    $preview = '';
                    if (isset($item->image_url) && $item->image_url) {
                        $ext = strtolower(pathinfo($item->image_url, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $preview = '<br><img src="' . esc_url($item->image_url) . '" style="max-width:80px; height:auto; border:1px solid #ddd; margin-top:5px; display:block;">';
                        }
                    }

                    $all_items[] = [
                        'type' => 'media',
                        'desc' => 'File: <strong>' . esc_html($item->post_title) . '</strong> (ID: ' . $item->ID . ')<br><small>' . $reason . '</small>' . $preview,
                        'date' => $item->post_date,
                        'size' => $size,
                        'raw_id' => $item->ID
                    ];
                }
            }

            ob_clean(); // Clean any previous output (warnings/notices)

            // Check capability for Delete buttons
            $can_delete = current_user_can('delete_users');

            ob_start();
            ?>
            <div class="azac-scan-tools"
                style="background:#fff; padding:15px; margin-bottom:15px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                <h3 style="margin-top:0;">Quét dọn File vật lý (Uploads)</h3>
                <p class="description">Tính năng này sẽ quét toàn bộ thư mục uploads để tìm các file không được quản lý bởi
                    WordPress Media Library. Quá trình có thể mất nhiều thời gian.</p>
                <div style="margin-top:10px;">
                    <button type="button" id="azac-start-physical-scan" class="button button-secondary">Bắt đầu quét File
                        rác</button>
                    <span id="azac-physical-scan-status" style="margin-left:10px; display:none;">
                        <span class="spinner is-active" style="float:none; margin:0 5px;"></span>
                        <span class="status-text">Đang chuẩn bị...</span>
                        <span class="progress-percent" style="font-weight:bold;">0%</span>
                    </span>
                </div>
                <div id="azac-physical-scan-progress-bar" style="height:5px; background:#f0f0f1; margin-top:10px; display:none;">
                    <div class="bar" style="height:100%; width:0%; background:#2271b1; transition: width 0.3s;"></div>
                </div>
            </div>

            <div class="azac-table-responsive" style="margin-left:0; padding:0; width:100%; box-sizing:border-box;">
                <div class="tablenav top">
                    <div class="alignright">
                        <?php self::render_pagination($total_items, $page, $limit, 'scan'); ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped" style="width:100%; table-layout:auto;">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"
                                style="width:30px; text-align:center; padding:8px 0;">
                                <?php if ($can_delete): ?>
                                                                    <input type="checkbox" id="cb-select-all-system">
                                <?php endif; ?>
                            </td>
                            <th style="width:120px;">Loại dữ liệu</th>
                            <th style="width:100px;">Dung lượng</th>
                            <th>Mô tả chi tiết</th>
                            <th style="width:150px;">Ngày phát hiện</th>
                            <?php if ($can_delete): ?>
                            <th style="width:100px; text-align:right;">Hành động</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="azac-system-tbody">
                        <?php if (empty($all_items)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="notice notice-success inline">
                                        <p>Hệ thống sạch sẽ! Không tìm thấy dữ liệu mồ côi.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_items as $item): ?>
                                                                                                                                                        <tr data-id="<?php echo esc_attr($item['raw_id']); ?>" data-type="<?php echo esc_attr($item['type']); ?>">
                                                                                                                                        <td class="check-column" style="text-align:center; vertical-align:middle;">
                                        <?php if ($can_delete): ?>
                                                                                            <input type="checkbox" class="cb-select-system" value="<?php echo esc_attr($item['type'] . '|' . $item['raw_id']); ?>">
                                        <?php endif; ?>
                                                        </td>
                                                        <td>
                                                                                <?php
                                                                                $badge_color = '#999';
                                                                                $label = $item['type'];
                                                                                switch ($item['type']) {
                                                                                    case 'media':
                                                                                        $badge_color = '#0073aa';
                                                                                        $label = 'MEDIA';
                                                                                        break;
                                                                                    case 'attendance':
                                                                                        $badge_color = '#e65100';
                                                                                        $label = 'ATTENDANCE';
                                                                                        break;
                                                                                    case 'review':
                                                                                        $badge_color = '#8e44ad';
                                                                                        $label = 'REVIEW';
                                                                                        break;
                                                                                    case 'teaching_hours':
                                                                                        $badge_color = '#d35400';
                                                                                        $label = 'TEACHING';
                                                                                        break;
                                                                                }
                                                                                echo '<span style="background:' . $badge_color . '; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; text-transform:uppercase;">' . esc_html($label) . '</span>';
                                                                                ?>
                                    </td>
                                    <td><?php echo isset($item['size']) ? esc_html($item['size']) : '-'; ?></td>
                                    <td><?php echo $item['desc']; ?></td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($item['date']))); ?></td>
                                    <?php if ($can_delete): ?>
                                    <td style="text-align:right;">
                                                                    <button type="button" class="button button-small button-link-delete azac-delete-system-item"
                                                                        data-id="<?php echo esc_attr($item['raw_id']); ?>"
                                                data-type="<?php echo esc_attr($item['type']); ?>" style="color:#a00;">Xóa vĩnh viễn</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="tablenav bottom">
                    <div class="alignright">
                        <?php self::render_pagination($total_items, $page, $limit, 'scan'); ?>
                    </div>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            $total_text = 'Tổng số: ' . number_format($total_items) . ' mục - Đang hiển thị trang ' . $page . '/' . ceil($total_items / $limit);

            wp_send_json_success(['html' => $html, 'total_text' => $total_text]);

        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }

    // --- PAGINATION HELPER ---
    private static function render_pagination($total, $current_page, $limit, $tab)
    {
        $total_pages = ceil($total / $limit);
        if ($total_pages <= 1)
            return;

        echo '<div class="azac-pagination-wrapper" data-tab="' . $tab . '">';
        echo '<span class="azac-pagination">';

        // First
        if ($current_page > 1) {
            echo '<a href="#" class="button" data-page="1">« Đầu</a> ';
            echo '<a href="#" class="button" data-page="' . ($current_page - 1) . '">‹ Trước</a> ';
        } else {
            echo '<span class="button disabled">« Đầu</span> ';
            echo '<span class="button disabled">‹ Trước</span> ';
        }

        echo '<span class="button disabled">Trang ' . $current_page . ' / ' . $total_pages . '</span> ';

        // Next
        if ($current_page < $total_pages) {
            echo '<a href="#" class="button" data-page="' . ($current_page + 1) . '">Sau ›</a> ';
            echo '<a href="#" class="button" data-page="' . $total_pages . '">Cuối »</a>';
        } else {
            echo '<span class="button disabled">Sau ›</span> ';
            echo '<span class="button disabled">Cuối »</span>';
        }

        echo '</span>';
        echo '</div>';
    }

    // --- COUNT & FETCH HELPERS ---

    private static function count_orphaned_data()
    {
        global $wpdb;
        $count = 0;
        $posts = $wpdb->posts;
        $sess_table = $wpdb->prefix . 'az_sessions';

        // Scope Limit: Only scan last 6 months as requested to reduce load
        $date_limit = date('Y-m-d', strtotime('-6 months'));

        // Helper to build WHERE
        $build_where = function ($table) use ($wpdb, $posts, $sess_table, $date_limit) {
            // Check if session_id exists
            $has_session_id = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'session_id'");

            // Base condition: Class ID must exist
            $where = "(NOT EXISTS (SELECT 1 FROM $posts p WHERE p.ID = $table.class_id))";

            if ($has_session_id) {
                // User Request: session_id points to wp_posts (orphaned if not in wp_posts)
                $where .= " OR ($table.session_id > 0 AND NOT EXISTS (SELECT 1 FROM $posts p WHERE p.ID = $table.session_id))";
            } else {
                // Fallback: Link via session_date to az_sessions
                $where .= " OR ($table.session_date != '0000-00-00' AND NOT EXISTS (SELECT 1 FROM $sess_table s WHERE s.class_id = $table.class_id AND s.session_date = $table.session_date))";
            }

            // Apply Date Scope Limit if session_date column exists (most tables have it)
            // If not, we might check created_at, but session_date is safer for business logic
            $where = "($where) AND $table.session_date >= '$date_limit'";

            return $where;
        };

        // 1. Attendance
        $att_table = $wpdb->prefix . 'az_attendance';
        if ($wpdb->get_var("SHOW TABLES LIKE '$att_table'") == $att_table) {
            $where = $build_where($att_table);
            $count += $wpdb->get_var("SELECT COUNT(id) FROM $att_table WHERE $where");
        }

        // 2. Reviews (Check BOTH az_reviews and az_feedback)
        $review_tables = [$wpdb->prefix . 'az_reviews', $wpdb->prefix . 'az_feedback'];
        foreach ($review_tables as $rev_table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$rev_table'") == $rev_table) {
                $where = $build_where($rev_table);
                $count += $wpdb->get_var("SELECT COUNT(id) FROM $rev_table WHERE $where");
            }
        }

        // 3. Teaching
        $tea_table = $wpdb->prefix . 'az_teaching_hours';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tea_table'") == $tea_table) {
            $where = $build_where($tea_table);
            $count += $wpdb->get_var("SELECT COUNT(id) FROM $tea_table WHERE $where");
        }

        return $count;
    }

    private static function count_orphaned_media()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} a WHERE post_type = 'attachment' AND (post_parent = 0 OR NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = a.post_parent))");
    }

    private static function fetch_orphaned_data($limit, $offset)
    {
        global $wpdb;
        $posts = $wpdb->posts;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $parts = [];

        // Scope Limit: Only scan last 6 months
        $date_limit = date('Y-m-d', strtotime('-6 months'));

        // Helper to build Query Part
        $build_query = function ($type, $table) use ($wpdb, $posts, $sess_table, $date_limit) {
            $has_session_id = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'session_id'");

            $cols = "SELECT '$type' as type, id, created_at, class_id, session_date, ";
            $cols .= ($has_session_id ? "session_id" : "0 as session_id") . ", ";
            $cols .= "(SELECT post_title FROM $posts WHERE ID = $table.class_id) as class_title ";

            $where = "(NOT EXISTS (SELECT 1 FROM $posts p WHERE p.ID = $table.class_id))";

            if ($has_session_id) {
                $where .= " OR ($table.session_id > 0 AND NOT EXISTS (SELECT 1 FROM $posts p WHERE p.ID = $table.session_id))";
            } else {
                $where .= " OR ($table.session_date != '0000-00-00' AND NOT EXISTS (SELECT 1 FROM $sess_table s WHERE s.class_id = $table.class_id AND s.session_date = $table.session_date))";
            }

            // Apply Date Scope Limit
            $where = "($where) AND $table.session_date >= '$date_limit'";

            return "$cols FROM $table WHERE $where";
        };

        // 1. Attendance
        $att_table = $wpdb->prefix . 'az_attendance';
        if ($wpdb->get_var("SHOW TABLES LIKE '$att_table'") == $att_table) {
            $parts[] = $build_query('attendance', $att_table);
        }

        // 2. Reviews (Check BOTH)
        $review_tables = [$wpdb->prefix . 'az_reviews', $wpdb->prefix . 'az_feedback'];
        foreach ($review_tables as $rev_table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$rev_table'") == $rev_table) {
                $parts[] = $build_query('review', $rev_table);
            }
        }

        // 3. Teaching
        $tea_table = $wpdb->prefix . 'az_teaching_hours';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tea_table'") == $tea_table) {
            $parts[] = $build_query('teaching_hours', $tea_table);
        }

        if (empty($parts))
            return [];

        $sql = implode(" UNION ALL ", $parts);
        $sql .= " LIMIT $limit OFFSET $offset";

        return $wpdb->get_results($sql);
    }


    private static function get_orphaned_media($limit = 20, $offset = 0)
    {
        global $wpdb;

        // 1. Get Candidates (Attachment with parent=0 or invalid parent)
        // Optimization: Use NOT EXISTS
        $sql = "SELECT ID, post_title, post_parent, post_date 
            FROM {$wpdb->posts} a
            WHERE post_type = 'attachment' 
            AND (post_parent = 0 OR NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = a.post_parent)) 
            LIMIT %d OFFSET %d";

        $sql = $wpdb->prepare($sql, $limit, $offset);
        $candidates = $wpdb->get_results($sql);

        if (empty($candidates))
            return [];

        // 2. Optimized Usage Check (Batch Check instead of Big String)
        $whitelist = ['logo', 'favicon', 'default-avatar', 'avatar', 'placeholder', 'site_icon'];
        $filtered = [];
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

        // Prepare IDs for efficient SQL querying
        $candidate_ids = [];
        foreach ($candidates as $c) {
            $candidate_ids[] = $c->ID;
        }

        // 2.1 Check usage in meta/options by ID (Exact Match)
        if (!empty($candidate_ids)) {
            $ids_str = implode(',', array_map('intval', $candidate_ids));

            // Check Post Meta (ID usage)
            $used_in_postmeta = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_value IN ($ids_str)");

            // Check User Meta (ID usage)
            $used_in_usermeta = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_value IN ($ids_str)");

            // Check Options (ID usage)
            $used_in_options = $wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_value IN ($ids_str)");

            $used_ids = array_merge($used_in_postmeta, $used_in_usermeta, $used_in_options);
            $used_ids = array_flip($used_ids);
        } else {
            $used_ids = [];
        }

        foreach ($candidates as $c) {
            // Check 1: ID Usage (Direct)
            if (isset($used_ids[$c->ID]))
                continue;

            $file_path = get_attached_file($c->ID);
            if (!$file_path || !file_exists($file_path))
                continue;

            $filename = wp_basename($file_path);
            $file_url = wp_get_attachment_url($c->ID);

            // Normalize relative path
            $relative_path = str_replace($base_url, '', $file_url);

            // Check 2: Whitelist
            $is_safe = false;
            foreach ($whitelist as $kw) {
                if (stripos($filename, $kw) !== false) {
                    $is_safe = true;
                    break;
                }
            }
            if ($is_safe)
                continue;

            // Check 3: SQL LIKE Scan (Targeted)
            // Instead of loading all content, we query if THIS specific file is used.
            // This is safer for memory but multiple queries. Since batch is small (20), it's acceptable.

            // We search for:
            // 1. Filename (most common in content)
            // 2. File URL (absolute)
            // 3. Relative Path
            // 4. Encoded URL (JSON)

            $escaped_url = str_replace('/', '\\/', $file_url); // simple escape for JSON-like string check

            // Construct a single query to check usage in active posts
            // Limit 1 is enough to prove usage
            // Optimization: Limit post_type as requested
            $usage_sql = $wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_status NOT IN ('trash', 'auto-draft') 
                AND post_type IN ('post', 'page', 'az_class', 'product', 'az_lesson')
                AND (
                    post_content LIKE %s 
                    OR post_content LIKE %s 
                    OR post_content LIKE %s
                )
                LIMIT 1
            ", '%' . $wpdb->esc_like($filename) . '%', '%' . $wpdb->esc_like($relative_path) . '%', '%' . $wpdb->esc_like($escaped_url) . '%');

            $found_in_content = $wpdb->get_var($usage_sql);
            if ($found_in_content)
                continue;

            // Check Post Meta (String usage - e.g. Elementor Data, JSON)
            // Excluding standard keys to save time
            $meta_usage_sql = $wpdb->prepare("
                SELECT meta_id FROM {$wpdb->postmeta} 
                WHERE meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata')
                AND (
                    meta_value LIKE %s 
                    OR meta_value LIKE %s
                )
                LIMIT 1
            ", '%' . $wpdb->esc_like($filename) . '%', '%' . $wpdb->esc_like($relative_path) . '%');

            $found_in_meta = $wpdb->get_var($meta_usage_sql);
            if ($found_in_meta)
                continue;

            // Check 4: Thumbnail Sizes (New)
            // Ensures that if a resized version is used, the original is not marked as orphan
            $metadata = wp_get_attachment_metadata($c->ID);
            $is_thumb_used = false;

            if ($metadata && isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_info) {
                    if (isset($size_info['file'])) {
                        $thumb_file = $size_info['file']; // e.g. image-150x150.jpg

                        // Check if thumb is used in content
                        // Optimization: Limit post_type
                        $thumb_usage_sql = $wpdb->prepare("
                            SELECT ID FROM {$wpdb->posts} 
                            WHERE post_status NOT IN ('trash', 'auto-draft') 
                            AND post_type IN ('post', 'page', 'az_class', 'product', 'az_lesson')
                            AND post_content LIKE %s 
                            LIMIT 1
                        ", '%' . $wpdb->esc_like($thumb_file) . '%');

                        if ($wpdb->get_var($thumb_usage_sql)) {
                            $is_thumb_used = true;
                            break;
                        }
                    }
                }
            }

            if ($is_thumb_used)
                continue;

            // Found Trash
            $c->size_formatted = size_format(filesize($file_path));
            $c->image_url = $file_url;
            $c->reason = 'Không tìm thấy ID/URL (bao gồm cả thumbnail) trong Content, Meta, Options hay User Data.';
            $filtered[] = $c;
        }

        return $filtered;
    }

    private static function get_orphaned_reviews()
    {
        global $wpdb;
        $feedback_table = $wpdb->prefix . 'az_feedback';
        $sess_table = $wpdb->prefix . 'az_sessions';

        // Check tables exist
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $feedback_table)) !== $feedback_table) {
            return [];
        }

        // Find reviews where (class_id, session_date) pair is NOT in sessions table
        // OR Class is deleted

        $orphaned = $wpdb->get_results("
            SELECT f.*, p.post_title as class_title
            FROM $feedback_table f 
            LEFT JOIN {$wpdb->posts} p ON f.class_id = p.ID
            LEFT JOIN $sess_table s ON f.class_id = s.class_id AND f.session_date = s.session_date
            WHERE p.ID IS NULL OR s.id IS NULL
            LIMIT 100
        ");

        $items = [];
        foreach ($orphaned as $o) {
            $class_name = $o->class_title ? $o->class_title : 'Unknown Class';
            $o->type = 'review';
            $o->reason = 'Review thuộc buổi học (Class: <strong>' . $class_name . '</strong>, Date: ' . $o->session_date . ') đã bị xóa.';
            $items[] = $o;
        }
        return $items;
    }

    private static function get_orphaned_teaching_hours()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'az_teaching_hours';
        $sess_table = $wpdb->prefix . 'az_sessions';

        // Check table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        $orphaned = $wpdb->get_results("
            SELECT t.*, p.post_title as class_title
            FROM $table t 
            LEFT JOIN {$wpdb->posts} p ON t.class_id = p.ID
            LEFT JOIN $sess_table s ON t.class_id = s.class_id AND t.session_date = s.session_date
            WHERE p.ID IS NULL OR s.id IS NULL
            LIMIT 100
        ");

        $items = [];
        foreach ($orphaned as $o) {
            $class_name = $o->class_title ? $o->class_title : 'Unknown Class';
            $o->type = 'teaching_hours'; // Custom type
            $o->reason = 'Giờ dạy thuộc buổi học (Class: <strong>' . $class_name . '</strong>, Date: ' . $o->session_date . ') đã bị xóa.';
            $items[] = $o;
        }
        return $items;
    }

    private static function get_unregistered_files_in_folder($subfolder)
    {
        global $wpdb;
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        $target_dir = $subfolder ? $base_dir . '/' . $subfolder : $base_dir;

        if (!is_dir($target_dir))
            return [];

        // Get files in this folder (non-recursive)
        $files = scandir($target_dir);
        $physical_files = [];
        foreach ($files as $f) {
            if ($f == '.' || $f == '..')
                continue;
            if (is_dir($target_dir . '/' . $f))
                continue; // Skip subdirs

            // Skip system files
            if ($f == 'index.php' || $f == '.htaccess')
                continue;

            // Skip elementor/wc-logs if in root
            if (!$subfolder && (strpos($f, 'elementor') !== false || strpos($f, 'wc-logs') !== false))
                continue;

            $rel_path = $subfolder ? $subfolder . '/' . $f : $f;
            $physical_files[] = $rel_path;
        }

        if (empty($physical_files))
            return [];

        // Get DB files that match this folder
        if ($subfolder) {
            $like = $wpdb->esc_like($subfolder) . '/%';
            $db_files = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
                $like
            ));
        } else {
            // Root folder: files that do NOT contain '/'
            $db_files = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value NOT LIKE '%/%'");
        }

        // Compare
        $orphans = [];
        foreach ($physical_files as $p_file) {
            // Check if in DB
            if (!in_array($p_file, $db_files)) {
                // Check thumbnail
                if (!self::is_thumbnail_of_existing_file($p_file, $db_files)) {
                    $orphans[] = (object) [
                        'type' => 'physical_file',
                        'post_title' => $p_file, // reused property
                        'reason' => 'File vật lý không có trong DB',
                        'post_date' => date("Y-m-d H:i:s", filemtime($target_dir . '/' . basename($p_file))),
                        'size_formatted' => size_format(filesize($target_dir . '/' . basename($p_file))),
                        'image_url' => $upload_dir['baseurl'] . '/' . $p_file,
                        'raw_id' => 'phys_' . base64_encode($p_file)
                    ];
                }
            }
        }
        return $orphans;
    }

    public static function ajax_init_physical_scan()
    {
        ob_start();
        try {
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                throw new Exception('Forbidden');

            $upload_dir = wp_upload_dir();
            $base = $upload_dir['basedir'];
            $folders = [];

            // Add root (empty string)
            $folders[] = '';

            // Add Years/Months
            $years = glob($base . '/*', GLOB_ONLYDIR);
            if ($years) {
                foreach ($years as $year_path) {
                    $year = basename($year_path);
                    if (!is_numeric($year))
                        continue;

                    $months = glob($year_path . '/*', GLOB_ONLYDIR);
                    if ($months) {
                        foreach ($months as $month_path) {
                            $month = basename($month_path);
                            if (!is_numeric($month))
                                continue;
                            $folders[] = $year . '/' . $month;
                        }
                    }
                }
            }

            ob_clean();
            wp_send_json_success(['folders' => $folders, 'total' => count($folders)]);
        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_scan_physical_folder()
    {
        ob_start();
        try {
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');
            if (!current_user_can('manage_options'))
                throw new Exception('Forbidden');

            $folder = isset($_POST['folder']) ? sanitize_text_field($_POST['folder']) : '';
            $orphans = self::get_unregistered_files_in_folder($folder);

            // Format for JS
            $formatted = [];
            foreach ($orphans as $item) {
                $preview = '';
                if (isset($item->image_url) && $item->image_url) {
                    $ext = strtolower(pathinfo($item->image_url, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $preview = '<br><img src="' . esc_url($item->image_url) . '" style="max-width:80px; height:auto; border:1px solid #ddd; margin-top:5px; display:block;">';
                    }
                }

                $formatted[] = [
                    'type' => 'physical_file',
                    'desc' => 'File: <strong>' . esc_html($item->post_title) . '</strong><br><small>' . $item->reason . '</small>' . $preview,
                    'date' => $item->post_date,
                    'size' => $item->size_formatted,
                    'raw_id' => $item->raw_id
                ];
            }

            ob_clean();
            wp_send_json_success(['orphans' => $formatted]);
        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }

    // Hàm phụ để kiểm tra xem file có phải là thumbnail của một ảnh đang dùng không 
    private static function is_thumbnail_of_existing_file($file_path, $all_db_files)
    {
        // Regex tìm pattern thumbnail của WP (ví dụ: image-150x150.jpg) 
        if (preg_match('/-(?:\d+x\d+)\.(?:jpg|jpeg|png|gif|webp)$/i', $file_path)) {
            $original_path = preg_replace('/-(?:\d+x\d+)\./i', '.', $file_path);
            // Check if original path exists in DB files
            // Note: $all_db_files contains relative paths like '2024/01/image.jpg'
            if (in_array($original_path, $all_db_files)) {
                return true;
            }
        }
        return false;
    }

    private static function get_orphaned_attendance()
    {
        global $wpdb;
        $att_table = $wpdb->prefix . 'az_attendance';
        $sess_table = $wpdb->prefix . 'az_sessions';

        // Check if session table exists first to avoid errors
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sess_table)) !== $sess_table) {
            return [];
        }

        // Find attendance records where Class is deleted OR Session is deleted
        // Join wp_posts to get Class Title
        return $wpdb->get_results("
            SELECT a.*, p.post_title as class_title
            FROM $att_table a 
            LEFT JOIN {$wpdb->posts} p ON a.class_id = p.ID
            LEFT JOIN $sess_table s ON a.class_id = s.class_id AND a.session_date = s.session_date
            WHERE p.ID IS NULL 
               OR (s.id IS NULL AND a.session_date != '0000-00-00')
            LIMIT 100
        ");
    }

    private static function get_orphaned_meta()
    {
        global $wpdb;
        return $wpdb->get_results("
            SELECT meta_id, post_id, meta_key 
            FROM {$wpdb->postmeta} 
            WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})
            LIMIT 100
        ");
    }

    public static function ajax_cleanup()
    {
        ob_start();
        try {
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                throw new Exception('Bạn không có quyền thực hiện hành động này.');
            }

            $items = isset($_POST['items']) ? $_POST['items'] : [];
            if (empty($items) || !is_array($items)) {
                throw new Exception('Không có dữ liệu để xử lý.');
            }

            global $wpdb;
            $count_media = 0;
            $count_attendance = 0;
            $count_reviews = 0;
            $count_teaching = 0;
            $count_meta = 0;
            $count_physical = 0;

            foreach ($items as $item) {
                // format: "type|id"
                $parts = explode('|', $item);
                if (count($parts) !== 2)
                    continue;

                $type = $parts[0];
                $id = $parts[1];

                if ($type === 'media') {
                    if (wp_delete_attachment((int) $id, true)) {
                        $count_media++;
                    }
                } elseif ($type === 'attendance') {
                    if ($wpdb->delete($wpdb->prefix . 'az_attendance', ['id' => (int) $id], ['%d'])) {
                        $count_attendance++;
                    }
                } elseif ($type === 'physical_file') {
                    $encoded_path = str_replace('phys_', '', $id);
                    $relative_path = base64_decode($encoded_path);
                    $upload_dir = wp_upload_dir();
                    $full_path = wp_normalize_path($upload_dir['basedir'] . '/' . $relative_path);

                    // Security check: ensure path is within uploads directory
                    if (strpos($full_path, wp_normalize_path($upload_dir['basedir'])) === 0 && file_exists($full_path)) {
                        @unlink($full_path);
                        $count_physical++;
                    }
                } elseif ($type === 'review') {
                    $deleted_review = false;
                    // Try az_reviews
                    $t1 = $wpdb->prefix . 'az_reviews';
                    if ($wpdb->get_var("SHOW TABLES LIKE '$t1'") == $t1) {
                        if ($wpdb->delete($t1, ['id' => $id], ['%d']))
                            $deleted_review = true;
                    }
                    // Try az_feedback
                    $t2 = $wpdb->prefix . 'az_feedback';
                    if ($wpdb->get_var("SHOW TABLES LIKE '$t2'") == $t2) {
                        if ($wpdb->delete($t2, ['id' => $id], ['%d']))
                            $deleted_review = true;
                    }

                    if ($deleted_review) {
                        $count_reviews++;
                    }
                } elseif ($type === 'teaching_hours') {
                    if ($wpdb->delete($wpdb->prefix . 'az_teaching_hours', ['id' => $id], ['%d'])) {
                        $count_teaching++;
                    }
                } elseif ($type === 'meta') {
                    if ($wpdb->delete($wpdb->postmeta, ['meta_id' => $id], ['%d'])) {
                        $count_meta++;
                    }
                }
            }

            // Log
            $total = $count_media + $count_attendance + $count_reviews + $count_meta + $count_physical;
            if ($total > 0) {
                $user = wp_get_current_user();
                $msg = "Đã xóa $total mục mồ côi (Media: $count_media, Physical: $count_physical, Attendance: $count_attendance, Reviews: $count_reviews, Meta: $count_meta).";
                self::log('SYSTEM_CLEANUP', $msg, $user->ID);
            }

            ob_clean();
            wp_send_json_success([
                'message' => "Đã dọn dẹp $total mục thành công.",
                'counts' => [
                    'media' => $count_media,
                    'physical' => $count_physical,
                    'attendance' => $count_attendance,
                    'reviews' => $count_reviews,
                    'meta' => $count_meta
                ]
            ]);

        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_cleanup_logs()
    {
        ob_start();
        try {
            // Fix 403: Use the main system nonce as sent by JS
            check_ajax_referer('azac_system_cleanup_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                throw new Exception('Bạn không có quyền thực hiện hành động này.');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'az_system_logs';
            $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : '';
            $items = isset($_POST['items']) ? $_POST['items'] : []; // Array of IDs now

            $deleted = 0;

            if ($mode === 'delete_all') {
                $deleted = $wpdb->query("TRUNCATE TABLE $table_name");
            } elseif ($mode === 'older_30') {
                $deleted = $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            } elseif ($mode === 'delete_selected') {
                if (empty($items)) {
                    throw new Exception('Chưa chọn nhật ký nào.');
                }

                $ids = array_map('intval', $items);
                $ids_str = implode(',', $ids);

                if (!empty($ids_str)) {
                    $deleted = $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
                }
            } else {
                throw new Exception('Chế độ không hợp lệ.');
            }

            ob_clean();
            wp_send_json_success([
                'message' => "Đã xóa dữ liệu nhật ký.",
                'deleted_count' => $deleted
            ]);

        } catch (Exception $e) {
            ob_clean();
            wp_send_json_error($e->getMessage());
        }
    }
}
