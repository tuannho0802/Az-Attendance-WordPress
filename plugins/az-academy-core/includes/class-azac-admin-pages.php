<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Pages
{
    public static function register_admin_pages()
    {
        $user = wp_get_current_user();

        add_menu_page(
            'Quản lý điểm danh',
            'Quản lý điểm danh',
            'read',
            'azac-attendance',
            [__CLASS__, 'render_attendance_list_page'],
            'dashicons-yes',
            0
        );
        add_menu_page(
            'Lớp học',
            'Lớp học',
            'read',
            'azac-classes-list',
            [__CLASS__, 'render_classes_list_page'],
            'dashicons-book-alt',
            0
        );

        $is_manager = in_array('az_manager', (array) $user->roles);

        if (!in_array('az_student', (array) $user->roles)) {
            add_menu_page(
                'Học viên',
                'Học viên',
                'read',
                'azac-students-list',
                [__CLASS__, 'render_students_list_page'],
                'dashicons-id',
                0
            );
        }

        add_menu_page(
            'Reviews',
            'Reviews',
            'manage_options', // Admin & Manager
            'azac-reviews',
            [__CLASS__, 'render_reviews_page'],
            'dashicons-chart-bar',
            0
        );

        if (!in_array('az_student', (array) $user->roles)) {
            add_menu_page(
                'Quản lý Học viên',
                'Quản lý Học viên',
                'read', // Allow teachers (validated inside)
                'azac-manage-students',
                [__CLASS__, 'render_manage_students_page'],
                'dashicons-groups',
                0
            );
        }
        add_menu_page(
            'Quản lý Giảng viên',
            'Quản lý Giảng viên',
            'manage_options', // Admin & Manager
            'azac-manage-teachers',
            [__CLASS__, 'render_manage_teachers_page'],
            'dashicons-admin-users',
            0
        );

        add_menu_page(
            'Chấm công Giảng viên',
            'Chấm công Giảng viên',
            'edit_published_posts', // Only teachers and admins
            'azac-teacher-attendance',
            [__CLASS__, 'render_teacher_attendance_page'],
            'dashicons-calendar-alt',
            0
        );
        remove_menu_page('edit.php?post_type=az_class');
        remove_menu_page('edit.php?post_type=az_student');
    }
    public static function render_attendance_list_page()
    {
        echo '<div class="wrap"><h1>Quản lý điểm danh</h1>';
        $user = wp_get_current_user();
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $is_admin = current_user_can('manage_options');
        $is_student = in_array('az_student', $user->roles, true);
        echo '<div class="azac-tabs" style="margin-bottom:10px;">';
        echo '<button class="button azac-tab-btn" data-target="#azac-tab-sessions">Buổi học</button>';
        if ($is_student) {
            echo ' <button class="button azac-tab-btn" data-target="#azac-tab-stats">Thống kê điểm danh</button>';
        }
        echo '</div>';
        if ($is_teacher || $is_admin || $is_student) {
            echo '<div id="azac-tab-sessions" class="azac-tab active">';

            // Main Layout Wrapper
            echo '<div class="azac-layout-wrapper" style="background:#fff; padding:20px; border:1px solid #c3c4c7; box-sizing:border-box;">';

            // New Toolbar
            echo '<div class="azac-session-filters-toolbar" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:15px;">';

            // Bulk Actions
            if (current_user_can('delete_users')) {
                echo '<div style="display:flex; gap:5px; align-items:center;">';
                echo '<select id="azac-bulk-action-selector-top">';
                echo '<option value="-1">Hành động hàng loạt</option>';
                echo '<option value="delete">Xóa các buổi đã chọn</option>';
                echo '</select>';
                echo '<input type="submit" id="azac-do-bulk-action" class="button action" value="Áp dụng">';
                echo '</div>';
            }

            // Class Filter (Select2)
            echo '<div style="flex:1; min-width:200px;">';
            echo '<select id="azac-filter-class" class="azac-select2" style="width:100%">';
            echo '<option value="">Tất cả lớp học</option>';
            echo '</select>';
            echo '</div>';

            // Date Range
            echo '<div style="display:flex; align-items:center; gap:5px;">';
            echo '<input type="text" id="azac-filter-date-start" placeholder="Từ ngày" class="regular-text" style="width:110px">';
            echo '<span>-</span>';
            echo '<input type="text" id="azac-filter-date-end" placeholder="Đến ngày" class="regular-text" style="width:110px">';
            echo '</div>';

            // Sort
            echo '<div>';
            echo '<select id="azac-filter-sort" style="height:30px; line-height:1;">';
            echo '<option value="date_desc">Mới nhất</option>';
            echo '<option value="date_asc">Cũ nhất</option>';
            echo '<option value="rate_asc">Tỷ lệ vắng cao nhất</option>';
            echo '</select>';
            echo '</div>';

            // Search
            echo '<div>';
            echo '<input type="text" id="azac-filter-search" placeholder="Tìm kiếm..." style="height:30px;">';
            echo '</div>';

            echo '</div>'; // End Toolbar

            // Table
            echo '<div class="azac-table-responsive" style="overflow-x:auto;">';
            echo '<table class="wp-list-table widefat fixed striped table-view-list">';
            echo '<thead>';
            echo '<tr>';
            if ($is_admin) {
                echo '<th class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></th>';
            }
            echo '<th>Lớp</th>';
            echo '<th style="width: 8%; text-align:center;">Buổi</th>';
            echo '<th style="width: 17%;">Thời gian</th>';
            echo '<th style="width: 15%;">Trạng thái ngày dạy</th>';
            echo '<th style="width: 15%;">Tỷ lệ có mặt</th>';
            echo '<th style="width: 10%;">Trạng thái</th>';
            echo '<th style="width: 10%;">Hành động</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody id="azac-sessions-table-body">';
            echo '<tr><td colspan="7">Đang tải danh sách buổi học...</td></tr>';
            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            echo '<div id="azac-sessions-pagination"></div>';
            echo '</div>'; // End azac-layout-wrapper
            echo '</div>'; // End #azac-tab-sessions

            if ($is_student) {
                echo '<div id="azac-tab-stats" class="azac-tab">';
                echo '<div id="azac-stats-grid" class="azac-grid">';
                echo '<div class="azac-card"><div class="azac-card-title">Đang tải thống kê...</div></div>';
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    public static function render_classes_list_page()
    {
        $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        if ($class_id) {
            self::render_class_dashboard_page();
            return;
        }
        echo '<div class="wrap"><h1>Lớp học</h1>';
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $is_manager = in_array('az_manager', (array) $user->roles);
        $is_teacher = in_array('az_teacher', $user->roles, true);

        // Search Logic
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Search Form
        echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px">';
        echo '<input type="hidden" name="page" value="azac-classes-list">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Tìm kiếm lớp..." class="regular-text" style="width:auto">';
        echo '<button type="submit" class="button button-secondary">Tìm kiếm</button>';
        if ($search) {
            echo '<a href="' . admin_url('admin.php?page=azac-classes-list') . '" class="button">Xóa lọc</a>';
        }
        echo '</form>';

        $args = [
            'post_type' => 'az_class',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            's' => $search, // WP_Query supports 's'
        ];
        if ($is_admin || $is_teacher) {
            $args['post_status'] = ['publish', 'pending'];
        }
        $classes = get_posts($args);
        if ($is_teacher) {
            $classes = array_filter($classes, function ($c) use ($user) {
                $teacher_user = intval(get_post_meta($c->ID, 'az_teacher_user', true));
                return $teacher_user === intval($user->ID);
            });
        } elseif (in_array('az_student', $user->roles, true)) {
            $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
            $classes = array_filter($classes, function ($c) use ($student_post_id) {
                $ids = get_post_meta($c->ID, 'az_students', true);
                $ids = is_array($ids) ? array_map('absint', $ids) : [];
                return in_array($student_post_id, $ids, true);
            });
        }

        $classes = array_values($classes);
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($classes);
        $total_pages = ceil($total_items / $per_page);
        $paged_classes = array_slice($classes, ($current_page - 1) * $per_page, $per_page);

        $is_admin = in_array('administrator', $user->roles, true);
        $is_manager = in_array('az_manager', (array) $user->roles);
        if ($is_admin || $is_manager) {
            echo '<div class="azac-inline-create">';
            echo '<input type="text" id="azac_new_class_title" class="regular-text" placeholder="Tên lớp học" />';
            $teachers = get_users(['role' => 'az_teacher']);
            echo '<select id="azac_new_class_teacher" class="regular-text">';
            echo '<option value="">-- Chọn giảng viên --</option>';
            foreach ($teachers as $t) {
                echo '<option value="' . esc_attr($t->ID) . '">' . esc_html($t->display_name ?: $t->user_login) . '</option>';
            }
            echo '</select>';
            echo '<button class="button button-primary" id="azac_create_class_btn">Tạo lớp</button>';
            echo '</div>';
        }
        echo '<div class="azac-grid">';
        $palette = AzAC_Core_Admin::$palette;
        foreach ($paged_classes as $c) {
            $gv = get_post_meta($c->ID, 'az_giang_vien', true);
            $tsb = intval(get_post_meta($c->ID, 'az_tong_so_buoi', true));
            $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
            $current_sessions = count(AzAC_Core_Sessions::get_class_sessions($c->ID));
            $progress_total = max(0, $tsb);
            $progress_done = $current_sessions;
            $progress_percent = ($progress_total > 0) ? min(100, round(($progress_done / $progress_total) * 100)) : 0;
            $status = get_post_status($c->ID);
            $is_pending = ($status === 'pending');
            $link_dashboard = admin_url('admin.php?page=azac-classes-list&class_id=' . $c->ID);
            $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
            $link_view = get_permalink($c->ID);

            // Hash Logic for Card Title
            $hash = 0;
            $c_name = $c->post_title;
            for ($i = 0; $i < strlen($c_name); $i++) {
                $hash += ord($c_name[$i]);
            }
            $color = $palette[$hash % count($palette)];

            echo '<div class="azac-card">';
            echo '<div class="azac-card-title" style="background-color: ' . $color . '; color: #fff; padding: 10px; border-radius: 6px;">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '" style="border:1px solid rgba(255,255,255,0.5);">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
            echo '<div class="azac-card-body">';
            echo '<div class="azac-meta-list">';
            echo '<div class="azac-meta-item"><span class="azac-meta-label">Giảng viên</span><span class="azac-meta-value">' . esc_html($gv ?: 'Chưa gán') . '</span></div>';
            echo '<div class="azac-meta-item"><span class="azac-meta-label">Tổng số buổi</span><span class="azac-meta-value">' . esc_html($tsb) . '</span></div>';
            echo '<div class="azac-meta-item"><span class="azac-meta-label">Số học viên</span><span class="azac-meta-value">' . esc_html($shv) . '</span></div>';
            echo '<div class="azac-meta-item"><span class="azac-meta-label">Số buổi hiện tại</span><span class="azac-meta-value">' . esc_html($progress_done . '/' . $progress_total) . '</span></div>';
            echo '</div>';
            echo '<div class="azac-progress"><div class="azac-progress-bar" data-cid="' . esc_attr($c->ID) . '" style="width:' . esc_attr($progress_percent) . '%"></div></div>';
            echo '</div>';
            echo '<div class="azac-card-actions azac-actions--classes">';
            if ($is_admin || $is_manager) {
                if ($is_pending) {
                    echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                } else {
                    echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                }
                echo '<a class="button button-secondary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                if (!$is_pending) {
                    echo '<a class="button button-info" href="' . esc_url($link_view) . '">Vào lớp</a> ';
                }
                if ($is_admin) {
                    echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
                }
            } elseif ($is_teacher) {
                if (!$is_pending) {
                    echo '<a class="button button-secondary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                    echo '<a class="button button-info" href="' . esc_url($link_view) . '">Vào lớp</a>';
                }
            } else {
                echo '<a class="button button-info" href="' . esc_url($link_view) . '">Xem lớp</a>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        AzAC_Core_Helper::render_pagination($current_page, $total_pages);
        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded",function(){';
        echo 'var bars=document.querySelectorAll(".azac-progress-bar");';
        echo 'bars.forEach(function(bar){var cid=bar.getAttribute("data-cid");if(cid && window.AZACU && typeof window.AZACU.getClassColor==="function"){bar.style.backgroundColor=window.AZACU.getClassColor(cid);}});';
        echo '});';
        echo '</script>';
        echo '</div>';
    }
    public static function render_students_list_page()
    {
        $user = wp_get_current_user();

        // Strict Permission: Block Student Role
        if (in_array('az_student', $user->roles, true)) {
            wp_redirect(admin_url());
            exit;
        }

        echo '<div class="wrap azac-admin-teal"><h1>Học viên</h1>';

        // Search Logic
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Search Form
        echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px">';
        echo '<input type="hidden" name="page" value="azac-students-list">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Tìm tên hoặc SĐT..." class="regular-text" style="width:auto">';
        echo '<button type="submit" class="button button-secondary">Tìm kiếm</button>';
        if ($search) {
            echo '<a href="' . admin_url('admin.php?page=azac-students-list') . '" class="button">Xóa lọc</a>';
        }
        echo '</form>';

        // Allowed Users Logic (Teacher Restriction)
        $allowed_user_ids = null;
        if (in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            $allowed_user_ids = [];
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => $user->ID,
                'post_status' => ['publish', 'pending'],
                'fields' => 'ids'
            ]);

            $cpt_ids = [];
            foreach ($classes as $cid) {
                $s_list = get_post_meta($cid, 'az_students', true);
                if (is_array($s_list)) {
                    $cpt_ids = array_merge($cpt_ids, array_map('absint', $s_list));
                }
            }
            $cpt_ids = array_unique($cpt_ids);

            if (!empty($cpt_ids)) {
                global $wpdb;
                $placeholders = implode(',', array_fill(0, count($cpt_ids), '%d'));
                $sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'az_user_id' AND post_id IN ($placeholders)";
                $prepared = $wpdb->prepare($sql, $cpt_ids);
                $uids = $wpdb->get_col($prepared);
                if ($uids) {
                    $allowed_user_ids = array_map('absint', $uids);
                }
            }

            if (empty($allowed_user_ids)) {
                $allowed_user_ids = [0]; // Force no results
            }
        }

        // Pagination Setup
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        // Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'display_name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';

        // Build Query Args
        $args = [
            'role' => 'az_student',
            'number' => $per_page,
            'paged' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
        ];

        // Search Logic
        if ($search) {
            // 1. Search by Name/Email/Login
            $q1_args = [
                'role' => 'az_student',
                'search' => '*' . $search . '*',
                'fields' => 'ID'
            ];
            $q1 = new WP_User_Query($q1_args);
            $ids1 = $q1->get_results();

            // 2. Search by Phone (Meta)
            $q2_args = [
                'role' => 'az_student',
                'meta_key' => 'az_phone',
                'meta_value' => $search,
                'meta_compare' => 'LIKE',
                'fields' => 'ID'
            ];
            $q2 = new WP_User_Query($q2_args);
            $ids2 = $q2->get_results();

            $merged_ids = array_unique(array_merge($ids1, $ids2));

            // Intersect with Allowed Users if Teacher
            if ($allowed_user_ids !== null) {
                $merged_ids = array_intersect($merged_ids, $allowed_user_ids);
            }

            if (empty($merged_ids)) {
                $args['include'] = [0];
            } else {
                $args['include'] = $merged_ids;
            }
        } else {
            if ($allowed_user_ids !== null) {
                $args['include'] = $allowed_user_ids;
            }
        }

        // Execute Main Query
        global $wpdb;
        $students = [];
        $total_items = 0;

        if ($orderby === 'attendance') {
            // Sort by Attendance (Custom Logic)
            $args['number'] = -1; // Fetch all for sorting
            $args['fields'] = 'ID';
            $user_query = new WP_User_Query($args);
            $all_uids = $user_query->get_results();
            $total_items = count($all_uids);

            if ($all_uids) {
                // 1. Map User ID -> CPT ID
                $placeholders = implode(',', array_fill(0, count($all_uids), '%d'));
                $sql_cpt = "SELECT pm.meta_value as user_id, p.ID as cpt_id FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE p.post_type = 'az_student' AND p.post_status = 'publish' AND pm.meta_key = 'az_user_id' AND pm.meta_value IN ($placeholders)";
                $cpt_results = $wpdb->get_results($wpdb->prepare($sql_cpt, $all_uids));
                $cpt_map = [];
                foreach ($cpt_results as $r)
                    $cpt_map[$r->user_id] = $r->cpt_id;

                // 2. Map CPT ID -> Attendance Score
                $cpt_ids = array_values($cpt_map);
                $stats_map = [];
                if ($cpt_ids) {
                    $cpt_placeholders = implode(',', array_fill(0, count($cpt_ids), '%d'));
                    $sql_stats = "SELECT student_id, COUNT(*) as total, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as present FROM {$wpdb->prefix}az_attendance WHERE student_id IN ($cpt_placeholders) GROUP BY student_id";
                    $stats_results = $wpdb->get_results($wpdb->prepare($sql_stats, $cpt_ids));
                    foreach ($stats_results as $r) {
                        $stats_map[$r->student_id] = ($r->total > 0) ? round(($r->present / $r->total) * 100) : 0;
                    }
                }

                // 3. Sort
                usort($all_uids, function ($uid_a, $uid_b) use ($cpt_map, $stats_map, $order) {
                    $cpt_a = isset($cpt_map[$uid_a]) ? $cpt_map[$uid_a] : 0;
                    $cpt_b = isset($cpt_map[$uid_b]) ? $cpt_map[$uid_b] : 0;
                    $score_a = isset($stats_map[$cpt_a]) ? $stats_map[$cpt_a] : 0;
                    $score_b = isset($stats_map[$cpt_b]) ? $stats_map[$cpt_b] : 0;

                    if ($score_a == $score_b)
                        return 0;
                    return ($order === 'ASC') ? ($score_a <=> $score_b) : ($score_b <=> $score_a);
                });

                // 4. Slice
                $offset = ($current_page - 1) * $per_page;
                $sliced_ids = array_slice($all_uids, $offset, $per_page);

                if ($sliced_ids) {
                    $students = get_users(['include' => $sliced_ids, 'orderby' => 'include']);
                }
            }
        } else {
            // Standard Query
            $user_query = new WP_User_Query($args);
            $students = $user_query->get_results();
            $total_items = $user_query->get_total();
        }

        $total_pages = ceil($total_items / $per_page);

        // Helper for Sort Links
        $base_url = remove_query_arg(['paged'], admin_url('admin.php?page=azac-students-list'));
        $sort_link = function ($col) use ($base_url, $orderby, $order) {
            $new_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
            return add_query_arg(['orderby' => $col, 'order' => $new_order], $base_url);
        };

        // Render Table
        if (empty($students)) {
            echo '<p>Không tìm thấy học viên nào.</p>';
        } else {
            // 1. Prepare Class Map (Get classes for students)
            $student_classes_map = [];
            $class_rows = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm.meta_value 
                FROM $wpdb->posts p 
                LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'az_students'
                WHERE p.post_type = 'az_class' AND p.post_status = 'publish'
            ");

            if ($class_rows) {
                foreach ($class_rows as $cr) {
                    $sids = maybe_unserialize($cr->meta_value);
                    if (is_array($sids)) {
                        foreach ($sids as $sid) {
                            if (!isset($student_classes_map[$sid])) {
                                $student_classes_map[$sid] = [];
                            }
                            $student_classes_map[$sid][] = $cr->post_title;
                        }
                    }
                }
            }

            // 2. Batch fetch CPT IDs & Attendance Stats
            $student_uids = wp_list_pluck($students, 'ID');
            $user_cpt_map = [];
            $attendance_map = [];

            if (!empty($student_uids)) {
                $placeholders = implode(',', array_fill(0, count($student_uids), '%d'));
                $sql = "
                    SELECT pm.meta_value as user_id, p.ID as cpt_id 
                    FROM $wpdb->postmeta pm
                    JOIN $wpdb->posts p ON pm.post_id = p.ID
                    WHERE p.post_type = 'az_student'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = 'az_user_id'
                    AND pm.meta_value IN ($placeholders)
                ";
                $prepared = $wpdb->prepare($sql, $student_uids);
                $results = $wpdb->get_results($prepared);
                $cpt_ids_for_stats = [];
                foreach ($results as $r) {
                    $user_cpt_map[$r->user_id] = $r->cpt_id;
                    $cpt_ids_for_stats[] = $r->cpt_id;
                }

                // Fetch Stats for current page students
                if ($cpt_ids_for_stats) {
                    $cpt_placeholders = implode(',', array_fill(0, count($cpt_ids_for_stats), '%d'));
                    $sql_stats = "SELECT student_id, COUNT(*) as total, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as present FROM {$wpdb->prefix}az_attendance WHERE student_id IN ($cpt_placeholders) GROUP BY student_id";
                    $stats_results = $wpdb->get_results($wpdb->prepare($sql_stats, $cpt_ids_for_stats));
                    foreach ($stats_results as $r) {
                        $attendance_map[$r->student_id] = ($r->total > 0) ? round(($r->present / $r->total) * 100) : 0;
                    }
                }
            }

            echo '<div style="overflow-x:auto;">';
            echo '<table class="wp-list-table widefat fixed striped table-view-list">';
            echo '<thead><tr>';
            echo '<th><a href="' . esc_url($sort_link('display_name')) . '" style="color:#fff;">Học viên ' . ($orderby == 'display_name' ? ($order == 'ASC' ? '▲' : '▼') : '') . '</a></th>';
            echo '<th><a href="' . esc_url($sort_link('email')) . '" style="color:#fff;">Email ' . ($orderby == 'email' ? ($order == 'ASC' ? '▲' : '▼') : '') . '</a></th>';
            echo '<th>Số điện thoại</th>';
            echo '<th>Lĩnh vực kinh doanh</th>';
            echo '<th>Trạng thái lớp</th>';
            echo '<th><a href="' . esc_url($sort_link('attendance')) . '" style="color:#fff;">Chuyên cần ' . ($orderby == 'attendance' ? ($order == 'ASC' ? '▲' : '▼') : '') . '</a></th>';
            echo '<th>Hành động</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($students as $u) {
                // Get CPT ID from Map
                $cpt_id = isset($user_cpt_map[$u->ID]) ? $user_cpt_map[$u->ID] : 0;

                $avatar_url = get_avatar_url($u->ID, ['size' => 200]);
                $phone = get_user_meta($u->ID, 'az_phone', true);
                $business_field = get_user_meta($u->ID, 'az_business_field', true);
                $registered_date = $u->user_registered;

                // Get Class Status
                $class_status = '<span style="color:#e74c3c">Chưa tham gia lớp học</span>';
                if ($cpt_id && isset($student_classes_map[$cpt_id]) && !empty($student_classes_map[$cpt_id])) {
                    $class_status = implode(', ', $student_classes_map[$cpt_id]);
                }

                $modal_data = htmlspecialchars(json_encode([
                    'id' => $cpt_id, // Pass CPT ID (0 if none)
                    'name' => $u->display_name,
                    'avatar' => $avatar_url,
                    'email' => $u->user_email,
                    'phone' => $phone,
                    'business' => $business_field,
                    'date' => date_i18n(get_option('date_format'), strtotime($registered_date))
                ]), ENT_QUOTES, 'UTF-8');

                echo '<tr>';

                // Student Column
                echo '<td class="column-student" data-label="Học viên" style="display:flex;align-items:center;gap:10px;">';
                echo get_avatar($u->ID, 32, '', '', ['class' => 'avatar-circle', 'style' => 'border-radius:50%;']);
                echo '<strong>' . esc_html($u->display_name) . '</strong>';
                echo '</td>';

                // Email
                echo '<td data-label="Email">' . esc_html($u->user_email) . '</td>';

                // Phone
                echo '<td data-label="Số điện thoại">' . ($phone ? esc_html($phone) : '<span style="color:#999">Chưa có SĐT</span>') . '</td>';

                // Business
                echo '<td data-label="Lĩnh vực kinh doanh">' . esc_html($business_field) . '</td>';

                // Class Status
                echo '<td data-label="Trạng thái lớp">' . $class_status . '</td>';

                // Attendance Badge
                $percent = (isset($attendance_map) && isset($attendance_map[$cpt_id])) ? $attendance_map[$cpt_id] : 0;
                $badge_bg = '#e74c3c'; // Danger (< 50%)
                if ($percent >= 80) {
                    $badge_bg = '#2ecc71'; // Safe (>= 80%)
                } elseif ($percent >= 50) {
                    $badge_bg = '#f39c12'; // Warning (50-79%)
                }

                echo '<td data-label="Chuyên cần">';
                if ($cpt_id && isset($attendance_map) && isset($attendance_map[$cpt_id])) {
                    echo '<span style="background-color:' . $badge_bg . '; color:#fff; padding:3px 8px; border-radius:10px; font-weight:bold; font-size:12px;">' . $percent . '%</span>';
                } else {
                    echo '<span style="color:#999;">-</span>';
                }
                echo '</td>';

                // Actions
                echo '<td data-label="Hành động">';
                echo '<div style="display:flex;gap:5px;">';
                echo '<button type="button" class="button button-small azac-view-student-btn" data-student="' . $modal_data . '"><span class="dashicons dashicons-visibility" style="line-height:1.3"></span></button>';

                if (in_array('administrator', $user->roles, true) || in_array('az_manager', (array) $user->roles)) {
                    $link_edit = admin_url('user-edit.php?user_id=' . $u->ID);
                    echo '<a href="' . esc_url($link_edit) . '" class="button button-small" title="Chỉnh sửa"><span class="dashicons dashicons-edit" style="line-height:1.3"></span></a>';
                }
                echo '</div>';
                echo '</td>';

                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        AzAC_Core_Helper::render_pagination($current_page, $total_pages);

        $stats_nonce = wp_create_nonce('azac_student_stats');

        ?>
        <div id="azac-student-modal" class="azac-modal"
            style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
            <div class="azac-modal-content"
                style="background:#fff;padding:20px;border-radius:8px;width:90%;max-width:600px;position:relative;max-height:90vh;overflow-y:auto;">
                <span class="azac-close"
                    style="position:absolute;top:10px;right:15px;font-size:24px;cursor:pointer;">&times;</span>
                <div class="azac-modal-body">
                    <div style="text-align:center;margin-bottom:20px;">
                        <img id="modal-avatar" src=""
                            style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin-bottom:10px;">
                        <h2 id="modal-name" style="margin:0 0 5px;"></h2>
                        <div style="font-size:13px;color:#666;">
                            <span id="modal-email"></span> • <span id="modal-phone"></span>
                        </div>
                        <div style="font-size:13px;color:#666;margin-top:4px;">
                            Lĩnh vực: <span id="modal-business"></span> • Đăng ký: <span id="modal-date"></span>
                        </div>
                    </div>

                    <div style="border-top:1px solid #eee;padding-top:15px;">
                        <h3 style="margin-top:0;margin-bottom:15px;font-size:16px;color:#0f6d5e;">Quá trình học tập</h3>
                        <div id="modal-classes-container">
                            <p style="text-align:center;color:#666;">Đang tải dữ liệu...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var azacStatsNonce = '<?php echo esc_js($stats_nonce); ?>';
            var azacAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

            jQuery(document).ready(function ($) {
                $('.azac-view-student-btn').on('click', function () {
                    var data = $(this).data('student');
                    $('#modal-avatar').attr('src', data.avatar);
                    $('#modal-name').text(data.name);
                    $('#modal-email').text(data.email);
                    $('#modal-phone').text(data.phone);
                    $('#modal-business').text(data.business);
                    $('#modal-date').text(data.date);

                    if (!data.id) {
                        $('#modal-classes-container').html('<p style="text-align:center;color:#e74c3c;">Học viên chưa tham gia lớp học nào.</p>');
                        $('#azac-student-modal').css('display', 'flex');
                        return;
                    }

                    $('#modal-classes-container').html('<p style="text-align:center;color:#666;">Đang tải dữ liệu...</p>');
                    $('#azac-student-modal').css('display', 'flex');

                    // Fetch Stats
                    $.ajax({
                        url: azacAjaxUrl,
                        method: 'POST',
                        data: {
                            action: 'azac_student_stats',
                            nonce: azacStatsNonce,
                            student_id: data.id
                        },
                        success: function (res) {
                            if (res.success) {
                                var html = '';
                                if (res.data.classes.length === 0) {
                                    html = '<p>Học viên chưa tham gia lớp nào.</p>';
                                } else {
                                    html += '<div class="azac-modal-list" style="display:flex;flex-direction:column;gap:15px;">';
                                    res.data.classes.forEach(function (c) {
                                        // Calculate based on SLOTS (checkpoints) to match user expectation (1 checkin + 1 mid = 2 slots)
                                        var present_slots = 0;
                                        var total_slots = 0;
                                        if (c.sessions && Array.isArray(c.sessions)) {
                                            c.sessions.forEach(function (s) {
                                                // Check Start (Dau gio)
                                                if (s.checkin !== null) {
                                                    total_slots++;
                                                    if (s.checkin == 1) present_slots++;
                                                }
                                                // Check Mid (Giua gio)
                                                if (s.mid !== null) {
                                                    total_slots++;
                                                    if (s.mid == 1) present_slots++;
                                                }
                                            });
                                        }
                                        var present = present_slots;
                                        var absent = total_slots - present_slots;
                                        var percent = total_slots > 0 ? Math.round((present / total_slots) * 100) : 0;

                                        // Color logic: >80% Green, 50-80% Yellow, <50% Red
                                        var color = '#e74c3c';
                                        if (percent > 80) color = '#2ecc71';
                                        else if (percent >= 50) color = '#f39c12';

                                        html += '<div class="azac-class-item" style="border:1px solid #eee;border-radius:8px;padding:10px;">';
                                        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
                                        html += '<strong style="font-size:14px;">' + c.title + '</strong>';
                                        html += '<span style="font-size:12px;background:' + color + ';color:#fff;padding:2px 8px;border-radius:10px;">' + percent + '% chuyên cần</span>';
                                        html += '</div>';

                                        // Progress bar
                                        html += '<div style="height:6px;background:#eee;border-radius:3px;margin-bottom:10px;overflow:hidden;">';
                                        html += '<div style="height:100%;width:' + percent + '%;background:' + color + '"></div>';
                                        html += '</div>';

                                        // Stats details
                                        html += '<div style="font-size:12px;color:#666;display:flex;gap:15px;margin-bottom:10px;">';
                                        html += '<span>Có mặt: <b>' + present + '</b></span>';
                                        html += '<span>Vắng: <b>' + absent + '</b></span>';
                                        html += '</div>';

                                        // Sessions toggle
                                        html += '<button type="button" class="button button-small azac-toggle-sessions" style="width:100%">Xem chi tiết buổi học</button>';

                                        html += '<div class="azac-sessions-list" style="display:none;margin-top:10px;border-top:1px dashed #eee;padding-top:10px;">';
                                        if (c.sessions && c.sessions.length > 0) {
                                            html += '<table style="width:100%;font-size:12px;text-align:left;"><thead><tr><th>Ngày</th><th>Đầu giờ</th><th>Giữa giờ</th></tr></thead><tbody>';
                                            c.sessions.forEach(function (s) {
                                                var d = s.date.split('-').reverse().join('/');
                                                var st1 = s.checkin === 1 ? '<span style="color:#2ecc71">✔</span>' : (s.checkin === 0 ? '<span style="color:#e74c3c">✘</span>' : '<span style="color:#ccc">-</span>');
                                                var st2 = s.mid === 1 ? '<span style="color:#3498db">✔</span>' : (s.mid === 0 ? '<span style="color:#e74c3c">✘</span>' : '<span style="color:#ccc">-</span>');
                                                html += '<tr><td style="padding:4px 0;">' + d + '</td><td>' + st1 + '</td><td>' + st2 + '</td></tr>';
                                            });
                                            html += '</tbody></table>';
                                        } else {
                                            html += '<p style="margin:0;font-style:italic;">Chưa có dữ liệu buổi học.</p>';
                                        }
                                        html += '</div>';

                                        html += '</div>';
                                    });
                                    html += '</div>';
                                }
                                $('#modal-classes-container').html(html);
                            } else {
                                $('#modal-classes-container').html('<p style="color:red;">Lỗi tải dữ liệu.</p>');
                            }
                        },
                        error: function () {
                            $('#modal-classes-container').html('<p style="color:red;">Lỗi kết nối.</p>');
                        }
                    });
                });

                $(document).on('click', '.azac-toggle-sessions', function () {
                    $(this).next('.azac-sessions-list').slideToggle();
                });

                $('.azac-close, #azac-student-modal').on('click', function (e) {
                    if (e.target === this) {
                        $('#azac-student-modal').hide();
                    }
                });
            });
        </script>
        <?php
        echo '</div>';
    }
    public static function render_class_dashboard_page()
    {
        $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        if (!$class_id) {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1>';
            $user = wp_get_current_user();
            $is_admin = in_array('administrator', $user->roles, true);
            $is_teacher = in_array('az_teacher', $user->roles, true);
            $args = [
                'post_type' => 'az_class',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ];
            if ($is_admin || $is_teacher) {
                $args['post_status'] = ['publish', 'pending'];
            }
            $classes = get_posts($args);
            if (in_array('az_student', $user->roles, true)) {
                $student_post_id = AzAC_Core_Helper::get_current_student_post_id();
                $classes = array_filter($classes, function ($c) use ($student_post_id) {
                    $ids = get_post_meta($c->ID, 'az_students', true);
                    $ids = is_array($ids) ? array_map('absint', $ids) : [];
                    return in_array($student_post_id, $ids, true);
                });
            } elseif (in_array('az_teacher', $user->roles, true)) {
                $classes = array_filter($classes, function ($c) use ($user) {
                    $teacher_user = intval(get_post_meta($c->ID, 'az_teacher_user', true));
                    return $teacher_user === intval($user->ID);
                });
            }
            if (!$classes) {
                echo '<p>Chưa có lớp nào để hiển thị.</p></div>';
                return;
            }
            echo '<div class="azac-grid">';
            foreach ($classes as $c) {
                $gv = get_post_meta($c->ID, 'az_giang_vien', true);
                $tsb = intval(get_post_meta($c->ID, 'az_tong_so_buoi', true));
                $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
                $current_sessions = count(AzAC_Core_Sessions::get_class_sessions($c->ID));
                $progress_total = max(0, $tsb);
                $progress_done = $current_sessions;
                $progress_percent = ($progress_total > 0) ? min(100, round(($progress_done / $progress_total) * 100)) : 0;
                $status = get_post_status($c->ID);
                $is_pending = ($status === 'pending');
                $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
                $link_view = get_permalink($c->ID);
                $link_dashboard = admin_url('admin.php?page=azac-classes-list&class_id=' . $c->ID);

                // Hash Color Logic
                $palette = AzAC_Core_Admin::$palette;
                $hash = 0;
                $c_name = $c->post_title;
                for ($i = 0; $i < strlen($c_name); $i++) {
                    $hash += ord($c_name[$i]);
                }
                $color = $palette[$hash % count($palette)];

                echo '<div class="azac-card">';
                echo '<div class="azac-card-title" style="border-left: 5px solid ' . $color . '">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
                echo '<div class="azac-card-body">';
                echo '<div class="azac-meta-list">';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Giảng viên</span><span class="azac-meta-value">' . esc_html($gv ?: 'Chưa gán') . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Tổng số buổi</span><span class="azac-meta-value">' . esc_html($tsb) . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Sĩ số</span><span class="azac-meta-value">' . esc_html($shv) . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Số buổi hiện tại</span><span class="azac-meta-value">' . esc_html($progress_done . '/' . $progress_total) . '</span></div>';
                echo '</div>';
                echo '<div class="azac-progress"><div class="azac-progress-bar" data-cid="' . esc_attr($c->ID) . '" style="width:' . esc_attr($progress_percent) . '%; background-color:' . $color . '"></div></div>';
                echo '</div>';
                if (in_array('administrator', $user->roles, true)) {
                    echo '<div class="azac-card-actions azac-actions--classes">';
                    if ($is_pending) {
                        echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                    } else {
                        echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                    }
                    echo '<a class="button button-secondary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                    echo '<a class="button button-info" href="' . esc_url($link_view) . '">Vào lớp</a> ';
                    echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
                    echo '</div>';
                } elseif (in_array('az_teacher', $user->roles, true)) {
                    echo '<div class="azac-card-actions azac-actions--classes">';
                    if ($is_pending) {
                        echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                    } else {
                        echo '<a class="button button-secondary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> <a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> <a class="button button-info" href="' . esc_url($link_view) . '">Vào lớp</a>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="azac-card-actions azac-actions--single"><a class="button button-info" href="' . esc_url($link_view) . '">Vào lớp</a></div>';
                }
                echo '</div>';
            }
            echo '</div></div>';
            return;
        }
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'az_class') {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1><p>Lớp không hợp lệ.</p></div>';
            return;
        }
        $user = wp_get_current_user();
        $is_student = in_array('az_student', $user->roles, true);
        $is_teacher = in_array('az_teacher', $user->roles, true);
        $is_admin = in_array('administrator', $user->roles, true);
        $is_manager = in_array('az_manager', (array) $user->roles);
        $assigned_teacher = intval(get_post_meta($class_id, 'az_teacher_user', true));
        $can_access = current_user_can('edit_post', $class_id) || $is_student || ($is_teacher && $assigned_teacher === intval($user->ID));
        if (!$can_access) {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1><p>Không đủ quyền.</p></div>';
            return;
        }
        $students = AzAC_Core_Helper::get_class_students($class_id);
        $teacher_name = get_post_meta($class_id, 'az_giang_vien', true);
        $teacher_user_id = intval(get_post_meta($class_id, 'az_teacher_user', true));
        if (!$teacher_name && $teacher_user_id) {
            $u = get_userdata($teacher_user_id);
            if ($u) {
                $teacher_name = $u->display_name ?: $u->user_login;
            }
        }
        $nonce = wp_create_nonce('azac_attendance_save');
        $today = current_time('Y-m-d');
        $sessions_meta = AzAC_Core_Sessions::get_class_sessions($class_id);
        $selected_date = isset($_GET['session_date']) ? sanitize_text_field($_GET['session_date']) : '';
        if (!$selected_date) {
            $selected_date = $sessions_meta ? $sessions_meta[count($sessions_meta) - 1]['date'] : $today;
        }
        global $wpdb;
        $att_table = $wpdb->prefix . 'az_attendance';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT attendance_type, status, COUNT(*) as c FROM {$att_table} WHERE class_id=%d AND session_date=%s GROUP BY attendance_type, status", $class_id, $selected_date), ARRAY_A);
        $stats = [
            'checkin_present' => 0,
            'checkin_absent' => 0,
            'mid_present' => 0,
            'mid_absent' => 0,
        ];
        foreach ($rows as $r) {
            if ($r['attendance_type'] === 'check-in') {
                if (intval($r['status']) === 1) {
                    $stats['checkin_present'] += intval($r['c']);
                } else {
                    $stats['checkin_absent'] += intval($r['c']);
                }
            } else {
                if (intval($r['status']) === 1) {
                    $stats['mid_present'] += intval($r['c']);
                } else {
                    $stats['mid_absent'] += intval($r['c']);
                }
            }
        }
        $sessions_count = 1;
        foreach ($sessions_meta as $idx => $s) {
            if (($s['date'] ?? '') === $selected_date) {
                $sessions_count = $idx + 1;
                break;
            }
        }
        echo '<div class="wrap">';
        echo '<h1>Điểm danh lớp: ' . esc_html($post->post_title) . '</h1>';
        echo '<div class="azac-stats">';
        echo '<div class="azac-stat azac-info-card"><div class="az-info-header">Thông tin lớp</div><div class="az-info-grid">';
        echo '<div class="az-info-item"><span class="az-info-label">Lớp</span><span class="az-info-value">' . esc_html($post->post_title) . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Giảng viên</span><span class="az-info-value">' . esc_html($teacher_name ?: 'Chưa gán') . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Sĩ số</span><span class="az-info-value">' . esc_html(count($students)) . '</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Tổng buổi</span><span class="az-info-value">' . esc_html(get_post_meta($class_id, 'az_tong_so_buoi', true)) . '</span></div>';
        echo '</div></div>';
        $checkin_total = max(1, $stats['checkin_present'] + $stats['checkin_absent']);
        $mid_total = max(1, $stats['mid_present'] + $stats['mid_absent']);
        $checkin_rate = round(($stats['checkin_present'] / $checkin_total) * 100);
        $mid_rate = round(($stats['mid_present'] / $mid_total) * 100);
        echo '<div class="azac-stat"><div class="azac-chart-row"><div class="azac-chart-box"><canvas id="azacChartCheckin"></canvas></div><div class="azac-chart-box"><canvas id="azacChartMid"></canvas></div></div></div>';
        echo '</div>';
        echo '<div class="azac-session-bar">';
        echo '<select id="azac_session_select">';
        foreach ($sessions_meta as $s) {
            $label = ($s['date'] ?? '') . (($s['time'] ?? '') ? (' ' . $s['time']) : '');
            $sel = (($s['date'] ?? '') === $selected_date) ? ' selected' : '';
            echo '<option value="' . esc_attr($s['date'] ?? '') . '"' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        if (!$is_student) {
            if ($is_admin || $is_manager) {
                echo '<input type="date" id="azac_session_date" value="' . esc_attr($selected_date) . '" /> ';
                echo '<input type="time" id="azac_session_time" value="" /> ';
                echo '<button class="button" id="azac_add_session_btn">Thêm buổi</button> ';
                echo '<button class="button" id="azac_update_session_btn">Cập nhật buổi</button>';
            }
            echo ' <button class="button button-success" id="azac_start_mid_btn">Hiện mã QR Review</button>';
        }
        echo '</div>';
        echo '<h2 id="azac_session_title">Buổi học thứ: ' . esc_html($sessions_count) . ' • Ngày: ' . esc_html(date_i18n('d/m/Y', strtotime($selected_date))) . '</h2>';
        echo '<div class="azac-tabs">';
        echo '<button class="button button-primary azac-tab-btn" data-target="#azac-checkin">Điểm danh đầu giờ</button> ';
        echo '<button class="button azac-tab-btn" data-target="#azac-mid">Điểm danh giữa giờ</button> ';
        echo '</div>';
        $can_edit = $is_admin || $is_manager || ($is_teacher && $selected_date === $today);
        echo '<div id="azac-checkin" class="azac-tab active">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td data-label="STT">' . esc_html($i++) . '</td>';
            echo '<td data-label="Họ và Tên">' . esc_html($s->post_title) . '</td>';
            $disable_attr = $can_edit ? '' : ' disabled';
            $disable_cls = $can_edit ? '' : ' azac-disabled';
            $readonly = $can_edit ? '' : ' readonly';
            echo '<td data-label="Trạng thái"><label class="azac-switch' . $disable_cls . '"><input type="checkbox" class="azac-status" data-student="' . esc_attr($s->ID) . '"' . $disable_attr . ' /><span class="azac-slider"></span></label></td>';
            echo '<td data-label="Ghi chú"><input type="text" class="regular-text azac-note" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . $readonly . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            $btn_style = $can_edit ? '' : ' style="display:none"';
            echo '<p><button class="button button-primary" id="azac-submit-checkin" data-type="check-in"' . $btn_style . '>Xác nhận điểm danh đầu giờ</button></p>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div id="azac-mid" class="azac-tab">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td data-label="STT">' . esc_html($i++) . '</td>';
            echo '<td data-label="Họ và Tên">' . esc_html($s->post_title) . '</td>';
            $disable_attr = $can_edit ? '' : ' disabled';
            $disable_cls = $can_edit ? '' : ' azac-disabled';
            $readonly = $can_edit ? '' : ' readonly';
            echo '<td data-label="Trạng thái"><label class="azac-switch' . $disable_cls . '"><input type="checkbox" class="azac-status-mid" data-student="' . esc_attr($s->ID) . '"' . $disable_attr . ' /><span class="azac-slider"></span></label></td>';
            echo '<td data-label="Ghi chú"><input type="text" class="regular-text azac-note-mid" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . $readonly . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            $btn_style = $can_edit ? '' : ' style="display:none"';
            echo '<p><button class="button button-primary" id="azac-submit-mid" data-type="mid-session"' . $btn_style . '>Xác nhận điểm danh giữa giờ</button></p>';
        }
        echo '</div>';
        echo '<div id="azac-mid-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">';
        echo '<div style="background:#fff;border-radius:12px;box-shadow:0 10px 20px rgba(0,0,0,.2);padding:16px;max-width:640px;width:90%;display:flex;flex-direction:column;gap:12px">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between"><div style="font-weight:700;color:#0f6d5e">Công cụ Review buổi học</div><button type="button" class="button" id="azac_mid_close_modal">Đóng</button></div>';
        echo '<div style="font-weight:600;color:#333">Mời học viên quét mã để đánh giá buổi học</div>';
        echo '<div style="display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap">';
        echo '<div style="flex:1;min-width:240px;display:flex;align-items:center;justify-content:center"><img id="azac_mid_qr" alt="QR" style="width:280px;height:280px;border:1px solid #e2e2e2;border-radius:12px"/></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<script>window.azacData=' . wp_json_encode([
            'classId' => $class_id,
            'isAdmin' => $is_admin || $is_manager,
            'nonce' => $nonce,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'today' => $today,
            'sessionDate' => $selected_date,
            'sessionNonce' => wp_create_nonce('azac_session'),
            'sessions' => $sessions_meta,
            'stats' => [
                'checkin' => [
                    'present' => $stats['checkin_present'],
                    'absent' => $stats['checkin_absent'],
                ],
                'mid' => [
                    'present' => $stats['mid_present'],
                    'absent' => $stats['mid_absent'],
                ],
            ],
        ]) . ';</script>';
        echo '</div>';
    }
    public static function render_reviews_page()
    {
        if (!current_user_can('manage_options')) {
            echo '<div class="wrap"><h1>Reviews</h1><p>Chỉ Admin có thể truy cập trang này.</p></div>';
            return;
        }
        $classes = get_posts([
            'post_type' => 'az_class',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'pending'],
        ]);
        $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        if (!$class_id && $classes) {
            $class_id = $classes[0]->ID;
        }
        echo '<div class="wrap"><h1>Reviews</h1>';
        echo '<div class="azac-session-bar">';
        echo '<label>Chọn lớp ';
        echo '<select id="azacReviewsClass">';
        foreach ($classes as $c) {
            $sel = ($class_id === $c->ID) ? ' selected' : '';
            echo '<option value="' . esc_attr($c->ID) . '"' . $sel . '>' . esc_html($c->post_title) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>Lọc theo sao ';
        echo '<select id="azacReviewsFilter"><option value="">Tất cả</option><option value="1,2">1-2 sao</option><option value="3">3 sao</option><option value="4,5">4-5 sao</option></select>';
        echo '</label>';
        echo '<label><input type="text" id="azacReviewsSearch" placeholder="Tìm kiếm đánh giá..." style="margin-left:5px"></label>';
        echo '</div>';
        echo '<div class="azac-reviews-grid">';
        echo '<div class="azac-reviews-visual">';
        echo '<div class="azac-stat-title">Tổng quan đánh giá</div>';
        echo '<div class="azac-chart-row"><div class="azac-toggle">';
        echo '<button type="button" class="button button-primary azac-view-btn" id="azacViewClass" data-view="class">Tổng lớp</button> ';
        echo '<button type="button" class="button azac-view-btn" id="azacViewSessions" data-view="sessions">Theo buổi</button>';
        echo '</div><div class="azac-chart-box" style="flex:1;min-width:300px"><canvas id="azacReviewsMixedChart"></canvas></div></div>';
        echo '<div class="azac-info-card" style="margin-top:8px"><div class="az-info-grid">';
        echo '<div class="az-info-item"><span class="az-info-label">Điểm trung bình</span><span class="az-info-value" id="azacReviewsAvg">0.0/5.0</span></div>';
        echo '<div class="az-info-item"><span class="az-info-label">Tổng lượt đánh giá</span><span class="az-info-value" id="azacReviewsTotal">0</span></div>';
        echo '</div></div>';
        echo '</div>';
        echo '<div class="azac-reviews-list">';
        echo '<div id="azacReviewsList" class="azac-reviews-scroll"></div>';
        echo '<div id="azacReviewsPagination" class="tablenav bottom" style="margin-top:10px;justify-content:center;display:flex"></div>';
        echo '</div>';
        echo '</div>';
        echo '<script>window.azacReviews=' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azac_get_reviews'),
            'classId' => $class_id,
        ]) . ';</script>';
        echo '<script>if(window.AZAC_Reviews&&typeof window.AZAC_Reviews.init==="function"){window.AZAC_Reviews.init();}</script>';
        echo '</div>';
    }
    public static function render_manage_students_page()
    {
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true) && !in_array('az_teacher', $user->roles, true) && !in_array('az_manager', (array) $user->roles)) {
            echo '<div class="wrap"><h1>Quản lý Học viên</h1><p>Unauthorized.</p></div>';
            return;
        }
        $classes = get_posts([
            'post_type' => 'az_class',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'pending'],
        ]);

        if (in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            $classes = array_filter($classes, function ($c) use ($user) {
                $teacher_user = intval(get_post_meta($c->ID, 'az_teacher_user', true));
                return $teacher_user === intval($user->ID);
            });
        }

        $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if ($class_id && in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            $owner = intval(get_post_meta($class_id, 'az_teacher_user', true));
            if ($owner !== $user->ID) {
                $class_id = 0;
            }
        }

        echo '<div class="wrap azac-admin-teal"><h1>Quản lý Học viên</h1>';

        // Filter Form
        echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px; flex-wrap: wrap">';
        echo '<input type="hidden" name="page" value="azac-manage-students">';
        echo '<label>Lọc theo lớp: <select name="class_id">';
        echo '<option value="">Tất cả</option>';
        foreach ($classes as $c) {
            $sel = ($class_id === $c->ID) ? ' selected' : '';
            echo '<option value="' . esc_attr($c->ID) . '"' . $sel . '>' . esc_html($c->post_title) . '</option>';
        }
        echo '</select></label>';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Tìm kiếm học viên..." class="regular-text" style="width:auto">';
        echo '<button class="button button-secondary">Lọc</button>';
        if ($class_id || $search) {
            echo '<a href="' . admin_url('admin.php?page=azac-manage-students') . '" class="button">Xóa lọc</a>';
        }
        echo '</form>';

        echo '<div class="azac-issue-legend">';
        echo '<span class="legend-badge legend-safe"><span class="dashicons dashicons-yes"></span> Đủ 2 lần</span>';
        echo '<span class="legend-badge legend-half"><span class="dashicons dashicons-warning"></span> Thiếu 1 nửa</span>';
        echo '<span class="legend-badge legend-absent"><span class="dashicons dashicons-no"></span> Vắng</span>';
        echo '<span class="legend-type"><span class="dot dot-checkin"></span> Đầu giờ</span>';
        echo '<span class="legend-type"><span class="dot dot-mid"></span> Giữa giờ</span>';
        echo '</div>';
        $rows = method_exists('AzAC_Core_Admin', 'get_students_admin_summary') ? AzAC_Core_Admin::get_students_admin_summary($class_id) : [];

        if ($search) {
            $rows = array_filter($rows, function ($r) use ($search) {
                return mb_stripos($r['name'], $search) !== false;
            });
        }

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($rows);
        $total_pages = ceil($total_items / $per_page);
        $paged_rows = array_slice($rows, ($current_page - 1) * $per_page, $per_page);

        echo '<table class="widefat fixed striped"><thead><tr><th>Tên học viên</th><th>Lớp đang học</th><th>Số buổi đã tham gia</th><th>Ghi chú điểm danh</th></tr></thead><tbody>';
        if ($paged_rows) {
            $palette = AzAC_Core_Admin::$palette;
            foreach ($paged_rows as $r) {
                $name = $r['name'];
                $classes_html_arr = array_map(function ($c) use ($palette) {
                    $hash = 0;
                    for ($i = 0; $i < strlen($c); $i++) {
                        $hash += ord($c[$i]);
                    }
                    $color = $palette[$hash % count($palette)];
                    return '<span class="azac-badge-class" style="background-color: ' . $color . ' !important;">' . esc_html($c) . '</span>';
                }, $r['classes']);

                $classes_txt = implode(' ', $classes_html_arr);
                // Mobile badge string
                $mobile_badges = '<div class="azac-mobile-badges">' . $classes_txt . '</div>';

                $joined = intval($r['joined']);
                $issues = isset($r['issues']) && is_array($r['issues']) ? $r['issues'] : [];
                $notes_html = '';
                if ($issues) {
                    foreach ($issues as $it) {
                        $date = esc_html($it['date']);
                        $type = $it['type'];
                        $ch_ok = intval($it['ch']) === 1;
                        $mid_ok = intval($it['mid']) === 1;
                        $badge_cls = ($type === 'absent') ? 'azac-issue-absent' : (($type === 'half') ? 'azac-issue-half' : 'azac-issue-safe');
                        $icon = ($type === 'absent') ? 'dashicons-no' : (($type === 'half') ? 'dashicons-warning' : 'dashicons-yes');
                        $ch_text = $ch_ok ? 'Có mặt' : 'Vắng mặt';
                        $mid_text = $mid_ok ? 'Có mặt' : 'Vắng mặt';
                        $notes_html .= '<span class="azac-badge azac-issue-badge ' . $badge_cls . '"><span class="dashicons ' . $icon . '"></span> ' . $date . '<span class="azac-tip"><span class="tip-badge ' . ($ch_ok ? 'present' : 'absent') . '">Đầu giờ: ' . $ch_text . '</span><span class="tip-badge ' . ($mid_ok ? 'present' : 'absent') . '">Giữa giờ: ' . $mid_text . '</span></span></span> ';
                    }
                } else {
                    $notes_html = '<span class="azac-badge azac-issue-badge azac-issue-safe"><span class="dashicons dashicons-yes"></span> Đủ 2 lần</span>';
                }
                echo '<tr>';
                // Append mobile badges to Name
                echo '<td data-label="Tên học viên">' . esc_html($name) . $mobile_badges . '</td>';
                echo '<td data-label="Lớp đang học"><div class="azac-container-flex">' . $classes_txt . '</div></td>';
                echo '<td data-label="Số buổi đã tham gia">' . esc_html($joined) . '</td>';
                echo '<td data-label="Ghi chú điểm danh"><div class="azac-container-flex">' . $notes_html . '</div></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">Chưa có dữ liệu.</td></tr>';
        }
        echo '</tbody></table>';
        AzAC_Core_Helper::render_pagination($current_page, $total_pages);
        echo '<script>(function(){var s=document.getElementById("azacManageStudentsClass");if(!s)return;s.addEventListener("change",function(){var v=this.value||"";var url=new URL(window.location.href);if(v){url.searchParams.set("class_id",v);}else{url.searchParams.delete("class_id");}window.location.href=url.toString();});})();</script>';
        echo '</div>';
    }
    public static function render_manage_teachers_page()
    {
        if (!current_user_can('manage_options')) {
            echo '<div class="wrap"><h1>Quản lý Giảng viên</h1><p>Chỉ Admin có thể truy cập trang này.</p></div>';
            return;
        }

        // Palette for coloring (Unified)
        $palette = AzAC_Core_Admin::$palette;

        // --- Detail View Logic ---
        $teacher_id = isset($_GET['teacher_id']) ? absint($_GET['teacher_id']) : 0;
        if ($teacher_id) {
            $teacher = get_userdata($teacher_id);
            if (!$teacher) {
                echo '<div class="wrap"><h1>Lỗi</h1><p>Giảng viên không tồn tại.</p></div>';
                return;
            }

            echo '<div class="wrap azac-admin-teal"><h1>Chi tiết Giảng viên: ' . esc_html($teacher->display_name) . '</h1>';
            echo '<a href="' . admin_url('admin.php?page=azac-manage-teachers') . '" class="button">← Quay lại danh sách</a><br><br>';

            // Fetch sessions
            global $wpdb;
            $sess_table = $wpdb->prefix . 'az_sessions';

            // Get all classes of this teacher
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => $teacher_id,
                'post_status' => ['publish', 'pending'],
            ]);

            if (!$classes) {
                echo '<p>Giảng viên này chưa được gán lớp nào.</p></div>';
                return;
            }

            $class_ids = wp_list_pluck($classes, 'ID');
            $ids_placeholder = implode(',', array_fill(0, count($class_ids), '%d'));

            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$sess_table} WHERE class_id IN ($ids_placeholder) ORDER BY session_date DESC, session_time DESC",
                $class_ids
            ));

            echo '<table class="widefat fixed striped"><thead><tr>
                <th>Lớp học</th>
                <th>Ngày</th>
                <th>Thời gian</th>
                <th>Trạng thái chấm công</th>
                <th>Thời gian chấm công</th>
            </tr></thead><tbody>';

            $today = current_time('Y-m-d');

            if ($sessions) {
                foreach ($sessions as $s) {
                    $cls = get_post($s->class_id);
                    $cls_title = $cls ? $cls->post_title : 'Lớp #' . $s->class_id;
                    $is_checked = intval($s->teacher_checkin) === 1;
                    $checked_time = $s->teacher_checkin_time ? date_i18n('d/m/Y H:i', strtotime($s->teacher_checkin_time)) : '-';

                    $row_class = ($s->session_date === $today) ? ' class="is-today"' : '';

                    // Hash Logic for Class Badge
                    $hash = 0;
                    for ($i = 0; $i < strlen($cls_title); $i++) {
                        $hash += ord($cls_title[$i]);
                    }
                    $color = $palette[$hash % count($palette)];
                    $badge_html = '<span class="azac-badge-class" style="background-color: ' . $color . ' !important;">' . esc_html($cls_title) . '</span>';

                    // Mobile Badge (Append to Date)
                    $mobile_badge = '<div class="azac-mobile-badges">' . $badge_html . '</div>';

                    echo '<tr' . $row_class . '>';
                    echo '<td data-label="Lớp học">' . $badge_html . '</td>';
                    echo '<td data-label="Ngày">' . esc_html(date_i18n('d/m/Y', strtotime($s->session_date))) . $mobile_badge . '</td>';
                    echo '<td data-label="Thời gian">' . esc_html($s->session_time) . '</td>';
                    echo '<td data-label="Trạng thái chấm công">' . ($is_checked ? '<span class="azac-badge azac-badge-publish">Đã chấm công</span>' : '<span class="azac-badge azac-badge-pending">Chưa chấm công</span>') . '</td>';
                    echo '<td data-label="Thời gian chấm công">' . esc_html($checked_time) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5">Chưa có buổi học nào.</td></tr>';
            }

            echo '</tbody></table></div>';
            return;
        }

        // --- List View Logic ---
        $month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        echo '<div class="wrap azac-admin-teal"><h1>Quản lý Giảng viên</h1>';

        // Filter Form
        echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px">';
        echo '<input type="hidden" name="page" value="azac-manage-teachers">';
        echo '<label><strong>Lọc theo tháng:</strong> <input type="month" name="month" value="' . esc_attr($month) . '" class="regular-text" style="width:auto"></label>';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Tìm kiếm giảng viên..." class="regular-text" style="width:auto">';
        echo '<button class="button button-secondary">Lọc</button>';
        if ($month || $search) {
            echo '<a href="' . admin_url('admin.php?page=azac-manage-teachers') . '" class="button">Xóa lọc</a>';
        }
        echo '</form>';

        $rows = method_exists('AzAC_Core_Admin', 'get_teachers_admin_summary') ? AzAC_Core_Admin::get_teachers_admin_summary($month) : [];

        if ($search) {
            $rows = array_filter($rows, function ($r) use ($search) {
                return mb_stripos($r['name'], $search) !== false;
            });
        }

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($rows);
        $total_pages = ceil($total_items / $per_page);
        $paged_rows = array_slice($rows, ($current_page - 1) * $per_page, $per_page);

        echo '<table class="widefat fixed striped"><thead><tr>
            <th>Tên giảng viên</th>
            <th>Danh sách lớp phụ trách</th>
            <th>Học viên</th>
            <th>Tổng buổi dạy</th>
            <th>Đã chấm công</th>
            <th>Còn thiếu</th>
            <th>Hành động</th>
        </tr></thead><tbody>';
        if ($paged_rows) {
            foreach ($paged_rows as $r) {
                $name = $r['name'];
                $classes = $r['classes'];
                $students_total = intval($r['students_total']);

                $stats = isset($r['stats']) ? $r['stats'] : ['total' => 0, 'checked' => 0, 'missing' => 0];

                $links = array_map(function ($cl) use ($palette) {
                    // Force hash color
                    $hash = 0;
                    $c_name = $cl['title'];
                    for ($i = 0; $i < strlen($c_name); $i++) {
                        $hash += ord($c_name[$i]);
                    }
                    $color = $palette[$hash % count($palette)];

                    // Use badge class
                    $style = 'background-color:' . $color . ' !important; color:#fff !important; border:none;';
                    return '<a href="' . esc_url($cl['link']) . '" class="button azac-badge-class" style="margin:2px 4px;' . $style . '">' . esc_html($cl['title']) . '</a>';
                }, $classes);

                $detail_link = admin_url('admin.php?page=azac-manage-teachers&teacher_id=' . $r['id']);

                $mobile_badges = '<div class="azac-mobile-badges">' . implode(' ', $links) . '</div>';

                echo '<tr>';
                echo '<td data-label="Tên giảng viên"><strong>' . esc_html($name) . '</strong>' . $mobile_badges . '</td>';
                echo '<td data-label="Danh sách lớp phụ trách">' . implode(' ', $links) . '</td>';
                echo '<td data-label="Học viên">' . esc_html($students_total) . '</td>';
                echo '<td data-label="Tổng buổi dạy">' . esc_html($stats['total']) . '</td>';
                echo '<td data-label="Đã chấm công"><span style="color:#2ecc71;font-weight:bold">' . esc_html($stats['checked']) . '</span></td>';
                echo '<td data-label="Còn thiếu"><span style="color:#e74c3c;font-weight:bold">' . esc_html($stats['missing']) . '</span></td>';
                echo '<td data-label="Hành động"><a href="' . esc_url($detail_link) . '" class="button button-primary">Xem chi tiết</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">Chưa có dữ liệu giảng viên.</td></tr>';
        }
        echo '</tbody></table>';
        AzAC_Core_Helper::render_pagination($current_page, $total_pages);
        echo '</div>';
    }

    public static function render_teacher_attendance_page()
    {
        $user = wp_get_current_user();
        if (!in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true) && !in_array('az_manager', (array) $user->roles)) {
            wp_die('Unauthorized');
        }

        // --- Admin View ---
        if (in_array('administrator', $user->roles, true) || in_array('az_manager', (array) $user->roles)) {
            echo '<div class="wrap"><h1>Quản lý chấm công</h1>';

            // Filter Params
            $filter_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
            $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
            $per_page = 20;

            // Query
            global $wpdb;
            $sess_table = $wpdb->prefix . 'az_sessions';
            $posts_table = $wpdb->prefix . 'posts';
            $postmeta_table = $wpdb->prefix . 'postmeta';

            $where = "1=1";
            if ($filter_month) {
                $where .= $wpdb->prepare(" AND DATE_FORMAT(s.session_date, '%%Y-%%m') = %s", $filter_month);
            }

            $offset = ($paged - 1) * $per_page;

            $sql = "SELECT SQL_CALC_FOUND_ROWS s.*, p.post_title as class_name, pm.meta_value as teacher_id,
                    (SELECT COUNT(*) + 1 FROM {$sess_table} s2 WHERE s2.class_id = s.class_id AND (s2.session_date < s.session_date OR (s2.session_date = s.session_date AND s2.session_time < s.session_time))) as session_number 
                    FROM {$sess_table} s 
                    JOIN {$posts_table} p ON s.class_id = p.ID 
                    LEFT JOIN {$postmeta_table} pm ON (p.ID = pm.post_id AND pm.meta_key = 'az_teacher_user')
                    WHERE {$where} 
                    ORDER BY s.session_date DESC, s.session_time ASC 
                    LIMIT %d, %d";

            $rows = $wpdb->get_results($wpdb->prepare($sql, $offset, $per_page));
            $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");
            $total_pages = ceil($total_items / $per_page);

            // Filter Form
            echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px">';
            echo '<input type="hidden" name="page" value="azac-teacher-attendance">';
            echo '<label><strong>Lọc tháng:</strong> <input type="month" name="month" value="' . esc_attr($filter_month) . '"></label>';
            echo '<button class="button button-secondary">Lọc</button>';
            if ($filter_month) {
                echo '<a href="' . admin_url('admin.php?page=azac-teacher-attendance') . '" class="button">Xóa lọc</a>';
            }
            echo '</form>';

            // Table
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>Ngày dạy</th>
                <th>Lớp học</th>
                <th>Giảng viên</th>
                <th>Buổi</th>
                <th>Thời gian</th>
                <th>Trạng thái</th>
                <th>Thời gian chấm công</th>
            </tr></thead><tbody>';

            $today = current_time('Y-m-d');
            $palette = AzAC_Core_Admin::$palette;
            if ($rows) {
                foreach ($rows as $r) {
                    $teacher_name = '---';
                    if ($r->teacher_id) {
                        $u = get_userdata($r->teacher_id);
                        if ($u)
                            $teacher_name = $u->display_name;
                    }

                    $is_checked = intval($r->teacher_checkin) === 1;
                    $status_html = $is_checked
                        ? '<span class="azac-badge azac-badge-publish">Đã dạy</span>'
                        : '<span class="azac-badge azac-badge-pending">Chưa dạy</span>';

                    $checkin_time = $r->teacher_checkin_time ? date_i18n('d/m/Y H:i', strtotime($r->teacher_checkin_time)) : '---';

                    $row_class = ($r->session_date === $today) ? ' class="is-today"' : '';

                    // Color Logic
                    $hash = 0;
                    // Ensure class_name fallback
                    $c_name = !empty($r->class_name) ? $r->class_name : 'Lớp #' . $r->class_id;

                    for ($i = 0; $i < strlen($c_name); $i++) {
                        $hash += ord($c_name[$i]);
                    }
                    $color = $palette[$hash % count($palette)];
                    $badge_html = '<span class="azac-badge-class" style="background-color: ' . $color . ' !important;">' . esc_html($c_name) . '</span>';
                    $style = ' style="border-top: 3px solid ' . $color . ';"';

                    echo '<tr' . $row_class . $style . '>';

                    echo '<td data-label="Ngày dạy" class="azac-col-date">';
                    echo '<span class="azac-date-display">' . date_i18n('d/m/Y', strtotime($r->session_date)) . '</span>';
                    echo '</td>';

                    echo '<td data-label="Lớp học">' . $badge_html . '</td>';
                    echo '<td data-label="Giảng viên" class="azac-col-teacher"><strong>' . esc_html($teacher_name) . '</strong></td>';
                    echo '<td data-label="Buổi">Buổi ' . intval($r->session_number) . '<div class="azac-mobile-badges">' . $badge_html . '</div></td>';
                    echo '<td data-label="Thời gian">' . esc_html($r->session_time) . '</td>';
                    echo '<td data-label="Trạng thái">' . $status_html . '</td>';
                    echo '<td data-label="Thời gian chấm công">' . $checkin_time . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7">Không có dữ liệu.</td></tr>';
            }

            echo '</tbody></table>';

            // Pagination
            AzAC_Core_Helper::render_pagination($paged, $total_pages);

            echo '</div>'; // wrap
            return;
        }

        // --- Teacher View (Original Logic) ---
        echo '<div class="wrap"><h1>Chấm công Giảng viên</h1>';
        echo '<p>Danh sách các buổi học của lớp bạn phụ trách.</p>';

        $args = [
            'post_type' => 'az_class',
            'numberposts' => -1,
            'meta_key' => 'az_teacher_user',
            'meta_value' => $user->ID
        ];

        $classes = get_posts($args);
        $today = current_time('Y-m-d');

        echo '<div class="azac-grid">';

        $has_sessions = false;
        $palette = AzAC_Core_Admin::$palette;

        foreach ($classes as $c) {
            global $wpdb;
            $sess_table = $wpdb->prefix . 'az_sessions';
            // Show all sessions ordered by date ASC
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC, session_time ASC",
                $c->ID
            ));

            if (empty($sessions))
                continue;
            $has_sessions = true;

            // Hash Color
            $hash = 0;
            $c_name = $c->post_title;
            for ($i = 0; $i < strlen($c_name); $i++) {
                $hash += ord($c_name[$i]);
            }
            $color = $palette[$hash % count($palette)];

            echo '<div class="azac-card">';
            echo '<div class="azac-card-title" style="border-left: 5px solid ' . $color . '">' . esc_html($c->post_title) . '</div>';
            echo '<div class="azac-card-body">';
            echo '<table class="widefat">';
            echo '<thead><tr><th>Buổi</th><th>Thời gian</th><th>Trạng thái</th><th>Hành động</th></tr></thead>';
            echo '<tbody>';

            $s_index = 0;
            foreach ($sessions as $s) {
                $s_index++;
                $is_checked = intval($s->teacher_checkin) === 1;
                $checked_attr = $is_checked ? 'checked' : '';
                // Only allow checkin on exact date
                $is_today = ($s->session_date === $today);
                $disabled_attr = !$is_today ? 'disabled' : '';
                $row_class = $is_today ? 'class="is-today"' : '';

                // Format date for display
                $date_display = date_i18n('d/m/Y', strtotime($s->session_date));

                echo '<tr ' . $row_class . '>';
                // Buổi # instead of title
                echo '<td data-label="Buổi"><strong>Buổi ' . $s_index . '</strong><br><small>' . $date_display . '</small></td>';
                echo '<td data-label="Thời gian">' . esc_html($s->session_time) . '</td>';
                echo '<td data-label="Trạng thái">' . ($is_checked ? '<span class="azac-badge azac-badge-publish">Đã dạy</span>' : '<span class="azac-badge azac-badge-pending">Chưa dạy</span>') . '</td>';
                echo '<td data-label="Hành động">';
                echo '<label class="azac-switch ' . ($is_today ? '' : 'azac-disabled') . '">';
                echo '<input type="checkbox" class="azac-teacher-checkin-cb" data-class="' . esc_attr($c->ID) . '" data-date="' . esc_attr($s->session_date) . '" ' . $checked_attr . ' ' . $disabled_attr . '>';
                echo '<span class="azac-slider round"></span>';
                echo '</label>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // body
            echo '</div>'; // card
        }

        if (!$has_sessions) {
            echo '<p>Chưa có lịch học nào được phân công.</p>';
        }

        echo '</div>'; // grid
        echo '<script>window.azacData=' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'sessionNonce' => wp_create_nonce('azac_session'),
        ]) . ';</script>';
        echo '</div>'; // wrap
    }
}
