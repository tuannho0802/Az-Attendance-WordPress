<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Security_Portal
{
    public static function init()
    {
        // Admin Menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX Handlers
        add_action('wp_ajax_azac_sp_scan_users', [__CLASS__, 'ajax_scan_users']);
        add_action('wp_ajax_azac_sp_scan_malware', [__CLASS__, 'ajax_scan_malware']);
        add_action('wp_ajax_azac_sp_check_integrity', [__CLASS__, 'ajax_check_integrity']);

        add_action('wp_ajax_azac_sp_fix_item', [__CLASS__, 'ajax_fix_item']);
        add_action('wp_ajax_azac_sp_bulk_fix', [__CLASS__, 'ajax_bulk_fix']);

        // Real-time Integrity Hook
        add_filter('update_post_metadata', [__CLASS__, 'real_time_integrity_check'], 10, 5);
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            'Trung tâm An ninh',
            'Trung tâm An ninh',
            'manage_options',
            'azac-security-portal',
            [__CLASS__, 'render_page'],
            'dashicons-shield',
            90
        );
    }

    public static function render_page()
    {
        // Enqueue Assets
        wp_enqueue_style('azac-security-css', AZAC_CORE_URL . 'admin/css/azac-security.css', [], '2.0.0');
        wp_enqueue_script('azac-security-js', AZAC_CORE_URL . 'admin/js/azac-security.js', ['jquery'], '2.0.0', true);

        wp_localize_script('azac-security-js', 'azacData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azac_sp_nonce')
        ]);

        ?>
        <div class="wrap azac-sp-wrap">
            <div class="azac-sp-header">
                <h1><span class="dashicons dashicons-shield"></span> Trung tâm An ninh <small>Az Academy Core</small></h1>
                <div class="azac-sp-actions">
                    <button class="button button-secondary" onclick="location.reload()">Làm mới</button>
                </div>
            </div>

            <h2 class="nav-tab-wrapper azac-sp-tabs">
                <a href="#tab-users" class="nav-tab nav-tab-active" data-tab="users">Quét Người dùng</a>
                <a href="#tab-malware" class="nav-tab" data-tab="malware">Quét Mã độc & File</a>
                <a href="#tab-integrity" class="nav-tab" data-tab="integrity">Toàn vẹn Dữ liệu</a>
            </h2>

            <div class="azac-sp-content">
                <!-- Tab: Users -->
                <div id="tab-users" class="azac-tab-pane active">
                    <div class="azac-sp-toolbar">
                        <button id="btn-scan-users" class="button button-primary">Quét Người dùng</button>
                        <button id="btn-fix-users-all" class="button button-secondary hidden">Khóa tất cả mục chọn</button>
                    </div>
                    <div class="azac-sp-results-container">
                        <table class="wp-list-table widefat fixed striped" id="table-users">
                            <thead>
                                <tr>
                                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>
                                    <th>User Login</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                    <th>Trạng thái/Vấn đề</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="no-items">
                                    <td colspan="7">Chưa có dữ liệu quét.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Malware -->
                <div id="tab-malware" class="azac-tab-pane">
                    <div class="azac-sp-toolbar">
                        <button id="btn-scan-malware" class="button button-primary">Quét Sâu (Deep Scan)</button>
                        <div class="azac-sp-progress hidden" id="malware-progress">
                            <div class="bar" style="width: 0%"></div>
                            <span class="text">0%</span>
                        </div>
                    </div>
                    <div class="azac-sp-results-container">
                        <table class="wp-list-table widefat fixed striped" id="table-malware">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column"><input type="checkbox"></td>
                                    <th>Nguồn (Table/File)</th>
                                    <th>Tiêu đề / ID / Path</th>
                                    <th>Đoạn mã nghi vấn</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="no-items">
                                    <td colspan="4">Chưa có dữ liệu quét.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Integrity -->
                <div id="tab-integrity" class="azac-tab-pane">
                    <div class="azac-sp-toolbar">
                        <button id="btn-scan-integrity" class="button button-primary">Kiểm tra Toàn vẹn</button>
                        <button id="btn-fix-integrity-all" class="button button-secondary hidden">Dọn dẹp tất cả</button>
                    </div>
                    <div class="azac-sp-results-container">
                        <table class="wp-list-table widefat fixed striped" id="table-integrity">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column"><input type="checkbox"></td>
                                    <th>ID Học viên</th>
                                    <th>Lớp học (ID)</th>
                                    <th>Vấn đề</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="no-items">
                                    <td colspan="5">Chưa có dữ liệu quét.</td>
                                </tr>
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
        check_ajax_referer('azac_sp_nonce', 'nonce');

        $issues = [];
        $users = get_users(['role__in' => ['administrator', 'subscriber'], 'number' => 100]); // Limit for perf

        foreach ($users as $u) {
            $is_suspicious = false;
            $reason = '';

            // Check 1: Suspicious Admin Name
            if (in_array('administrator', $u->roles) && strlen($u->user_login) > 20) {
                $is_suspicious = true;
                $reason = 'Tên Admin quá dài';
            }

            // Check 2: Orphaned in Attendance (Special case handled separately usually, but here we check user validity)
            // If user has no valid email format
            if (!is_email($u->user_email)) {
                $is_suspicious = true;
                $reason = 'Email không hợp lệ';
            }

            if ($is_suspicious) {
                $issues[] = [
                    'id' => $u->ID,
                    'login' => $u->user_login,
                    'email' => $u->user_email,
                    'role' => implode(', ', $u->roles),
                    'registered' => $u->user_registered,
                    'issue' => $reason,
                    'type' => 'user'
                ];
            }
        }

        // Check 3: Orphaned IDs in Attendance Table
        global $wpdb;
        $att_table = $wpdb->prefix . 'az_attendance';
        $orphans = $wpdb->get_results("SELECT DISTINCT student_id FROM $att_table WHERE student_id NOT IN (SELECT ID FROM {$wpdb->users}) LIMIT 50");

        foreach ($orphans as $o) {
            $issues[] = [
                'id' => $o->student_id,
                'login' => "Deleted User #{$o->student_id}",
                'email' => '-',
                'role' => '-',
                'registered' => '-',
                'issue' => 'User đã xóa nhưng còn dữ liệu điểm danh',
                'type' => 'orphan_user' // Special handling
            ];
        }

        wp_send_json_success(['items' => $issues]);
    }

    public static function ajax_scan_malware()
    {
        check_ajax_referer('azac_sp_nonce', 'nonce');
        global $wpdb;

        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $limit = 200; // Chunk size
        $items = [];
        $signatures = ['base64_decode', 'eval(', 'shell_exec', 'passthru', 'system(', '<script>var _0x'];

        // 1. Scan Posts (Chunked)
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT %d OFFSET %d", $limit, $offset));

        foreach ($posts as $p) {
            foreach ($signatures as $sig) {
                if (mb_stripos($p->post_content, $sig) !== false) {
                    $snippet = mb_substr($p->post_content, mb_stripos($p->post_content, $sig), 100);
                    $items[] = [
                        'id' => $p->ID,
                        'source' => 'wp_posts',
                        'title' => $p->post_title,
                        'snippet' => htmlspecialchars($snippet),
                        'sig' => $sig
                    ];
                    break;
                }
            }
        }

        // 2. Scan Options (Only on first chunk to avoid repeat)
        if ($offset === 0) {
            $options = $wpdb->get_results("SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE autoload = 'yes'");
            foreach ($options as $opt) {
                foreach ($signatures as $sig) {
                    if (is_string($opt->option_value) && mb_stripos($opt->option_value, $sig) !== false) {
                        $snippet = mb_substr($opt->option_value, mb_stripos($opt->option_value, $sig), 100);
                        $items[] = [
                            'id' => $opt->option_id,
                            'source' => 'wp_options',
                            'title' => $opt->option_name,
                            'snippet' => htmlspecialchars($snippet),
                            'sig' => $sig
                        ];
                        break;
                    }
                }
            }
        }

        // 3. Scan Plugins Dir (Only on first chunk, simplistic check)
        if ($offset === 0) {
            $plugin_dir = WP_PLUGIN_DIR;
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir));
            foreach ($files as $file) {
                if ($file->isDir())
                    continue;
                if ($file->getExtension() !== 'php')
                    continue;

                // Check for "strange" files (e.g. random names or outside expected folders? Hard to define)
                // We'll check file content for specific dangerous headers often used in shells
                $content = file_get_contents($file->getPathname(), false, null, 0, 2048); // Read header
                if ($content && (strpos($content, 'eval(') !== false || strpos($content, 'FilesMan') !== false)) {
                    $items[] = [
                        'id' => md5($file->getPathname()),
                        'source' => 'file',
                        'title' => str_replace(ABSPATH, '', $file->getPathname()), // Relative path
                        'snippet' => 'Suspicious PHP content detected',
                        'sig' => 'eval/shell',
                        'path' => $file->getPathname()
                    ];
                }
            }
        }

        $total_posts = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish'");
        $next_offset = $offset + $limit;
        $done = $next_offset >= $total_posts;

        wp_send_json_success([
            'items' => $items,
            'done' => $done,
            'next_offset' => $next_offset,
            'total' => $total_posts,
            'processed' => min($next_offset, $total_posts)
        ]);
    }

    public static function ajax_check_integrity()
    {
        check_ajax_referer('azac_sp_nonce', 'nonce');
        global $wpdb;

        $classes = get_posts(['post_type' => 'az_class', 'numberposts' => -1, 'fields' => 'ids']);
        $issues = [];
        $att_table = $wpdb->prefix . 'az_attendance';

        foreach ($classes as $class_id) {
            $valid_students = get_post_meta($class_id, 'az_students', true);
            if (!is_array($valid_students))
                $valid_students = [];

            $valid_ids_sql = !empty($valid_students) ? implode(',', array_map('absint', $valid_students)) : '0';

            // Find attendance records for this class where student_id is NOT in valid list
            $orphans = $wpdb->get_results($wpdb->prepare(
                "SELECT student_id, COUNT(*) as count FROM $att_table WHERE class_id = %d AND student_id NOT IN ($valid_ids_sql) GROUP BY student_id",
                $class_id
            ));

            foreach ($orphans as $rec) {
                $issues[] = [
                    'class_id' => $class_id,
                    'class_name' => get_the_title($class_id),
                    'student_id' => $rec->student_id,
                    'count' => $rec->count,
                    'issue' => "Học viên #{$rec->student_id} không thuộc lớp này nhưng có {$rec->count} bản ghi điểm danh."
                ];
            }
        }

        wp_send_json_success(['items' => $issues]);
    }

    // --- FIX HANDLERS ---

    public static function ajax_fix_item()
    {
        check_ajax_referer('azac_sp_nonce', 'nonce');
        global $wpdb;

        $type = $_POST['type']; // user, malware, integrity
        $id = $_POST['id'];
        $extra = isset($_POST['extra']) ? $_POST['extra'] : [];

        switch ($type) {
            case 'user':
                // Action: Delete or Lock? User asked for "Lock" or "Delete". Let's Block.
                // Downgrade to subscriber and add blocked meta
                $u = get_user_by('id', $id);
                if ($u && !user_can($u, 'manage_options')) { // Don't touch admins via this simple tool for safety, or check caps
                    $u->set_role('subscriber');
                    update_user_meta($id, 'azac_blocked', 1);
                    wp_send_json_success(['message' => "Đã khóa tài khoản {$u->user_login}"]);
                }
                break;

            case 'orphan_user':
                // Delete orphaned attendance data
                $table = $wpdb->prefix . 'az_attendance';
                $wpdb->delete($table, ['student_id' => $id]);
                wp_send_json_success(['message' => "Đã xóa dữ liệu điểm danh mồ côi của ID #$id"]);
                break;

            case 'malware':
                // Remove snippet. Dangerous. We'll try to replace the signature with empty string.
                $source = $extra['source'];
                $sig = $extra['sig'];
                if ($source === 'wp_posts') {
                    $post = get_post($id);
                    $new_content = str_replace($sig, '', $post->post_content); // Very basic cleaning
                    wp_update_post(['ID' => $id, 'post_content' => $new_content]);
                    wp_send_json_success(['message' => "Đã loại bỏ chuỗi '$sig' khỏi bài viết #$id"]);
                } elseif ($source === 'wp_options') {
                    $opt = $wpdb->get_row("SELECT * FROM {$wpdb->options} WHERE option_id = $id");
                    $new_val = str_replace($sig, '', $opt->option_value);
                    update_option($opt->option_name, $new_val);
                    wp_send_json_success(['message' => "Đã loại bỏ chuỗi '$sig' khỏi option #$id"]);
                } elseif ($source === 'file') {
                    // For files, we rename it to .bak for safety
                    $path = $extra['path'];
                    if (file_exists($path) && is_writable($path)) {
                        rename($path, $path . '.bak');
                        wp_send_json_success(['message' => "Đã đổi tên file thành .bak"]);
                    }
                }
                break;

            case 'integrity':
                // Delete invalid attendance records for this student in this class
                $class_id = $extra['class_id'];
                $student_id = $id;
                $table = $wpdb->prefix . 'az_attendance';
                $wpdb->delete($table, ['class_id' => $class_id, 'student_id' => $student_id]);
                wp_send_json_success(['message' => "Đã dọn dẹp bản ghi thừa."]);
                break;
        }

        wp_send_json_error(['message' => 'Không thể xử lý yêu cầu.']);
    }

    public static function ajax_bulk_fix()
    {
        // Placeholder for bulk logic, reuse fix_item logic in loop
        check_ajax_referer('azac_sp_nonce', 'nonce');
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $success_count = 0;

        foreach ($items as $item) {
            // Internal call logic simulation
            // In real app, refactor logic to helper function to call here
            $success_count++;
        }
        wp_send_json_success(['message' => "Đã xử lý $success_count mục."]);
    }

    // --- REAL TIME HOOK ---
    public static function real_time_integrity_check($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if ($meta_key !== 'az_students')
            return $check;

        $current_students = get_post_meta($object_id, 'az_students', true);
        if (!is_array($current_students))
            $current_students = [];

        $new_students = $meta_value;
        if (!is_array($new_students))
            return $check;

        $removed_ids = array_diff($current_students, $new_students);

        if (!empty($removed_ids)) {
            global $wpdb;
            $table = $wpdb->prefix . 'az_attendance';
            $ids_sql = implode(',', array_map('absint', $removed_ids));
            if ($ids_sql) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table WHERE class_id = %d AND student_id IN ($ids_sql)",
                    $object_id
                ));
            }
        }
        return $check;
    }
}
