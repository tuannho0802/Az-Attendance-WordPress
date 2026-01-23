<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Sessions
{
    public static function register()
    {
        add_action('init', [__CLASS__, 'ensure_sessions_table'], 1);
        add_action('wp_ajax_azac_add_session', [__CLASS__, 'ajax_add_session']);
        add_action('wp_ajax_azac_update_session', [__CLASS__, 'ajax_update_session']);
        add_action('wp_ajax_azac_list_sessions', [__CLASS__, 'ajax_list_sessions']);
        add_action('wp_ajax_azac_get_session_details', [__CLASS__, 'ajax_get_session_details']);
        add_action('wp_ajax_azac_save_session_content', [__CLASS__, 'ajax_save_session_content']);
        add_action('wp_ajax_azac_upload_pdf_image', [__CLASS__, 'ajax_upload_pdf_image']);
        add_action('wp_ajax_azac_get_class_session_dates', [__CLASS__, 'ajax_get_class_session_dates']);
        add_action('wp_ajax_azac_teacher_checkin', [__CLASS__, 'ajax_teacher_checkin']);
        add_action('wp_ajax_azac_delete_session', [__CLASS__, 'ajax_delete_session']);
        add_action('wp_ajax_azac_bulk_delete_sessions', [__CLASS__, 'ajax_bulk_delete_sessions']);

        // Cascade delete when Class is deleted
        add_action('before_delete_post', [__CLASS__, 'on_class_delete']);
    }

    public static function on_class_delete($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'az_class') {
            return;
        }

        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $att_table = $wpdb->prefix . 'az_attendance';
        $teaching_table = $wpdb->prefix . 'az_teaching_hours';

        // 1. Delete Attendance
        $wpdb->delete($att_table, ['class_id' => $post_id], ['%d']);

        // 2. Delete Teaching Hours (if exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '$teaching_table'") === $teaching_table) {
            $wpdb->delete($teaching_table, ['class_id' => $post_id], ['%d']);
        }

        // 3. Delete Sessions
        $wpdb->delete($sess_table, ['class_id' => $post_id], ['%d']);
    }
    public static function ajax_get_class_session_dates()
    {
        check_ajax_referer('azac_session', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!$class_id) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }

        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $dates = $wpdb->get_col($wpdb->prepare("SELECT session_date FROM {$sess_table} WHERE class_id=%d", $class_id));

        wp_send_json_success(['dates' => $dates]);
    }

    public static function ensure_sessions_table()
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
                session_content longtext NULL,
                session_attachments text NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY class_id (class_id),
                KEY session_date (session_date),
                UNIQUE KEY uniq_class_date (class_id, session_date)
            ) {$charset_collate};";
            dbDelta($sql_sessions);
        } else {
            // Upgrade table if exists but missing columns
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$sess_table} LIKE 'session_content'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$sess_table} ADD COLUMN session_content longtext NULL");
            }
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$sess_table} LIKE 'session_attachments'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$sess_table} ADD COLUMN session_attachments text NULL");
            }
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$sess_table} LIKE 'teacher_checkin'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$sess_table} ADD COLUMN teacher_checkin tinyint(1) NOT NULL DEFAULT 0");
            }
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$sess_table} LIKE 'teacher_checkin_time'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$sess_table} ADD COLUMN teacher_checkin_time datetime NULL");
            }
        }
    }
    public static function get_class_sessions($class_id)
    {
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, session_date, session_time, title, session_content, session_attachments FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC", $class_id), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $d = isset($r['session_date']) ? sanitize_text_field($r['session_date']) : '';
            $t = isset($r['session_time']) ? sanitize_text_field($r['session_time']) : '';
            if ($d) {
                $out[] = [
                    'id' => $r['id'],
                    'date' => $d,
                    'time' => $t,
                    'title' => $r['title'],
                    'content' => $r['session_content'],
                    'attachments' => $r['session_attachments']
                ];
            }
        }
        return $out;
    }
    public static function upsert_class_session($class_id, $date, $time)
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
        return self::get_class_sessions($class_id);
    }
    public static function update_class_session($class_id, $old_date, $new_date, $new_time)
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
        return self::get_class_sessions($class_id);
    }
    public static function ajax_add_session()
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

        // Only Admin can add sessions
        if (!$is_admin) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }

        $sessions = self::upsert_class_session($class_id, $date, $time);

        // Audit Log
        do_action('azac_session_created', $class_id, $date, $time, $user->ID);

        wp_send_json_success(['sessions' => $sessions, 'selected' => $date]);
    }
    public static function ajax_update_session()
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

        // Only Admin can update sessions
        if (!$is_admin) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }

        $sessions = self::update_class_session($class_id, $date, $new_date, $new_time);

        // Audit Log
        do_action('azac_session_updated', $class_id, $date, $new_date, $user->ID);

        wp_send_json_success(['sessions' => $sessions, 'selected' => $new_date]);
    }
    public static function ajax_list_sessions()
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

        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 20;
        $filter_class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $filter_today = isset($_POST['filter_today']) ? intval($_POST['filter_today']) : 0;
        $date_start = isset($_POST['date_start']) ? sanitize_text_field($_POST['date_start']) : '';
        $date_end = isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : '';

        $allowed_class_ids = [];
        $args = [
            'post_type' => 'az_class',
            'numberposts' => -1,
            'fields' => 'ids',
            'post_status' => $is_admin ? ['publish', 'pending'] : ['publish'],
        ];

        if (!empty($search) && mb_strlen($search) >= 2) {
            $args['s'] = $search;
        }

        if ($is_teacher && !$is_admin) {
            $args['meta_key'] = 'az_teacher_user';
            $args['meta_value'] = intval($user->ID);
        }

        $all_classes_ids = get_posts($args);
        $available_classes = [];

        if ($is_student) {
            $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
            foreach ($all_classes_ids as $cid) {
                $ids = get_post_meta($cid, 'az_students', true);
                $ids = is_array($ids) ? array_map('absint', $ids) : [];
                if (in_array($student_post_id, $ids, true)) {
                    $allowed_class_ids[] = $cid;
                    $available_classes[] = [
                        'id' => $cid,
                        'title' => get_the_title($cid)
                    ];
                }
            }
        } else {
            $allowed_class_ids = $all_classes_ids;
            foreach ($allowed_class_ids as $cid) {
                $available_classes[] = [
                    'id' => $cid,
                    'title' => get_the_title($cid)
                ];
            }
        }

        // Filter by class_id from POST
        $filter_class_id = isset($_POST['filter_class_id']) ? absint($_POST['filter_class_id']) : $filter_class_id;
        if ($filter_class_id) {
            if (in_array($filter_class_id, $allowed_class_ids)) {
                $allowed_class_ids = [$filter_class_id];
            } else {
                $allowed_class_ids = [];
            }
        }

        if (empty($allowed_class_ids)) {
            wp_send_json_success(['sessions' => [], 'total_items' => 0, 'total_pages' => 0, 'current_page' => $paged]);
        }

        $sess_table = $wpdb->prefix . 'az_sessions';
        $ids_placeholder = implode(',', array_fill(0, count($allowed_class_ids), '%d'));

        // Build Date Where Clause
        $where_sql = "";
        $params_where = $allowed_class_ids;

        if ($date_start) {
            $ds = date('Y-m-d', strtotime($date_start));
            $where_sql .= " AND session_date >= %s";
            $params_where[] = $ds;
        }
        if ($date_end) {
            $de = date('Y-m-d', strtotime($date_end));
            $where_sql .= " AND session_date <= %s";
            $params_where[] = $de;
        }
        if ($filter_today && !$date_start && !$date_end) {
            $today = current_time('Y-m-d');
            $where_sql .= " AND session_date = %s";
            $params_where[] = $today;
        }

        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$sess_table} WHERE class_id IN ($ids_placeholder) {$where_sql}",
            $params_where
        ));

        $total_pages = ceil($total_items / $per_page);
        $offset = ($paged - 1) * $per_page;

        $order_sql = "ORDER BY (session_date = CURRENT_DATE()) DESC, session_date DESC, session_time DESC";
        $join_sql = "";
        $select_extra = "";
        
        if ($sort === 'date_asc') {
            $order_sql = "ORDER BY session_date ASC, session_time ASC";
        } elseif ($sort === 'rate_asc' || $sort === 'rate_desc') {
            // Calculate attendance rate for sorting
            // Rate = (Sum of status=1) / (Count of records)
            // Using a subquery might be slow but functional
            $att_table = $wpdb->prefix . 'az_attendance';
            $select_extra = ", (SELECT COALESCE(SUM(status),0) / NULLIF(COUNT(*),0) FROM {$att_table} WHERE class_id={$sess_table}.class_id AND session_date={$sess_table}.session_date) as att_rate";

            if ($sort === 'rate_asc') {
                // High Absence = Low Attendance Rate -> rate ASC
                $order_sql = "ORDER BY att_rate ASC, session_date DESC";
            } else {
                $order_sql = "ORDER BY att_rate DESC, session_date DESC";
            }
        }

        // Calculate Session Number (Ordinal)
        $select_extra .= ", (SELECT COUNT(*) FROM {$sess_table} as s2 WHERE s2.class_id = {$sess_table}.class_id AND s2.session_date <= {$sess_table}.session_date) as session_number";

        $query = "SELECT * {$select_extra} FROM {$sess_table} WHERE class_id IN ($ids_placeholder) {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
        $query_params = array_merge($params_where, [$per_page, $offset]);

        $rows = $wpdb->get_results($wpdb->prepare($query, $query_params));

        $out = [];
        foreach ($rows as $s) {
            $c_id = intval($s->class_id);
            $c_title = get_the_title($c_id);
            $s_date = $s->session_date;

            $att_table = $wpdb->prefix . 'az_attendance';
            $att_rows = $wpdb->get_results($wpdb->prepare("SELECT attendance_type, status, COUNT(*) as c FROM {$att_table} WHERE class_id=%d AND session_date=%s GROUP BY attendance_type, status", $c_id, $s_date), ARRAY_A);

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

            $ids = get_post_meta($c_id, 'az_students', true);
            $ids = is_array($ids) ? array_map('absint', $ids) : [];
            $total_students = count($ids);

            $rate_checkin = ($checkin_present + $checkin_absent) > 0 ? round(($checkin_present / ($checkin_present + $checkin_absent)) * 100) : 0;
            $rate_mid = ($mid_present + $mid_absent) > 0 ? round(($mid_present / ($mid_present + $mid_absent)) * 100) : 0;
            $rate_overall = round(($rate_checkin + $rate_mid) / 2);

            $out[] = [
                'id' => intval($s->id),
                'class_id' => $c_id,
                'class_title' => $c_title,
                'session_number' => intval($s->session_number),
                'date' => $s_date,
                'time' => $s->session_time,
                'link' => admin_url('admin.php?page=azac-classes-list&class_id=' . $c_id . '&session_date=' . urlencode($s_date)),
                'checkin' => ['present' => $checkin_present, 'absent' => $checkin_absent],
                'mid' => ['present' => $mid_present, 'absent' => $mid_absent],
                'total' => $total_students,
                'rate' => ['checkin' => $rate_checkin, 'mid' => $rate_mid, 'overall' => $rate_overall],
            ];
        }

        wp_send_json_success([
            'sessions' => $out,
            'available_classes' => $available_classes,
            'total_items' => intval($total_items),
            'total_pages' => intval($total_pages),
            'current_page' => intval($paged)
        ]);
    }

    public static function ajax_get_session_details()
    {
        check_ajax_referer('azac_session_content', 'nonce');

        $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        if (!$session_id) {
            wp_send_json_error(['message' => 'Invalid Session ID'], 400);
        }

        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sess_table} WHERE id = %d", $session_id));

        if (!$row) {
            wp_send_json_error(['message' => 'Session not found'], 404);
        }

        // Attachments
        $att_ids = json_decode($row->session_attachments, true);
        if (!is_array($att_ids)) {
            $att_ids = [];
        }

        $attachments = [];
        foreach ($att_ids as $att_id) {
            $att_id = intval($att_id);
            if ($att_id) {
                $url = wp_get_attachment_url($att_id);
                // Ensure URL is valid and absolute
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    $post = get_post($att_id);
                    $attachments[] = [
                        'id' => $att_id,
                        'title' => $post ? $post->post_title : 'Tài liệu',
                        'url' => $url,
                        'mime' => get_post_mime_type($att_id)
                    ];
                }
            }
        }

        wp_send_json_success([
            'content' => wpautop(stripslashes($row->session_content)),
            'raw_content' => stripslashes($row->session_content),
            'attachments' => $attachments
        ]);
    }

    public static function ajax_save_session_content()
    {
        check_ajax_referer('azac_session_content', 'nonce');

        $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $attachments = isset($_POST['attachments']) ? (array) $_POST['attachments'] : [];

        if (!$session_id) {
            wp_send_json_error(['message' => 'Invalid Session ID'], 400);
        }

        // Permission check
        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';
        $session = $wpdb->get_row($wpdb->prepare("SELECT class_id, session_date FROM {$sess_table} WHERE id = %d", $session_id));

        if (!$session) {
            wp_send_json_error(['message' => 'Session not found'], 404);
        }

        $class_id = $session->class_id;
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);

        $can_edit = false;
        if ($is_admin) {
            $can_edit = true;
        } elseif ($is_teacher) {
            $teacher_user = intval(get_post_meta($class_id, 'az_teacher_user', true));
            if ($teacher_user === intval($user->ID)) {
                $today = current_time('Y-m-d');
                if ($session->session_date !== $today) {
                    wp_send_json_error(['message' => 'Chỉ được sửa nội dung vào đúng ngày dạy.'], 403);
                }
                $can_edit = true;
            }
        }

        if (!$can_edit) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        // Sanitize attachments (ensure they are integers)
        $clean_atts = array_map('absint', $attachments);
        $json_atts = json_encode($clean_atts);

        $updated = $wpdb->update(
            $sess_table,
            [
                'session_content' => $content,
                'session_attachments' => $json_atts
            ],
            ['id' => $session_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'DB Error'], 500);
        }

        wp_send_json_success(['message' => 'Saved']);
    }

    public static function ajax_upload_pdf_image()
    {
        check_ajax_referer('azac_session_content', 'nonce');

        $data = isset($_POST['image']) ? $_POST['image'] : '';
        if (empty($data)) {
            wp_send_json_error(['message' => 'No image data'], 400);
        }

        // Permission check
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);

        if (!$is_admin && !$is_teacher) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        // Detect type and strip header
        $ext = '.png';
        if (strpos($data, 'data:image/jpeg;base64,') === 0) {
            $data = str_replace('data:image/jpeg;base64,', '', $data);
            $ext = '.jpg';
        } elseif (strpos($data, 'data:image/png;base64,') === 0) {
            $data = str_replace('data:image/png;base64,', '', $data);
            $ext = '.png';
        }

        $data = str_replace(' ', '+', $data);
        $decoded = base64_decode($data);

        if (!$decoded) {
            wp_send_json_error(['message' => 'Decode failed'], 400);
        }

        // Upload to WP Media
        $upload_dir = wp_upload_dir();

        // Optimized Filename: session-[ID]-[timestamp]-[index]
        $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        $idx = isset($_POST['image_index']) ? absint($_POST['image_index']) : wp_rand(100, 999);
        $prefix = $session_id ? "session-{$session_id}" : "pdf-import";

        $filename = "{$prefix}-" . time() . "-{$idx}{$ext}";
        $file_path = $upload_dir['path'] . '/' . $filename;

        file_put_contents($file_path, $decoded);

        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success(['url' => wp_get_attachment_url($attach_id), 'id' => $attach_id]);
    }

    public static function ajax_teacher_checkin()
    {
        check_ajax_referer('azac_session', 'nonce');
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $is_checkin = isset($_POST['is_checkin']) ? intval($_POST['is_checkin']) : 0;

        if (!$class_id || !$date) {
            wp_send_json_error(['message' => 'Invalid'], 400);
        }

        $user = wp_get_current_user();
        if (in_array('az_teacher', $user->roles, true)) {
            $teacher_user = intval(get_post_meta($class_id, 'az_teacher_user', true));
            if ($teacher_user !== intval($user->ID)) {
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            // Critical: Only allow check-in if session_date == current_date
            $today = current_time('Y-m-d');
            if ($date !== $today) {
                wp_send_json_error(['message' => 'Chỉ được chấm công vào đúng ngày dạy.'], 403);
            }
        } elseif (!in_array('administrator', $user->roles, true)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        global $wpdb;
        $sess_table = $wpdb->prefix . 'az_sessions';

        $wpdb->update(
            $sess_table,
            [
                'teacher_checkin' => $is_checkin,
                'teacher_checkin_time' => $is_checkin ? current_time('mysql') : null,
                // Also update legacy/admin columns for consistency if needed, but keeping separate as requested
                'is_taught' => $is_checkin,
                'taught_at' => $is_checkin ? current_time('mysql') : null
            ],
            ['class_id' => $class_id, 'session_date' => $date],
            ['%d', '%s', '%d', '%s'],
            ['%d', '%s']
        );

        wp_send_json_success(['is_checkin' => $is_checkin]);
    }

    public static function ajax_delete_session()
    {
        check_ajax_referer('azac_delete_session', '_ajax_nonce');
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'az_sessions';

        $session = $wpdb->get_row($wpdb->prepare("SELECT class_id, session_date FROM $table WHERE id = %d", $id));

        if ($session) {
            $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
            if ($deleted) {
                $att_table = $wpdb->prefix . 'az_attendance';
                $wpdb->delete($att_table, [
                    'class_id' => $session->class_id,
                    'session_date' => $session->session_date
                ], ['%d', '%s']);

                // Cascade Delete: Teaching Hours
                $teaching_table = $wpdb->prefix . 'az_teaching_hours';
                if ($wpdb->get_var("SHOW TABLES LIKE '$teaching_table'") === $teaching_table) {
                    $wpdb->delete($teaching_table, [
                        'class_id' => $session->class_id,
                        'session_date' => $session->session_date
                    ], ['%d', '%s']);
                }

                do_action('azac_session_deleted', $session->class_id, $session->session_date, get_current_user_id());

                wp_send_json_success(['message' => 'Deleted']);
            }
        }

        wp_send_json_error(['message' => 'Failed to delete'], 500);
    }

    public static function ajax_bulk_delete_sessions()
    {
        check_ajax_referer('az_bulk_delete_nonce', '_ajax_nonce');
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $ids = isset($_POST['session_ids']) ? (array) $_POST['session_ids'] : [];
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            wp_send_json_error(['message' => 'Không có buổi học nào được chọn'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'az_sessions';
        $att_table = $wpdb->prefix . 'az_attendance';

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT class_id, session_date FROM $table WHERE id IN ($ids_placeholder)",
            $ids
        ));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id IN ($ids_placeholder)",
            $ids
        ));

        if ($deleted !== false) {
            foreach ($sessions as $sess) {
                $wpdb->delete($att_table, [
                    'class_id' => $sess->class_id,
                    'session_date' => $sess->session_date
                ], ['%d', '%s']);

                // Cascade Delete: Teaching Hours
                $teaching_table = $wpdb->prefix . 'az_teaching_hours';
                if ($wpdb->get_var("SHOW TABLES LIKE '$teaching_table'") === $teaching_table) {
                    $wpdb->delete($teaching_table, [
                        'class_id' => $sess->class_id,
                        'session_date' => $sess->session_date
                    ], ['%d', '%s']);
                }

                do_action('azac_session_deleted', $sess->class_id, $sess->session_date, get_current_user_id());
            }
            wp_send_json_success(['message' => 'Deleted ' . $deleted . ' sessions']);
        }

        wp_send_json_error(['message' => 'Failed to delete'], 500);
    }
}
