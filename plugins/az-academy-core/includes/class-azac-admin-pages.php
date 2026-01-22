<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Pages
{
    public static function register_admin_pages()
    {
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
        add_menu_page(
            'Học viên',
            'Học viên',
            'edit_posts',
            'azac-students-list',
            [__CLASS__, 'render_students_list_page'],
            'dashicons-id',
            0
        );
        add_menu_page(
            'Reviews',
            'Reviews',
            'manage_options',
            'azac-reviews',
            [__CLASS__, 'render_reviews_page'],
            'dashicons-chart-bar',
            0
        );
        add_menu_page(
            'Quản lý Học viên',
            'Quản lý Học viên',
            'manage_options',
            'azac-manage-students',
            [__CLASS__, 'render_manage_students_page'],
            'dashicons-groups',
            0
        );
        add_menu_page(
            'Quản lý Giảng viên',
            'Quản lý Giảng viên',
            'manage_options',
            'azac-manage-teachers',
            [__CLASS__, 'render_manage_teachers_page'],
            'dashicons-admin-users',
            0
        );
        add_menu_page(
            'Chấm công Giảng viên',
            'Chấm công Giảng viên',
            'read', // Allow read, check role inside
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
        $is_admin = in_array('administrator', $user->roles, true);
        $is_student = in_array('az_student', $user->roles, true);
        echo '<div class="azac-tabs" style="margin-bottom:10px;">';
        echo '<button class="button azac-tab-btn" data-target="#azac-tab-sessions">Buổi học</button>';
        if ($is_student) {
            echo ' <button class="button azac-tab-btn" data-target="#azac-tab-stats">Thống kê điểm danh</button>';
        }
        echo '</div>';
        if ($is_teacher || $is_admin || $is_student) {
            echo '<div id="azac-tab-sessions" class="azac-tab active">';
            echo '<div class="azac-session-filters">';
            echo '<label>Nhóm ';
            echo '<select id="azac-filter-group"><option value="session">Buổi học</option><option value="class">Lớp học</option></select>';
            echo '</label> ';
            echo '<label>Sắp xếp ';
            echo '<select id="azac-filter-sort"><option value="date_desc">Ngày mới nhất</option><option value="date_asc">Ngày cũ nhất</option><option value="rate_desc">Tỉ lệ có mặt cao→thấp</option><option value="rate_asc">Tỉ lệ có mặt thấp→cao</option></select>';
            echo '</label> ';
            echo '<label>Lọc lớp ';
            echo '<select id="azac-filter-class"><option value="">Tất cả</option></select>';
            echo '</label>';
            echo '</div>';
            echo '<div id="azac-sessions-grid" class="azac-grid">';
            echo '<div class="azac-card"><div class="azac-card-title">Đang tải danh sách buổi học...</div></div>';
            echo '</div>';
            echo '</div>';
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
        if ($is_admin) {
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
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
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
            if ($is_admin) {
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
                echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
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
        echo '<div class="wrap"><h1>Học viên</h1>';
        $user = wp_get_current_user();
        $students = get_posts([
            'post_type' => 'az_student',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (in_array('az_teacher', $user->roles, true)) {
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'meta_key' => 'az_teacher_user',
                'meta_value' => intval($user->ID),
            ]);
            $ids = [];
            foreach ($classes as $c) {
                $list = get_post_meta($c->ID, 'az_students', true);
                $list = is_array($list) ? array_map('absint', $list) : [];
                $ids = array_merge($ids, $list);
            }
            $ids = array_values(array_unique($ids));
            $students = array_filter($students, function ($s) use ($ids) {
                return in_array($s->ID, $ids, true);
            });
        }
        $students = array_values($students);
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($students);
        $total_pages = ceil($total_items / $per_page);
        $paged_students = array_slice($students, ($current_page - 1) * $per_page, $per_page);

        echo '<div class="azac-grid">';
        foreach ($paged_students as $s) {
            $uid = intval(get_post_meta($s->ID, 'az_user_id', true));
            $name = $s->post_title;
            $email = '';
            if ($uid) {
                $u = get_userdata($uid);
                if ($u)
                    $email = $u->user_email ?: '';
            }
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($name) . '</div>';
            echo '<div class="azac-card-body">';
            echo '<div>Tài khoản: ' . esc_html($email ?: 'Chưa liên kết') . '</div>';
            echo '</div>';
            if (in_array('administrator', $user->roles, true)) {
                $link_edit = admin_url('post.php?post=' . $s->ID . '&action=edit');
                echo '<div class="azac-card-actions azac-actions--single"><a class="button button-primary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a></div>';
            }
            echo '</div>';
        }
        echo '</div>';
        AzAC_Core_Helper::render_pagination($current_page, $total_pages);
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
                echo '<div class="azac-card">';
                echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
                echo '<div class="azac-card-body">';
                echo '<div class="azac-meta-list">';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Giảng viên</span><span class="azac-meta-value">' . esc_html($gv ?: 'Chưa gán') . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Tổng số buổi</span><span class="azac-meta-value">' . esc_html($tsb) . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Sĩ số</span><span class="azac-meta-value">' . esc_html($shv) . '</span></div>';
                echo '<div class="azac-meta-item"><span class="azac-meta-label">Số buổi hiện tại</span><span class="azac-meta-value">' . esc_html($progress_done . '/' . $progress_total) . '</span></div>';
                echo '</div>';
                echo '<div class="azac-progress"><div class="azac-progress-bar" data-cid="' . esc_attr($c->ID) . '" style="width:' . esc_attr($progress_percent) . '%"></div></div>';
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
            echo '<script>(function(){if(window.AZACU&&typeof window.AZACU.getClassColor===\"function\"){document.querySelectorAll(\".azac-progress-bar[data-cid]\").forEach(function(el){var cid=parseInt(el.getAttribute(\"data-cid\"),10)||0;var color=window.AZACU.getClassColor(cid);el.style.background=color;});}})();</script>';
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
            echo '<input type="date" id="azac_session_date" value="' . esc_attr($selected_date) . '" /> ';
            echo '<input type="time" id="azac_session_time" value="" /> ';
            echo '<button class="button" id="azac_add_session_btn">Thêm buổi</button> ';
            echo '<button class="button" id="azac_update_session_btn">Cập nhật buổi</button>';
            echo ' <button class="button button-success" id="azac_start_mid_btn">Hiện mã QR Review</button>';
        }
        echo '</div>';
        echo '<h2 id="azac_session_title">Buổi học thứ: ' . esc_html($sessions_count) . ' • Ngày: ' . esc_html(date_i18n('d/m/Y', strtotime($selected_date))) . '</h2>';
        echo '<div class="azac-tabs">';
        echo '<button class="button button-primary azac-tab-btn" data-target="#azac-checkin">Điểm danh đầu giờ</button> ';
        echo '<button class="button azac-tab-btn" data-target="#azac-mid">Điểm danh giữa giờ</button> ';
        echo '</div>';
        echo '<div id="azac-checkin" class="azac-tab active">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td>' . esc_html($i++) . '</td>';
            echo '<td>' . esc_html($s->post_title) . '</td>';
            echo '<td><label class="azac-switch' . ($is_student ? ' azac-disabled' : '') . '"' . ($is_student ? ' title="Đã bị vô hiệu hóa"' : '') . '><input type="checkbox" class="azac-status" data-student="' . esc_attr($s->ID) . '"' . ($is_student ? ' disabled' : '') . ' /><span class="azac-slider"></span></label></td>';
            echo '<td><input type="text" class="regular-text azac-note" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . ($is_student ? ' readonly' : '') . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            echo '<p><button class="button button-primary" id="azac-submit-checkin" data-type="check-in">Xác nhận điểm danh đầu giờ</button></p>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div id="azac-mid" class="azac-tab">';
        echo '<table class="widefat fixed striped"><thead><tr><th>STT</th><th>Họ và Tên</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
        $i = 1;
        foreach ($students as $s) {
            echo '<tr>';
            echo '<td>' . esc_html($i++) . '</td>';
            echo '<td>' . esc_html($s->post_title) . '</td>';
            $disable_mid = ($is_student) ? ' disabled' : '';
            $disable_cls = ($is_student) ? ' azac-disabled' : '';
            $disable_tip = ($is_student) ? ' title="Chỉ Admin/Giảng viên có thể chỉnh sửa"' : '';
            echo '<td><label class="azac-switch' . $disable_cls . '"' . $disable_tip . '><input type="checkbox" class="azac-status-mid" data-student="' . esc_attr($s->ID) . '"' . $disable_mid . ' /><span class="azac-slider"></span></label></td>';
            echo '<td><input type="text" class="regular-text azac-note-mid" data-student="' . esc_attr($s->ID) . '" placeholder="Nhập ghi chú"' . ($is_student ? ' readonly' : '') . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (!$is_student) {
            echo '<p><button class="button button-primary" id="azac-submit-mid" data-type="mid-session">Xác nhận điểm danh giữa giờ</button></p>';
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
        if (!current_user_can('manage_options')) {
            echo '<div class="wrap"><h1>Quản lý Học viên</h1><p>Chỉ Admin có thể truy cập trang này.</p></div>';
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
        echo '<div class="wrap azac-admin-teal"><h1>Quản lý Học viên</h1>';
        echo '<div class="azac-session-bar">';
        echo '<label>Lọc theo lớp ';
        echo '<select id="azacManageStudentsClass">';
        echo '<option value="">Tất cả</option>';
        foreach ($classes as $c) {
            $sel = ($class_id === $c->ID) ? ' selected' : '';
            echo '<option value="' . esc_attr($c->ID) . '"' . $sel . '>' . esc_html($c->post_title) . '</option>';
        }
        echo '</select></label></div>';
        echo '<div class="azac-issue-legend">';
        echo '<span class="legend-badge legend-safe"><span class="dashicons dashicons-yes"></span> Đủ 2 lần</span>';
        echo '<span class="legend-badge legend-half"><span class="dashicons dashicons-warning"></span> Thiếu 1 nửa</span>';
        echo '<span class="legend-badge legend-absent"><span class="dashicons dashicons-no"></span> Vắng</span>';
        echo '<span class="legend-type"><span class="dot dot-checkin"></span> Đầu giờ</span>';
        echo '<span class="legend-type"><span class="dot dot-mid"></span> Giữa giờ</span>';
        echo '</div>';
        $rows = method_exists('AzAC_Core_Admin', 'get_students_admin_summary') ? AzAC_Core_Admin::get_students_admin_summary($class_id) : [];

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($rows);
        $total_pages = ceil($total_items / $per_page);
        $paged_rows = array_slice($rows, ($current_page - 1) * $per_page, $per_page);

        echo '<table class="widefat fixed striped"><thead><tr><th>Tên học viên</th><th>Lớp đang học</th><th>Số buổi đã tham gia</th><th>Ghi chú điểm danh</th></tr></thead><tbody>';
        if ($paged_rows) {
            foreach ($paged_rows as $r) {
                $name = $r['name'];
                $classes_txt = implode(', ', array_map('esc_html', $r['classes']));
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
                echo '<td>' . esc_html($name) . '</td>';
                echo '<td>' . $classes_txt . '</td>';
                echo '<td>' . esc_html($joined) . '</td>';
                echo '<td>' . $notes_html . '</td>';
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

            if ($sessions) {
                foreach ($sessions as $s) {
                    $cls = get_post($s->class_id);
                    $cls_title = $cls ? $cls->post_title : 'Lớp #' . $s->class_id;
                    $is_checked = intval($s->teacher_checkin) === 1;
                    $checked_time = $s->teacher_checkin_time ? date_i18n('d/m/Y H:i', strtotime($s->teacher_checkin_time)) : '-';

                    echo '<tr>';
                    echo '<td>' . esc_html($cls_title) . '</td>';
                    echo '<td>' . esc_html(date_i18n('d/m/Y', strtotime($s->session_date))) . '</td>';
                    echo '<td>' . esc_html($s->session_time) . '</td>';
                    echo '<td>' . ($is_checked ? '<span class="azac-badge azac-badge-publish">Đã chấm công</span>' : '<span class="azac-badge azac-badge-pending">Chưa chấm công</span>') . '</td>';
                    echo '<td>' . esc_html($checked_time) . '</td>';
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
        $rows = method_exists('AzAC_Core_Admin', 'get_teachers_admin_summary') ? AzAC_Core_Admin::get_teachers_admin_summary($month) : [];

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $total_items = count($rows);
        $total_pages = ceil($total_items / $per_page);
        $paged_rows = array_slice($rows, ($current_page - 1) * $per_page, $per_page);

        echo '<div class="wrap azac-admin-teal"><h1>Quản lý Giảng viên</h1>';

        // Month Filter Form
        echo '<form method="get" style="margin-bottom:15px;display:flex;align-items:center;gap:10px">';
        echo '<input type="hidden" name="page" value="azac-manage-teachers">';
        echo '<label><strong>Lọc theo tháng:</strong> <input type="month" name="month" value="' . esc_attr($month) . '" class="regular-text" style="width:auto"></label>';
        echo '<button class="button button-secondary">Lọc</button>';
        if ($month) {
            echo '<a href="' . admin_url('admin.php?page=azac-manage-teachers') . '" class="button">Xóa lọc</a>';
        }
        echo '</form>';

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

                $links = array_map(function ($cl) {
                    $style = 'background:' . esc_attr($cl['color']) . ';border-color:' . esc_attr($cl['color']) . ';color:#fff';
                    return '<a href="' . esc_url($cl['link']) . '" class="button" style="margin:2px 4px;' . $style . '">' . esc_html($cl['title']) . '</a>';
                }, $classes);

                $detail_link = admin_url('admin.php?page=azac-manage-teachers&teacher_id=' . $r['id']);

                echo '<tr>';
                echo '<td><strong>' . esc_html($name) . '</strong></td>';
                echo '<td>' . implode(' ', $links) . '</td>';
                echo '<td>' . esc_html($students_total) . '</td>';
                echo '<td>' . esc_html($stats['total']) . '</td>';
                echo '<td><span style="color:#2ecc71;font-weight:bold">' . esc_html($stats['checked']) . '</span></td>';
                echo '<td><span style="color:#e74c3c;font-weight:bold">' . esc_html($stats['missing']) . '</span></td>';
                echo '<td><a href="' . esc_url($detail_link) . '" class="button button-primary">Xem chi tiết</a></td>';
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
        if (!in_array('az_teacher', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            wp_die('Unauthorized');
        }

        // --- Admin View ---
        if (in_array('administrator', $user->roles, true)) {
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

            // Build SQL
            // We want sessions where teacher_checkin=1 usually, but user said "view list of teachers who checked in", 
            // maybe also list sessions that are supposed to be checked in? 
            // "Admin sẽ chỉ được xem danh sách (table) của giảng viên đã chấm công buổi nào ngày nào" -> implying showing checkin history.
            // Let's show all sessions but emphasize checked ones, or just filter checked ones?
            // "table của giảng viên đã chấm công" -> implies rows where checkin=1.
            // But usually admins want to see missing checkins too.
            // User said: "lọc theo ngày giảng dạy hoặc tổng cộng".
            // Let's stick to showing ALL sessions but with status, and maybe default sort by date desc.

            $where = "1=1";
            if ($filter_month) {
                // month input is YYYY-MM
                $where .= $wpdb->prepare(" AND DATE_FORMAT(s.session_date, '%%Y-%%m') = %s", $filter_month);
            }

            // Join with classes to get title and teacher meta
            // We need to get teacher user ID from postmeta 'az_teacher_user'
            // And then get display_name from users table? Or just show class info?
            // "giảng viên đã chấm công" -> we need teacher name.

            $offset = ($paged - 1) * $per_page;

            $sql = "SELECT SQL_CALC_FOUND_ROWS s.*, p.post_title as class_name, pm.meta_value as teacher_id 
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

                    echo '<tr>';
                    echo '<td>' . date_i18n('d/m/Y', strtotime($r->session_date)) . '</td>';
                    echo '<td>' . esc_html($r->class_name) . '</td>';
                    echo '<td><strong>' . esc_html($teacher_name) . '</strong></td>';
                    echo '<td>' . esc_html($r->title ?: 'Buổi học') . '</td>';
                    echo '<td>' . esc_html($r->session_time) . '</td>';
                    echo '<td>' . $status_html . '</td>';
                    echo '<td>' . $checkin_time . '</td>';
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
        echo '<p>Danh sách các buổi học <strong>hôm nay (' . date('d/m/Y') . ')</strong> của lớp bạn phụ trách.</p>';

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

        foreach ($classes as $c) {
            global $wpdb;
            $sess_table = $wpdb->prefix . 'az_sessions';
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$sess_table} WHERE class_id=%d AND session_date=%s",
                $c->ID,
                $today
            ));

            if (empty($sessions))
                continue;
            $has_sessions = true;

            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($c->post_title) . '</div>';
            echo '<div class="azac-card-body">';
            echo '<table class="widefat">';
            echo '<thead><tr><th>Buổi</th><th>Thời gian</th><th>Trạng thái</th><th>Hành động</th></tr></thead>';
            echo '<tbody>';
            foreach ($sessions as $s) {
                $is_checked = intval($s->teacher_checkin) === 1;
                $checked_attr = $is_checked ? 'checked' : '';
                // Only allow checkin on exact date (redundant check but good for UI)
                $disabled_attr = ($s->session_date !== $today) ? 'disabled' : '';

                echo '<tr>';
                echo '<td>' . esc_html($s->title ?: 'Buổi học') . '</td>';
                echo '<td>' . esc_html($s->session_time) . '</td>';
                echo '<td>' . ($is_checked ? '<span class="azac-badge azac-badge-publish">Đã dạy</span>' : '<span class="azac-badge azac-badge-pending">Chưa dạy</span>') . '</td>';
                echo '<td>';
                echo '<label class="azac-switch">';
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
            echo '<p>Không có buổi học nào cần dạy hôm nay.</p>';
        }

        echo '</div>'; // grid
        echo '<script>window.azacData=' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'sessionNonce' => wp_create_nonce('azac_session'),
        ]) . ';</script>';
        echo '</div>'; // wrap
    }
}
