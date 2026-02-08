<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Core_CPT
{
    public static function register()
    {
        add_action('init', [__CLASS__, 'register_cpt_class']);
        add_action('init', [__CLASS__, 'register_cpt_student']);
        add_action('init', [__CLASS__, 'add_rewrite_rules'], 11);
        add_action('init', [__CLASS__, 'ensure_rewrite_rules'], 99);
        add_filter('request', [__CLASS__, 'map_request']);
        add_filter('post_type_link', [__CLASS__, 'filter_class_permalink'], 10, 2);
        add_action('add_meta_boxes', [__CLASS__, 'add_class_meta_boxes']);
        add_action('add_meta_boxes', [__CLASS__, 'add_class_students_meta_box']);
        add_action('save_post_az_class', [__CLASS__, 'save_class_meta'], 10, 2);
        add_action('save_post_az_class', [__CLASS__, 'save_class_students_meta'], 10, 2);
        add_filter('manage_az_class_posts_columns', [__CLASS__, 'columns_az_class']);
        add_action('wp_ajax_azac_remove_student_from_class', [__CLASS__, 'ajax_remove_student_from_class']);
        add_action('wp_ajax_azac_add_students_to_class', [__CLASS__, 'ajax_add_students_to_class']);
        add_action('manage_az_class_posts_custom_column', [__CLASS__, 'column_content_az_class'], 10, 2);
        add_filter('manage_az_student_posts_columns', [__CLASS__, 'columns_az_student']);
        add_action('manage_az_student_posts_custom_column', [__CLASS__, 'column_content_az_student'], 10, 2);
    }
    public static function ajax_remove_student_from_class()
    {
        check_ajax_referer('azac_ajax_nonce', 'security');

        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;

        if (!$class_id || !$student_id) {
            wp_send_json_error(['message' => 'Invalid parameters'], 400);
        }

        if (!current_user_can('edit_post', $class_id)) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        // Update meta
        $current_students = get_post_meta($class_id, 'az_students', true);
        if (!is_array($current_students)) {
            $current_students = [];
        }

        // Filter out the student
        $new_students = array_diff($current_students, [$student_id]);

        // Re-index array
        $new_students = array_values($new_students);

        // Update DB
        update_post_meta($class_id, 'az_students', $new_students);
        update_post_meta($class_id, 'az_so_hoc_vien', count($new_students));

        // CLEANUP: Remove attendance history for this student
        if (class_exists('AzAC_Core_Attendance') && method_exists('AzAC_Core_Attendance', 'cleanup_student_attendance')) {
            AzAC_Core_Attendance::cleanup_student_attendance($student_id, $class_id);
        } else {
            // Fallback direct cleanup
            global $wpdb;
            $table_att = $wpdb->prefix . 'az_attendance';
            $wpdb->query($wpdb->prepare("DELETE FROM $table_att WHERE class_id = %d AND student_id = %d", $class_id, $student_id));
        }

        wp_send_json_success(['count' => count($new_students)]);
    }

    public static function ajax_add_students_to_class()
    {
        check_ajax_referer('azac_ajax_nonce', 'security');

        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $student_ids = isset($_POST['student_ids']) ? (array) $_POST['student_ids'] : [];
        $student_ids = array_map('absint', $student_ids);
        $student_ids = array_filter($student_ids);

        if (!$class_id || empty($student_ids)) {
            wp_send_json_error(['message' => 'Invalid parameters'], 400);
        }

        // Use edit_posts to allow Managers as requested, but also verify context
        if (!current_user_can('edit_posts') || !current_user_can('edit_post', $class_id)) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        // Update meta
        $current_students = get_post_meta($class_id, 'az_students', true);
        if (!is_array($current_students)) {
            $current_students = [];
        }

        // Merge and Unique
        $new_students = array_merge($current_students, $student_ids);
        $new_students = array_unique($new_students);
        $new_students = array_values($new_students);

        // Update DB
        update_post_meta($class_id, 'az_students', $new_students);
        update_post_meta($class_id, 'az_so_hoc_vien', count($new_students));

        wp_send_json_success(['count' => count($new_students)]);
    }

    public static function register_cpt_class()
    {
        $labels = [
            'name' => 'Lớp học',
            'singular_name' => 'Lớp học',
            'menu_name' => 'Lớp học',
            'name_admin_bar' => 'Lớp học',
            'add_new' => 'Thêm lớp',
            'add_new_item' => 'Thêm lớp học mới',
            'new_item' => 'Lớp học mới',
            'edit_item' => 'Chỉnh sửa lớp học',
            'view_item' => 'Xem lớp học',
            'all_items' => 'Tất cả lớp học',
            'search_items' => 'Tìm lớp học',
            'not_found' => 'Không tìm thấy',
            'not_found_in_trash' => 'Không có trong thùng rác',
        ];
        $args = [
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'lop-hoc',
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'capability_type' => 'post',
            'rewrite' => ['slug' => 'lop-hoc', 'with_front' => false],
            'publicly_queryable' => true,
            'query_var' => 'az_class',
        ];
        register_post_type('az_class', $args);
        register_post_meta('az_class', 'az_giang_vien', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_tong_so_buoi', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_so_hoc_vien', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_students', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function ($value) {
                $ids = is_array($value) ? array_map('absint', $value) : [];
                return array_values(array_filter($ids));
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_teacher_user', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta('az_class', 'az_sessions', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => function ($value) {
                $items = is_array($value) ? $value : [];
                $out = [];
                foreach ($items as $it) {
                    $d = isset($it['date']) ? sanitize_text_field($it['date']) : '';
                    $t = isset($it['time']) ? sanitize_text_field($it['time']) : '';
                    if ($d)
                        $out[] = ['date' => $d, 'time' => $t];
                }
                usort($out, function ($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });
                return $out;
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
    public static function columns_az_class($cols)
    {
        $new = [];
        if (isset($cols['cb']))
            $new['cb'] = $cols['cb'];
        $new['title'] = 'Tên lớp';
        $new['az_teacher'] = 'Giảng viên';
        $new['az_sessions'] = 'Tổng buổi';
        $new['az_students'] = 'Sĩ số';
        $new['date'] = isset($cols['date']) ? $cols['date'] : 'Ngày';
        return $new;
    }
    public static function column_content_az_class($column, $post_id)
    {
        if ($column === 'az_teacher') {
            $teacher_name = get_post_meta($post_id, 'az_giang_vien', true);
            if (!$teacher_name) {
                $uid = intval(get_post_meta($post_id, 'az_teacher_user', true));
                if ($uid) {
                    $u = get_userdata($uid);
                    if ($u)
                        $teacher_name = $u->display_name ?: $u->user_login;
                }
            }
            echo '<span class="azac-badge">' . esc_html($teacher_name ?: 'Chưa gán') . '</span>';
        } elseif ($column === 'az_sessions') {
            $tsb = intval(get_post_meta($post_id, 'az_tong_so_buoi', true));
            echo '<span class="azac-badge">' . esc_html($tsb) . '</span>';
        } elseif ($column === 'az_students') {
            $shv = intval(get_post_meta($post_id, 'az_so_hoc_vien', true));
            echo '<span class="azac-badge">' . esc_html($shv) . '</span>';
        }
    }
    public static function columns_az_student($cols)
    {
        $new = [];
        if (isset($cols['cb']))
            $new['cb'] = $cols['cb'];
        $new['title'] = 'Họ và Tên';
        $new['az_user'] = 'Tài khoản';
        $new['date'] = isset($cols['date']) ? $cols['date'] : 'Ngày';
        return $new;
    }
    public static function column_content_az_student($column, $post_id)
    {
        if ($column === 'az_user') {
            $uid = intval(get_post_meta($post_id, 'az_user_id', true));
            if ($uid) {
                $u = get_userdata($uid);
                if ($u) {
                    $label = ($u->display_name ?: $u->user_login);
                    $email = $u->user_email ?: '';
                    echo '<span class="azac-badge">' . esc_html($label) . '</span> ' . esc_html($email);
                    return;
                }
            }
            echo '<span class="azac-badge">Chưa liên kết</span>';
        }
    }

    public static function register_cpt_student()
    {
        $labels = [
            'name' => 'Học viên',
            'singular_name' => 'Học viên',
            'menu_name' => 'Học viên',
            'add_new_item' => 'Thêm học viên',
            'edit_item' => 'Chỉnh sửa học viên',
        ];
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-id',
        ];
        register_post_meta('az_student', 'az_user_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_type('az_student', $args);
    }
    public static function add_rewrite_rules()
    {
        add_rewrite_rule('^lop-hoc/([^/]+)/?$', 'index.php?az_class=$matches[1]', 'top');
        add_rewrite_rule('^lop-hoc/?$', 'index.php?post_type=az_class', 'top');
    }
    public static function ensure_rewrite_rules()
    {
        $rules = get_option('rewrite_rules');
        $ok = false;
        if (is_array($rules)) {
            foreach ($rules as $k => $v) {
                if (strpos($k, 'lop-hoc/') !== false) {
                    $ok = true;
                    break;
                }
            }
        }
        if (!$ok) {
            flush_rewrite_rules();
        }
    }
    public static function filter_class_permalink($permalink, $post)
    {
        if ($post && $post->post_type === 'az_class') {
            return home_url('lop-hoc/' . $post->post_name . '/');
        }
        return $permalink;
    }
    public static function map_request($vars)
    {
        if (isset($vars['pagename']) && is_string($vars['pagename'])) {
            if (preg_match('#^lop-hoc/([^/]+)$#', $vars['pagename'], $m)) {
                $vars['post_type'] = 'az_class';
                $vars['name'] = $m[1];
                unset($vars['pagename']);
            }
        }
        return $vars;
    }

    public static function add_class_meta_boxes()
    {
        add_meta_box(
            'azac_class_meta',
            'Thông tin lớp học',
            [__CLASS__, 'render_class_meta_box'],
            'az_class',
            'side',
            'default'
        );
    }
    public static function render_class_meta_box($post)
    {
        wp_nonce_field('azac_class_meta_box', 'azac_class_meta_nonce');
        $teacher_user_id = intval(get_post_meta($post->ID, 'az_teacher_user', true));
        $giang_vien = '';
        if ($teacher_user_id) {
            $u = get_userdata($teacher_user_id);
            if ($u) {
                $giang_vien = $u->display_name ?: $u->user_login;
            }
        }
        if (!$giang_vien) {
            $giang_vien = get_post_meta($post->ID, 'az_giang_vien', true);
        }
        $tong_so_buoi = get_post_meta($post->ID, 'az_tong_so_buoi', true);
        $so_hoc_vien = get_post_meta($post->ID, 'az_so_hoc_vien', true);
        $teacher_user = $teacher_user_id ?: get_post_meta($post->ID, 'az_teacher_user', true);
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $disabled_admin = $is_admin ? '' : ' disabled';
        $disabled_name = ' disabled';
        echo '<p><label for="az_giang_vien">Giảng viên</label><br />';
        echo '<input type="text" id="az_giang_vien" name="az_giang_vien" class="regular-text" value="' . esc_attr($giang_vien) . '"' . $disabled_name . ' /></p>';
        echo '<p><label for="az_tong_so_buoi">Tổng số buổi</label><br />';
        echo '<input type="number" id="az_tong_so_buoi" name="az_tong_so_buoi" min="0" value="' . esc_attr($tong_so_buoi) . '"' . $disabled_admin . ' /></p>';
        echo '<p><label for="az_so_hoc_vien">Số học viên</label><br />';
        echo '<input type="number" id="az_so_hoc_vien" name="az_so_hoc_vien" min="0" value="' . esc_attr($so_hoc_vien) . '"' . $disabled_admin . ' /></p>';
        $teachers = get_users(['role' => 'az_teacher']);
        echo '<p><label for="az_teacher_user">Giảng viên (User)</label><br />';
        echo '<select id="az_teacher_user" name="az_teacher_user"' . $disabled_admin . '>';
        echo '<option value="">-- Chọn giảng viên --</option>';
        foreach ($teachers as $t) {
            $selected = selected(intval($teacher_user), intval($t->ID), false);
            echo '<option value="' . esc_attr($t->ID) . '" ' . $selected . '>' . esc_html($t->display_name) . '</option>';
        }
        echo '</select></p>';
        echo '<script>(function(){var sel=document.getElementById("az_teacher_user");var name=document.getElementById("az_giang_vien");if(sel&&name){sel.addEventListener("change",function(){var txt="";var opt=sel.options[sel.selectedIndex];if(opt){txt=opt.text;}name.value=txt;});}})();</script>';
    }
    public static function add_class_students_meta_box()
    {
        add_meta_box(
            'azac_class_students',
            'Danh sách học viên',
            [__CLASS__, 'render_class_students_meta_box'],
            'az_class',
            'normal',
            'default'
        );
    }
    public static function render_class_students_meta_box($post)
    {
        wp_nonce_field('azac_class_students_meta_box', 'azac_class_students_meta_nonce');
        $selected = get_post_meta($post->ID, 'az_students', true);
        $selected = is_array($selected) ? array_map('absint', $selected) : [];
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_manager = in_array('az_manager', $user->roles, true);
        $can_manage = $is_admin || $is_manager;

        // Prepare student data for the table
        $student_data = [];
        if (!empty($selected)) {
            $args = [
                'post_type' => 'az_student',
                'post__in' => $selected,
                'numberposts' => -1,
                'orderby' => 'post__in'
            ];
            $posts = get_posts($args);
            foreach ($posts as $p) {
                $uid = get_post_meta($p->ID, 'az_user_id', true);
                $email = '';
                $phone = '';
                $biz = '';
                if ($uid) {
                    $u = get_userdata($uid);
                    if ($u) {
                        $email = $u->user_email;
                        $phone = get_user_meta($uid, 'billing_phone', true) ?: get_user_meta($uid, 'phone', true);
                        $biz = get_user_meta($uid, 'az_business_field', true);
                    }
                }
                $student_data[] = [
                    'id' => $p->ID,
                    'name' => $p->post_title,
                    'email' => $email,
                    'phone' => $phone,
                    'biz' => $biz
                ];
            }
        }

        if ($can_manage) {
            // Search UI
            echo '<div class="azac-search-box">';
            echo '<h4 style="margin:0 0 10px 0;">Tìm kiếm & Thêm học viên</h4>';

            // Flex container for single row layout
            echo '<div class="azac-search-inputs" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';

            // Name Input
            echo '<input type="text" id="azac_search_name" class="regular-text" style="flex: 1; min-width: 150px;" placeholder="Họ tên" />';

            // Phone Input
            echo '<input type="text" id="azac_search_phone" class="regular-text" style="flex: 1; min-width: 150px;" placeholder="Số điện thoại" />';

            // Button inline
            echo '<button type="button" class="button button-primary" id="azac_search_btn" style="flex: 0 0 auto;">Tìm kiếm</button>';

            echo '</div>';

            echo '<div id="azac_search_results" class="azac-search-results" style="margin-top: 10px;"></div>';
            echo '</div>';

            // Available Students List (New)
            echo '<div class="azac-available-container">';
            echo '<h4 style="margin:0 0 10px 0;">Chọn từ danh sách (Học viên chưa vào lớp)</h4>';

            // Get students not in $selected
            // NEW LOGIC: Query Users directly, then map/create CPTs
            // 1. Get WP Users with role 'az_student'
            $user_args = [
                'role' => 'az_student',
                'number' => 100, // Limit
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => 'all_with_meta'
            ];
            
            // Filter out users who are already linked to CPTs that are in $selected
            // But we don't know the User->CPT mapping yet. 
            // So we fetch users, then filter.
            // Optimization: We can't easily exclude users in $selected in WP_User_Query 
            // because $selected contains CPT IDs, not User IDs.
            
            $users = get_users($user_args);
            $available_posts = [];
            
            foreach ($users as $u) {
                // Check if CPT exists for this user
                // We use a direct query for performance inside loop? 
                // Or better: fetch all CPTs for these users in one go before loop?
                // Given limit 100, one-by-one is okay, but batch is better.
                // For simplicity and robustness (handling "create if missing"), let's check one by one or cached.
                
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
                
                if ($cpt_id) {
                    // CPT exists. Check if it's in the class ($selected)
                    if (in_array($cpt_id, $selected)) {
                        continue; // Already in class
                    }
                } else {
                    // CPT does not exist. Create it!
                    $cpt_id = wp_insert_post([
                        'post_type' => 'az_student',
                        'post_title' => $u->display_name ?: $u->user_login,
                        'post_status' => 'publish'
                    ]);
                    if ($cpt_id && !is_wp_error($cpt_id)) {
                        update_post_meta($cpt_id, 'az_user_id', $u->ID);
                    } else {
                        continue; // Failed to create
                    }
                }
                
                // Create a dummy post object for compatibility with the loop below
                $p = get_post($cpt_id);
                if ($p) {
                    $available_posts[] = $p;
                }
            }


            if ($available_posts) {
                echo '<div class="azac-select-all-wrap"><label><input type="checkbox" id="azac_select_all_available"> Chọn tất cả</label></div>';
                echo '<div class="azac-available-box">';
                echo '<ul class="azac-available-list">';
                foreach ($available_posts as $ap) {
                    $uid = get_post_meta($ap->ID, 'az_user_id', true);
                    $email = '';
                    $phone = '';
                    $biz = '';
                    if ($uid) {
                        $u = get_userdata($uid);
                        if ($u) {
                            // Filter: Only allow 'az_student' role
                            if (!in_array('az_student', (array) $u->roles)) {
                                continue;
                            }
                            $email = $u->user_email;
                            $phone = get_user_meta($uid, 'billing_phone', true) ?: get_user_meta($uid, 'phone', true);
                            $biz = get_user_meta($uid, 'az_business_field', true);
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    // JSON data for JS to grab when adding
                    $s_data = htmlspecialchars(json_encode([
                        'id' => $ap->ID,
                        'name' => $ap->post_title,
                        'email' => $email,
                        'phone' => $phone,
                        'biz' => $biz
                    ]), ENT_QUOTES, 'UTF-8');

                    echo '<li class="azac-available-item">';
                    echo '<label><input type="checkbox" class="azac-available-cb" value="' . $ap->ID . '" data-info="' . $s_data . '"> ';
                    echo '<strong>' . esc_html($ap->post_title) . '</strong>';
                    if ($email)
                        echo ' - ' . esc_html($email);
                    echo '</label>';
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';
                echo '<div style="margin-top:10px;">';
                echo '<button type="button" class="button button-secondary" id="azac_add_selected_btn">Thêm đã chọn</button>';
                echo '</div>';

                // JS for Select All
                echo '<script>
                jQuery(function($){
                    $("#azac_select_all_available").on("change", function(){
                        $(".azac-available-cb").prop("checked", $(this).prop("checked"));
                    });
                });
                </script>';
            } else {
                echo '<p>Không có học viên nào khả dụng hoặc tất cả đã ở trong lớp.</p>';
            }
            echo '</div>';

        }

        // List Table
        echo '<table class="wp-list-table widefat fixed striped table-view-list azac-students-table">';
        echo '<thead><tr>';
        echo '<th class="manage-column column-name">Họ tên</th>';
        echo '<th class="manage-column">Email</th>';
        echo '<th class="manage-column">Số điện thoại</th>';
        echo '<th class="manage-column">Lĩnh vực KD</th>';
        if ($can_manage)
            echo '<th class="manage-column column-action" style="width:60px;">Xóa</th>';
        echo '</tr></thead>';
        echo '<tbody id="azac_students_tbody">';

        if (empty($student_data)) {
            echo '<tr class="no-items"><td colspan="5">Chưa có học viên nào.</td></tr>';
        } else {
            foreach ($student_data as $s) {
                echo '<tr data-id="' . esc_attr($s['id']) . '">';
                echo '<td>' . esc_html($s['name']) . '<input type="hidden" name="az_students[]" value="' . esc_attr($s['id']) . '"></td>';
                echo '<td>' . esc_html($s['email']) . '</td>';
                echo '<td>' . esc_html($s['phone']) . '</td>';
                echo '<td>' . esc_html($s['biz']) . '</td>';
                if ($can_manage) {
                    echo '<td><button type="button" class="button-link azac-remove-student-btn" style="color:#b32d2e;">Xóa</button></td>';
                }
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        // Count hidden input
        echo '<input type="hidden" id="azac_student_count_check" value="' . count($student_data) . '">';
    }
    public static function save_class_meta($post_id, $post)
    {
        if (!isset($_POST['azac_class_meta_nonce']) || !wp_verify_nonce($_POST['azac_class_meta_nonce'], 'azac_class_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_type !== 'az_class') {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['az_tong_so_buoi'])) {
            update_post_meta($post_id, 'az_tong_so_buoi', absint($_POST['az_tong_so_buoi']));
        }
        if (isset($_POST['az_so_hoc_vien'])) {
            update_post_meta($post_id, 'az_so_hoc_vien', absint($_POST['az_so_hoc_vien']));
        }
        if (isset($_POST['az_teacher_user'])) {
            update_post_meta($post_id, 'az_teacher_user', absint($_POST['az_teacher_user']));
            $tid = absint($_POST['az_teacher_user']);
            if ($tid) {
                $u = get_userdata($tid);
                if ($u) {
                    $label = $u->display_name ?: $u->user_login;
                    update_post_meta($post_id, 'az_giang_vien', sanitize_text_field($label));
                }
            }
        }
    }
    public static function save_class_students_meta($post_id, $post)
    {
        if (!isset($_POST['azac_class_students_meta_nonce']) || !wp_verify_nonce($_POST['azac_class_students_meta_nonce'], 'azac_class_students_meta_box')) {
            return;
        }
        if ($post->post_type !== 'az_class') {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            return;
        }
        $ids = isset($_POST['az_students']) && is_array($_POST['az_students']) ? array_map('absint', $_POST['az_students']) : [];

        // CLEANUP CHECK: Identify removed students
        $old_students = get_post_meta($post_id, 'az_students', true);
        if (!is_array($old_students))
            $old_students = [];

        $removed_ids = array_diff($old_students, $ids);

        update_post_meta($post_id, 'az_students', array_values(array_filter($ids)));
        update_post_meta($post_id, 'az_so_hoc_vien', count($ids));

        // Execute Cleanup
        if (!empty($removed_ids)) {
            // Try method first, fallback to direct SQL to ensure cleanup happens
            if (class_exists('AzAC_Core_Attendance') && method_exists('AzAC_Core_Attendance', 'cleanup_student_attendance')) {
                foreach ($removed_ids as $sid) {
                    AzAC_Core_Attendance::cleanup_student_attendance($sid, $post_id);
                }
            } else {
                global $wpdb;
                $table_att = $wpdb->prefix . 'az_attendance';
                $ids_sql = implode(',', array_map('absint', $removed_ids));
                if ($ids_sql) {
                    $wpdb->query($wpdb->prepare("DELETE FROM $table_att WHERE class_id = %d AND student_id IN ($ids_sql)", $post_id));
                }
            }
        }
    }
}
