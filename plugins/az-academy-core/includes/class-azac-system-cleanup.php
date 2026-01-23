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

        // Log Cleanup Hook
        add_action('wp_ajax_azac_cleanup_logs', [__CLASS__, 'ajax_cleanup_logs']);
    }

    // --- LOGGING HELPERS ---
    public static function log($action, $message, $user_id = null)
    {
        if (!$user_id)
            $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        $username = $user ? $user->user_login : 'System';

        $entry = [
            'time' => current_time('mysql'),
            'user' => $username,
            'action' => $action,
            'message' => $message
        ];

        $logs = get_option('azac_system_logs', []);
        if (!is_array($logs))
            $logs = [];
        array_unshift($logs, $entry); // Add to beginning

        if (count($logs) > 500) {
            $logs = array_slice($logs, 0, 500);
        }

        update_option('azac_system_logs', $logs);
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

    public static function register_menu()
    {
        add_menu_page(
            'Hệ thống',
            'Hệ thống',
            'manage_options', // Only Administrator
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

        // Fetch Data if on Scan tab
        $orphaned_media = [];
        $orphaned_attendance = [];
        $orphaned_reviews = [];
        $orphaned_meta = [];
        $orphaned_teaching = [];
        $logs = [];

        if ($active_tab === 'scan') {
            $orphaned_media = self::get_orphaned_media();
            $orphaned_attendance = self::get_orphaned_attendance();
            $orphaned_reviews = self::get_orphaned_reviews();
            $orphaned_meta = self::get_orphaned_meta();
            $orphaned_teaching = self::get_orphaned_teaching_hours();
        } else {
            $logs = get_option('azac_system_logs', []);
            if (!is_array($logs))
                $logs = [];
            // $logs are stored Newest First (unshift). We keep that order.
        }

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
                    <?php self::render_scan_tab($orphaned_media, $orphaned_attendance, $orphaned_reviews, $orphaned_meta, $orphaned_teaching); ?>
                <?php else: ?>
                    <?php self::render_logs_tab($logs); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_scan_tab($media, $attendance, $reviews, $meta, $teaching = [])
    {
        $all_items = [];

        foreach ($media as $item) {
            $reason = isset($item->reason) ? $item->reason : 'Ảnh không có bài viết đính kèm (Parent=0)';
            $size = isset($item->size_formatted) ? $item->size_formatted : 'N/A';

            // Preview Image
            $preview = '';
            if (isset($item->image_url) && $item->image_url) {
                $ext = strtolower(pathinfo($item->image_url, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $preview = '<br><img src="' . esc_url($item->image_url) . '" style="max-width:80px; height:auto; border:1px solid #ddd; margin-top:5px; display:block;">';
                }
            }

            $all_items[] = [
                'type' => 'media',
                'id' => $item->ID,
                'desc' => 'File: <strong>' . esc_html($item->post_title) . '</strong> (ID: ' . $item->ID . ')<br><small>' . $reason . '</small>' . $preview,
                'date' => $item->post_date,
                'size' => $size,
                'raw_id' => $item->ID
            ];
        }

        foreach ($attendance as $item) {
            $class_name = isset($item->class_title) && $item->class_title ? $item->class_title : 'Unknown Class';
            $all_items[] = [
                'type' => 'attendance',
                'id' => $item->id, // attendance ID
                'desc' => 'Điểm danh thừa: Class <strong>' . $class_name . '</strong> (ID: ' . $item->class_id . '), Date <strong>' . $item->session_date . '</strong>',
                'date' => isset($item->created_at) ? $item->created_at : 'N/A',
                'raw_id' => $item->id
            ];
        }

        foreach ($reviews as $item) {
            $all_items[] = [
                'type' => 'review',
                'id' => $item->id, // review ID
                'desc' => 'Review thuộc buổi học ID <strong>#' . esc_html($item->class_id) . '</strong> ngày <strong>' . esc_html($item->session_date) . '</strong> (Đã bị xóa)',
                'date' => isset($item->created_at) ? $item->created_at : 'N/A',
                'raw_id' => $item->id
            ];
        }

        foreach ($teaching as $item) {
            $all_items[] = [
                'type' => 'teaching_hours',
                'id' => $item->id,
                'desc' => 'Teaching Hour mồ côi: Class ID ' . $item->class_id . ', Date ' . $item->session_date,
                'date' => isset($item->created_at) ? $item->created_at : 'N/A',
                'raw_id' => $item->id
            ];
        }

        foreach ($meta as $item) {
            $all_items[] = [
                'type' => 'meta',
                'id' => $item->meta_id,
                'desc' => 'Meta thừa: Key <strong>' . esc_html($item->meta_key) . '</strong> (Post ID: ' . $item->post_id . ' không tồn tại)',
                'date' => 'N/A',
                'raw_id' => $item->meta_id
            ];
        }

        $physical_files = self::get_unregistered_physical_files();
        foreach ($physical_files as $item) {
            $preview = '';
            if (isset($item->image_url) && $item->image_url) {
                $ext = strtolower(pathinfo($item->image_url, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $preview = '<br><img src="' . esc_url($item->image_url) . '" style="max-width:80px; height:auto; border:1px solid #ddd; margin-top:5px; display:block;">';
                }
            }

            $all_items[] = [
                'type' => 'physical_file',
                'id' => 0, // No DB ID
                'desc' => 'File Rác Vật Lý: <strong>' . esc_html($item->post_title) . '</strong><br><small>' . $item->reason . '</small>' . $preview,
                'date' => $item->post_date,
                'size' => $item->size_formatted,
                'raw_id' => $item->raw_id // Encoded path
            ];
        }

        if (empty($all_items)) {
            echo '<div class="notice notice-success inline"><p>Hệ thống sạch sẽ! Không tìm thấy dữ liệu mồ côi.</p></div>';
            return;
        }

        ?>
        <div class="azac-layout-wrapper"
            style="background:#fff; padding:20px; border:1px solid #c3c4c7; box-sizing:border-box;">
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
                    <strong>Tổng số: <?php echo count($all_items); ?> mục</strong>
                </div>
            </div>

            <div class="azac-table-responsive" style="margin-left:0; padding:0; width:100%; box-sizing:border-box;">
                <table class="wp-list-table widefat fixed striped" style="width:100%; table-layout:auto;">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"
                                style="width:30px; text-align:center; padding:8px 0;"><input type="checkbox"
                                    id="cb-select-all-system"></td>
                            <th style="width:120px;">Loại dữ liệu</th>
                            <th style="width:100px;">Dung lượng</th>
                            <th>Mô tả chi tiết</th>
                            <th style="width:150px;">Ngày phát hiện</th>
                            <th style="width:100px; text-align:right;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="azac-system-tbody">
                        <?php foreach ($all_items as $item): ?>
                            <tr data-id="<?php echo esc_attr($item['raw_id']); ?>"
                                data-type="<?php echo esc_attr($item['type']); ?>">
                                <td class="check-column" style="text-align:center; vertical-align:middle;">
                                    <input type="checkbox" class="cb-select-system"
                                        value="<?php echo esc_attr($item['type'] . '|' . $item['raw_id']); ?>">
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
                                        case 'meta':
                                            $badge_color = '#666';
                                            $label = 'META';
                                            break;
                                        case 'physical_file':
                                            $badge_color = '#c0392b';
                                            $label = 'FILE RÁC';
                                            break;
                                    }

                                    echo '<span style="background:' . $badge_color . '; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; text-transform:uppercase;">' . esc_html($label) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo isset($item['size']) ? esc_html($item['size']) : '-'; ?></td>
                                <td><?php echo $item['desc']; ?></td>
                                <td><?php echo esc_html($item['date']); ?></td>
                                <td style="text-align:right;">
                                    <button type="button" class="button button-small button-link-delete azac-delete-system-item"
                                        data-id="<?php echo esc_attr($item['raw_id']); ?>"
                                        data-type="<?php echo esc_attr($item['type']); ?>" style="color:#a00;">Xóa vĩnh
                                        viễn</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private static function render_logs_tab($logs)
    {
        if (empty($logs)) {
            echo '<p>Chưa có nhật ký nào.</p>';
            return;
        }
        ?>
                                                                <div class="tablenav top">
                                                                    <div class="alignleft actions bulkactions">
                                                                        <select id="azac-log-bulk-action">
                                                                            <option value="-1">Hành động hàng loạt</option>
                                                                            <option value="delete_selected">Xóa đã chọn</option>
                                                                            <option value="older_30">Xóa cũ hơn 30 ngày</option>
                                                                            <option value="delete_all">Xóa toàn bộ nhật ký</option>
                                                                        </select>
                                                                        <button type="button" id="azac-do-log-cleanup" class="button action">Áp dụng</button>
                                                                    </div>
                                                                    <div class="alignright">
                                                                        <strong>Tổng số:
                                                                    <?php echo count($logs); ?> dòng
                                                                </strong>
                                                            </div>
                                                        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-logs"></td>
                    <th style="width:180px;">Thời gian</th>
                    <th style="width:150px;">User thực hiện</th>
                    <th style="width:150px;">Loại hành động</th>
                    <th>Nội dung chi tiết</th>
                </tr>
            </thead>
            <tbody id="azac-logs-tbody">
                <?php foreach ($logs as $index => $log):
                    // Create a unique hash for identification
                    $log_hash = md5($log['time'] . $log['user'] . (isset($log['message']) ? $log['message'] : ''));
                    ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" class="cb-select-log" value="<?php echo esc_attr($log_hash); ?>">
                        </th>
                        <td><?php echo esc_html($log['time']); ?></td>
                        <td><?php echo esc_html($log['user']); ?></td>
                        <td>
                            <?php
                                $action_label = isset($log['action']) ? $log['action'] : 'INFO';
                                $bg_color = '#f0f0f1';
                                $text_color = '#333';

                                if (strpos($action_label, 'CREATE') !== false || strpos($action_label, 'NEW') !== false) {
                                    $bg_color = '#d4edda';
                                    $text_color = '#155724'; // Green
                                } elseif (strpos($action_label, 'UPDATE') !== false || strpos($action_label, 'SAVE') !== false) {
                                    $bg_color = '#fff3cd';
                                    $text_color = '#856404'; // Yellow
                                } elseif (strpos($action_label, 'DELETE') !== false) {
                                    $bg_color = '#f8d7da';
                                    $text_color = '#721c24'; // Red
                                }

                                echo '<span style="background:' . $bg_color . '; color:' . $text_color . '; padding:2px 6px; border-radius:3px; font-weight:500; font-size:11px;">' . esc_html($action_label) . '</span>';
                                ?>
                        </td>
                        <td><?php echo esc_html($log['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            // Select All
            $('#cb-select-all-logs').on('change', function() {
                $('.cb-select-log').prop('checked', $(this).prop('checked'));
            });

            // Bulk Action
            $('#azac-do-log-cleanup').on('click', function() {
                var action = $('#azac-log-bulk-action').val();
                if(action === '-1') return;

                var selected = [];
                if(action === 'delete_selected') {
                    $('.cb-select-log:checked').each(function() {
                        selected.push($(this).val());
                    });
                    if(selected.length === 0) {
                        alert('Vui lòng chọn ít nhất một dòng.');
                        return;
                    }
                }

                if(!confirm(AZAC_SYSTEM.confirmLogDelete)) return;

                var data = {
                    action: 'azac_cleanup_logs',
                    nonce: AZAC_SYSTEM.cleanupLogsNonce,
                    mode: action,
                    items: selected
                };

                var $btn = $(this);
                $btn.prop('disabled', true).text('Đang xử lý...');

                $.post(AZAC_SYSTEM.ajaxUrl, data, function(res) {
                    if(res.success) {
                        alert(res.data.message);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (res.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Áp dụng');
                    }
                });
            });
        });
        </script>
        <?php
    }

    private static function get_orphaned_media()
    {
        global $wpdb;

        // 1. Get Candidates (Attachment with parent=0 or invalid parent)
        $candidates = $wpdb->get_results(" 
            SELECT ID, post_title, post_parent, post_date 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND (post_parent = 0 OR post_parent NOT IN (SELECT ID FROM {$wpdb->posts})) 
            LIMIT 2000 
        ");

        if (empty($candidates))
            return [];

        // 2. Pre-fetch ALL Possible IDs (Numeric) for O(1) Lookup
        // 2.1 Postmeta IDs
        $meta_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_value REGEXP '^[0-9]+$'
        ");

        // 2.2 Usermeta IDs (Avatar, etc)
        $usermeta_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value FROM {$wpdb->usermeta} 
            WHERE meta_value REGEXP '^[0-9]+$'
        ");

        // 2.3 Options IDs (Site Icon, Logo, etc)
        $option_ids = $wpdb->get_col("
            SELECT option_value FROM {$wpdb->options} 
            WHERE option_value REGEXP '^[0-9]+$'
        ");

        $used_ids = array_flip(array_merge($meta_ids, $usermeta_ids, $option_ids));
        unset($meta_ids, $usermeta_ids, $option_ids);

        // 3. Pre-fetch ALL Content/Meta Strings for RAM Scan
        // We search in: Post Content, Post Meta, User Meta, Options
        // Filter to reduce memory usage: only fetch strings that might contain URLs, JSON, or Image extensions

        // 3.1 Post Content (All types)
        $content_data = $wpdb->get_col("
            SELECT post_content FROM {$wpdb->posts} 
            WHERE post_status != 'auto-draft' 
            AND post_type != 'attachment'
        ");

        // 3.2 Post Meta (Strings, JSON, Serialized)
        // EXCLUDE _wp_attached_file and _wp_attachment_metadata to avoid self-matching
        $postmeta_data = $wpdb->get_col("
            SELECT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata')
            AND (
                meta_value LIKE '%http%' 
                OR meta_value LIKE '%{%' 
                OR meta_value LIKE '%.jpg%' OR meta_value LIKE '%.jpeg%' 
                OR meta_value LIKE '%.png%' OR meta_value LIKE '%.gif%' 
                OR meta_value LIKE '%.webp%'
                OR meta_value REGEXP '^[0-9,]+$' -- Comma separated IDs like product gallery
            )
        ");

        // 3.3 User Meta (Avatar URLs, etc)
        $usermeta_data = $wpdb->get_col("
            SELECT meta_value FROM {$wpdb->usermeta} 
            WHERE meta_value LIKE '%http%' 
            OR meta_value LIKE '%{%' 
            OR meta_value LIKE '%.jpg%' OR meta_value LIKE '%.jpeg%' 
            OR meta_value LIKE '%.png%'
        ");

        // 3.4 Options (Theme mods, serialized data)
        $option_data = $wpdb->get_col("
            SELECT option_value FROM {$wpdb->options} 
            WHERE option_name NOT LIKE '_transient%' 
            AND option_name NOT LIKE '_site_transient%'
            AND (
                option_value LIKE '%http%' 
                OR option_value LIKE '%{%' 
                OR option_value LIKE '%.jpg%' OR option_value LIKE '%.png%'
            )
        ");

        // Combine into one Big String
        // Note: In extremely large DBs, this might hit memory limit. 
        // For standard hosting (128MB+), text data usually fits unless millions of posts.
        $big_string = implode(' ', $content_data) . ' '
            . implode(' ', $postmeta_data) . ' '
            . implode(' ', $usermeta_data) . ' '
            . implode(' ', $option_data);

        unset($content_data, $postmeta_data, $usermeta_data, $option_data);

        $whitelist = ['logo', 'favicon', 'default-avatar', 'avatar', 'placeholder', 'site_icon'];
        $filtered = [];
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

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
            // Handle case where base_url might not match exactly or http/https mismatch
            // Use wp_basename check as fallback if relative path extraction fails logic

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

            // Check 3: Deep String Scan
            // Search for ID
            if (strpos($big_string, (string) $c->ID) !== false)
                continue;

            // Search for Full URL
            if (strpos($big_string, $file_url) !== false)
                continue;

            // Search for Filename
            // Critical for cases where only filename is stored (e.g. some sliders, or Student_UI example)
            if (strpos($big_string, $filename) !== false)
                continue;

            // Search for Escaped URL (JSON)
            $escaped_url = str_replace('/', '\/', $file_url);
            if (strpos($big_string, $escaped_url) !== false)
                continue;

            // Search for Relative Path (escaped or not)
            if ($relative_path !== $file_url) {
                if (strpos($big_string, $relative_path) !== false)
                    continue;
            }

            // Check 4: Thumbnail Sizes (New)
            // Ensures that if a resized version is used, the original is not marked as orphan
            $metadata = wp_get_attachment_metadata($c->ID);
            if ($metadata && isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_info) {
                    if (isset($size_info['file'])) {
                        $thumb_file = $size_info['file']; // e.g. image-150x150.jpg
                        if (strpos($big_string, $thumb_file) !== false) {
                            continue 2; // Found usage, skip this candidate
                        }
                    }
                }
            }

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

    private static function get_unregistered_physical_files()
    {
        global $wpdb;
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir']; // Đường dẫn đến /wp-content/uploads 

        if (!is_dir($path))
            return [];

        // 1. Lấy danh sách tất cả file trong database để so sánh (chỉ lấy phần đường dẫn sau /uploads/) 
        $all_db_files = $wpdb->get_col(" 
            SELECT pm.meta_value 
            FROM {$wpdb->postmeta} pm 
            WHERE pm.meta_key = '_wp_attached_file' 
        ");

        // 2. Duyệt thư mục vật lý (Recursive) 
        // Use RecursiveDirectoryIterator but be careful with depth or permission errors
        try {
            $dir_iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            $files_on_disk = new RecursiveIteratorIterator($dir_iterator);
        } catch (Exception $e) {
            return []; // Fail gracefully if permission denied
        }

        $orphaned_physical = [];
        $count = 0;

        foreach ($files_on_disk as $file) {
            if ($file->isDir())
                continue;
            if ($count > 500)
                break; // Giới hạn để tránh treo server 

            $file_full_path = $file->getPathname();
            // Normalize slashes for consistency
            $file_full_path = wp_normalize_path($file_full_path);
            $base_path = wp_normalize_path($path . '/');

            // Chuyển đường dẫn tuyệt đối thành tương đối giống trong DB (ví dụ: 2024/05/image.jpg) 
            $relative_path = str_replace($base_path, '', $file_full_path);

            // Loại bỏ các file hệ thống không nằm trong DB 
            if (strpos($relative_path, 'elementor/') !== false)
                continue;
            if (strpos($relative_path, 'wc-logs/') !== false)
                continue;
            if (strpos($relative_path, 'woocommerce_uploads/') !== false)
                continue;
            // Ignore index.php, htaccess, etc.
            $ext = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'html', 'htm', 'htaccess', 'config']))
                continue;

            // 3. So sánh: Nếu không có trong danh sách DB -> RÁC 
            if (!in_array($relative_path, $all_db_files)) {
                // Kiểm tra xem có phải là các bản resize của ảnh gốc không (suffix -150x150, v.v.) 
                // Bước này quan trọng để tránh xóa nhầm thumbnail của ảnh đang dùng 
                if (!self::is_thumbnail_of_existing_file($relative_path, $all_db_files)) {
                    $orphaned_physical[] = (object) [
                        'ID' => 0, // Không có ID vì không có trong DB 
                        'post_title' => $relative_path,
                        'post_date' => date("Y-m-d H:i:s", $file->getMTime()),
                        'size_formatted' => size_format($file->getSize()),
                        'image_url' => $upload_dir['baseurl'] . '/' . $relative_path,
                        'reason' => 'File vật lý tồn tại nhưng không được đăng ký trong Thư viện Media.',
                        'type' => 'physical_file',
                        'raw_id' => 'phys_' . base64_encode($relative_path) // Dùng path làm ID tạm, prefix phys_ to distinguish
                    ];
                    $count++;
                }
            }
        }
        return $orphaned_physical;
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
        check_ajax_referer('azac_system_cleanup_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden');
        }

        $items = isset($_POST['items']) ? $_POST['items'] : [];
        if (empty($items) || !is_array($items)) {
            wp_send_json_error('Không có dữ liệu để xử lý.');
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
                if ($wpdb->delete($wpdb->prefix . 'az_feedback', ['id' => $id], ['%d'])) {
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
            $log_entry = [
                'time' => current_time('mysql'),
                'user' => $user->user_login,
                'message' => "Đã xóa $total mục mồ côi (Media: $count_media, Physical: $count_physical, Attendance: $count_attendance, Reviews: $count_reviews, Meta: $count_meta)."
            ];

            $logs = get_option('azac_system_logs', []);
            if (!is_array($logs))
                $logs = [];
            $logs[] = $log_entry;

            // Keep last 50 logs
            if (count($logs) > 50) {
                $logs = array_slice($logs, -50);
            }

            update_option('azac_system_logs', $logs);
        }

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
    }

    public static function ajax_cleanup_logs()
    {
        check_ajax_referer('azac_cleanup_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden');
        }

        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : '';
        $items = isset($_POST['items']) ? $_POST['items'] : []; // Array of hashes

        $logs = get_option('azac_system_logs', []);
        if (!is_array($logs))
            $logs = [];

        // Note: logs are stored Newest First (index 0 is newest)
        // Re-indexing is automatic with array_values if we unset

        $initial_count = count($logs);
        $deleted = 0;

        if ($mode === 'delete_all') {
            $logs = [];
            $deleted = $initial_count;
        } elseif ($mode === 'older_30') {
            $cutoff = strtotime('-30 days');
            $filtered = [];
            foreach ($logs as $log) {
                // Time format is mysql (Y-m-d H:i:s)
                $time = strtotime($log['time']);
                if ($time >= $cutoff) {
                    $filtered[] = $log;
                } else {
                    $deleted++;
                }
            }
            $logs = $filtered;
        } elseif ($mode === 'delete_selected') {
            if (empty($items)) {
                wp_send_json_error('Chưa chọn nhật ký nào.');
            }

            $filtered = [];
            foreach ($logs as $log) {
                $hash = md5($log['time'] . $log['user'] . (isset($log['message']) ? $log['message'] : ''));
                if (in_array($hash, $items)) {
                    $deleted++;
                } else {
                    $filtered[] = $log;
                }
            }
            $logs = $filtered;
        } else {
            wp_send_json_error('Chế độ không hợp lệ.');
        }

        update_option('azac_system_logs', array_values($logs));

        wp_send_json_success([
            'message' => "Đã xóa $deleted dòng nhật ký.",
            'remaining' => count($logs)
        ]);
    }
}
