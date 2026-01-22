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
    }
    public static function ajax_search_students()
    {
        check_ajax_referer('azac_search_students', 'nonce');
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_text_field($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $biz = isset($_POST['biz']) ? sanitize_text_field($_POST['biz']) : '';

        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $args = [
            'post_type' => 'az_student',
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ];

        // Meta query for linking to users matching criteria
        $meta_query = [];
        $user_ids = [];

        // If searching by user fields (email, phone, biz), we find users first
        if ($email || $phone || $biz) {
            $u_args = [
                'number' => -1,
                'fields' => 'ID',
            ];
            $u_meta = [];
            if ($email) {
                $u_args['search'] = '*' . $email . '*';
                $u_args['search_columns'] = ['user_email'];
            }
            if ($phone) {
                $u_meta[] = [
                    'key' => 'billing_phone', // Common phone key, or use 'phone'
                    'value' => $phone,
                    'compare' => 'LIKE'
                ];
            }
            if ($biz) {
                $u_meta[] = [
                    'key' => 'az_business_field',
                    'value' => $biz,
                    'compare' => 'LIKE'
                ];
            }
            if (!empty($u_meta)) {
                $u_args['meta_query'] = $u_meta;
            }

            $found_users = get_users($u_args);
            if (empty($found_users)) {
                // If specific user criteria provided but no users found, return empty
                wp_send_json_success(['results' => []]);
            }
            $user_ids = $found_users;
        }

        if (!empty($user_ids)) {
            $meta_query[] = [
                'key' => 'az_user_id',
                'value' => $user_ids,
                'compare' => 'IN'
            ];
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        if ($name) {
            $args['s'] = $name;
        }

        $posts = get_posts($args);
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
        $is_admin = in_array('administrator', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);

        if (!$is_admin && !$is_teacher) {
            wp_send_json_error(['message' => 'Capability'], 403);
        }

        if ($is_teacher && !$is_admin) {
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
        if (!$is_admin) {
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
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $u = new WP_User($user_id);
                    $u->set_role('az_student');
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
}
