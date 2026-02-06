<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_Attendance
{
    public static function register()
    {
        add_action('wp_ajax_azac_save_attendance', [__CLASS__, 'ajax_save_attendance']);
        add_action('wp_ajax_azac_get_attendance', [__CLASS__, 'ajax_get_attendance']);
        add_action('wp_ajax_azac_add_student', [__CLASS__, 'ajax_add_student']);
        add_action('wp_ajax_azac_search_students', [__CLASS__, 'ajax_search_students']);
        add_action('wp_ajax_azac_get_student_attendance_status', [__CLASS__, 'ajax_get_student_attendance_status']);
    }
    public static function ajax_search_students()
    {
        check_ajax_referer('azac_ajax_nonce', 'security');
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_text_field($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $biz = isset($_POST['biz']) ? sanitize_text_field($_POST['biz']) : '';

        $user = wp_get_current_user();
        $is_manager = in_array('az_manager', (array) $user->roles);
        if (!in_array('administrator', $user->roles, true) && !$is_manager) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        // NEW LOGIC: Search Users first, then ensure CPTs exist
        $u_args = [
            'role' => 'az_student',
            'number' => 20, // Limit
            'fields' => 'all_with_meta'
        ];

        $meta_query = [];

        // Search by Name (using 'search' param which covers display_name, email, login)
        if ($name) {
            $u_args['search'] = '*' . $name . '*';
            $u_args['search_columns'] = ['display_name', 'user_login', 'user_email'];
        }

        // Search by Email (specific)
        if ($email) {
            // If email is specific, strict search? Or just use it.
            // WP_User_Query doesn't combine 'search' (OR) with 'user_email' (AND) easily if both present.
            // But usually only one field is filled by the UI?
            // UI has separate fields.
            // If email is present, filtering by email is precise.
            $u_args['search'] = '*' . $email . '*';
            $u_args['search_columns'] = ['user_email'];
        }

        // Meta query for Phone / Biz
        if ($phone) {
            $meta_query[] = [
                'key' => 'billing_phone', // Check both billing_phone and phone?
                'value' => $phone,
                'compare' => 'LIKE'
            ];
            // Note: OR logic for phone keys is hard in single WP_User_Query meta_query without advanced syntax.
            // Assuming billing_phone is primary.
        }
        if ($biz) {
            $meta_query[] = [
                'key' => 'az_business_field',
                'value' => $biz,
                'compare' => 'LIKE'
            ];
        }

        if (!empty($meta_query)) {
            $u_args['meta_query'] = $meta_query;
        }

        $users = get_users($u_args);
        $posts = [];

        foreach ($users as $u) {
            // Try to find existing CPT
            $cpt_args = [
                'post_type' => 'az_student',
                'posts_per_page' => 1,
                'meta_key' => 'az_user_id',
                'meta_value' => $u->ID,
                'fields' => 'ids'
            ];
            $cpt_ids = get_posts($cpt_args);
            $cpt_id = !empty($cpt_ids) ? $cpt_ids[0] : 0;

            if (!$cpt_id) {
                // CPT does not exist. Create it!
                $cpt_id = wp_insert_post([
                    'post_type' => 'az_student',
                    'post_title' => $u->display_name ?: $u->user_login,
                    'post_status' => 'publish'
                ]);
                if ($cpt_id && !is_wp_error($cpt_id)) {
                    update_post_meta($cpt_id, 'az_user_id', $u->ID);
                } else {
                    continue;
                }
            }

            $p = get_post($cpt_id);
            if ($p) {
                $posts[] = $p;
            }
        }

        $results = [];
        foreach ($posts as $p) {
            $uid = get_post_meta($p->ID, 'az_user_id', true);
            $u_email = '';
            $u_phone = '';
            $u_biz = '';
            if ($uid) {
                $u = get_userdata($uid);
                if ($u) {
                    // Filter: Only allow 'az_student' role
                    if (!in_array('az_student', (array) $u->roles)) {
                        continue;
                    }
                    $u_email = $u->user_email;
                    $u_phone = get_user_meta($uid, 'billing_phone', true) ?: get_user_meta($uid, 'phone', true);
                    $u_biz = get_user_meta($uid, 'az_business_field', true);
                } else {
                    continue;
                }
            } else {
                continue;
            }
            $results[] = [
                'id' => $p->ID,
                'name' => $p->post_title,
                'email' => $u_email,
                'phone' => $u_phone,
                'biz' => $u_biz,
            ];
        }

        wp_send_json_success(['results' => $results]);
    }
    public static function ajax_save_attendance()
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
        $is_admin = current_user_can('manage_options');
        $is_manager = current_user_can('edit_posts') || current_user_can('manager');
        $is_teacher = in_array('az_teacher', $user->roles, true);

        if (!$is_admin && !$is_manager && !$is_teacher) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }

        if ($is_teacher && !$is_admin && !$is_manager) {
            $today = current_time('Y-m-d');
            if ($session_date !== $today) {
                wp_send_json_error(['message' => 'Bạn chỉ được điểm danh cho ngày hôm nay.'], 403);
            }
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
        
        if ($inserted > 0) {
            do_action('azac_attendance_saved', $class_id, $session_date, $type, $user->ID);
        }
        
        wp_send_json_success(['inserted' => $inserted]);
    }
    public static function ajax_get_attendance()
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

        // --- AUTO-FILL ABSENT FOR PAST SESSIONS ---
        $today = current_time('Y-m-d');
        // Robust Date Parsing
        $d_formatted = '9999-99-99';
        if (strpos($session_date, '/') !== false) {
            $d_obj = DateTime::createFromFormat('d/m/Y', $session_date);
            if ($d_obj)
                $d_formatted = $d_obj->format('Y-m-d');
        } else {
            $d_obj = DateTime::createFromFormat('Y-m-d', $session_date);
            if ($d_obj)
                $d_formatted = $d_obj->format('Y-m-d');
        }

        if ($d_formatted < $today) {
            $student_ids = get_post_meta($class_id, 'az_students', true);
            if (is_array($student_ids) && !empty($student_ids)) {
                $student_ids = array_map('absint', $student_ids);
                // Get existing records for this session & type
                $existing_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT student_id FROM {$table} WHERE class_id=%d AND session_date=%s AND attendance_type=%s",
                    $class_id,
                    $session_date,
                    $type
                ));
                $existing_ids = array_map('absint', (array) $existing_ids);

                // Identify missing students
                $missing_ids = array_diff($student_ids, $existing_ids);

                if (!empty($missing_ids)) {
                    foreach ($missing_ids as $sid) {
                        $wpdb->insert(
                            $table,
                            [
                                'class_id' => $class_id,
                                'student_id' => $sid,
                                'session_date' => $session_date,
                                'attendance_type' => $type,
                                'status' => 0, // Absent
                                'note' => ''
                            ],
                            ['%d', '%d', '%s', '%s', '%d', '%s']
                        );
                    }
                }
            }
        }
        // ------------------------------------------

        $rows = $wpdb->get_results($wpdb->prepare("SELECT student_id, status, note FROM {$table} WHERE class_id=%d AND session_date=%s AND attendance_type=%s", $class_id, $session_date, $type), ARRAY_A);
        $map = [];
        foreach ($rows as $r) {
            $map[intval($r['student_id'])] = ['status' => intval($r['status']), 'note' => $r['note']];
        }
        wp_send_json_success(['items' => $map]);
    }
    public static function ajax_add_student()
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
        $is_manager = in_array('az_manager', (array) $user->roles);

        if (!$is_admin && !$is_manager) {
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

                // Security: Only Admin/Manager can create new users
                if (!$is_admin && !$is_manager) {
                    wp_send_json_error(['message' => 'Bạn không có quyền tạo tài khoản mới.'], 403);
                }

                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $u = new WP_User($user_id);
                    $u->set_role('az_student');

                    // Send notification email
                    wp_new_user_notification($user_id, null, 'both');

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

    public static function ajax_get_student_attendance_status()
    {
        // Security Check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_attendance_save')) {
            ob_clean();
            wp_send_json_error(['message' => 'Invalid Nonce'], 403);
        }

        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;
        $session_date = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';

        if (!$class_id || !$session_date || !$student_id) {
            ob_clean();
            wp_send_json_error(['message' => 'Invalid params'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'az_attendance';

        // Query both check-in and mid-session status
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT attendance_type, status FROM $table WHERE class_id = %d AND student_id = %d AND session_date = %s",
            $class_id,
            $student_id,
            $session_date
        ));

        $check_in = 0;
        $mid_session = 0;

        if ($results) {
            foreach ($results as $row) {
                if ($row->attendance_type === 'check-in') {
                    $check_in = intval($row->status);
                }
                if ($row->attendance_type === 'mid-session') {
                    $mid_session = intval($row->status);
                }
            }
        }

        ob_clean();
        wp_send_json_success([
            'check_in' => $check_in,
            'mid_session' => $mid_session
        ]);
    }
}


