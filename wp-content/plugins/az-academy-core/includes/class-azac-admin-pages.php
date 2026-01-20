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
            3
        );
        add_submenu_page(
            'azac-attendance',
            'Lớp học',
            'Lớp học',
            'edit_posts',
            'azac-classes-list',
            [__CLASS__, 'render_classes_list_page']
        );
        add_submenu_page(
            'azac-attendance',
            'Học viên',
            'Học viên',
            'edit_posts',
            'azac-students-list',
            [__CLASS__, 'render_students_list_page']
        );
        add_submenu_page(
            'azac-attendance',
            'Chi tiết lớp',
            'Chi tiết lớp',
            'read',
            'azac-class-dashboard',
            [__CLASS__, 'render_class_dashboard_page']
        );
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
        foreach ($classes as $c) {
            $gv = get_post_meta($c->ID, 'az_giang_vien', true);
            $tsb = intval(get_post_meta($c->ID, 'az_tong_so_buoi', true));
            $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
            $status = get_post_status($c->ID);
            $is_pending = ($status === 'pending');
            $link_dashboard = admin_url('admin.php?page=azac-class-dashboard&class_id=' . $c->ID);
            $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
            $link_view = get_permalink($c->ID);
            echo '<div class="azac-card">';
            echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
            echo '<div class="azac-card-body">';
            echo '<div>Giảng viên: ' . esc_html($gv ?: 'Chưa gán') . '</div>';
            echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
            echo '<div>Số học viên: ' . esc_html($shv) . '</div>';
            echo '</div>';
            echo '<div class="azac-card-actions">';
            if ($is_admin) {
                if ($is_pending) {
                    echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                } else {
                    echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                }
                echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
            } elseif ($is_teacher) {
                if ($is_pending) {
                    echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                } else {
                    echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a>';
                }
            } else {
                echo '<a class="button button-primary" href="' . esc_url($link_view) . '">Xem lớp</a>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div></div>';
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
        echo '<div class="azac-grid">';
        foreach ($students as $s) {
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
                echo '<div class="azac-card-actions"><a class="button button-primary" href="' . esc_url($link_edit) . '">Chỉnh sửa</a></div>';
            }
            echo '</div>';
        }
        echo '</div></div>';
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
                $tsb = get_post_meta($c->ID, 'az_tong_so_buoi', true);
                $shv = intval(get_post_meta($c->ID, 'az_so_hoc_vien', true));
                $status = get_post_status($c->ID);
                $is_pending = ($status === 'pending');
                $link_edit = admin_url('post.php?post=' . $c->ID . '&action=edit');
                $link_view = get_permalink($c->ID);
                $link_dashboard = admin_url('admin.php?page=azac-class-dashboard&class_id=' . $c->ID);
                echo '<div class="azac-card">';
                echo '<div class="azac-card-title">' . esc_html($c->post_title) . ' <span class="azac-badge ' . ($is_pending ? 'azac-badge-pending' : 'azac-badge-publish') . '">' . ($is_pending ? 'Chưa mở' : 'Đang mở') . '</span></div>';
                echo '<div class="azac-card-body">';
                echo '<div>Giảng viên: ' . esc_html($gv) . '</div>';
                echo '<div>Tổng số buổi: ' . esc_html($tsb) . '</div>';
                echo '<div>Sĩ số: ' . esc_html($shv) . '</div>';
                echo '</div>';
                if (in_array('administrator', $user->roles, true)) {
                    echo '<div class="azac-card-actions">';
                    if ($is_pending) {
                        echo '<button type="button" class="button button-success azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="publish">Mở lớp</button> ';
                    } else {
                        echo '<button type="button" class="button button-warning azac-status-btn" data-id="' . esc_attr($c->ID) . '" data-status="pending">Đóng lớp</button> ';
                    }
                    echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> ';
                    echo '<a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> ';
                    echo '<a class="button" href="' . esc_url($link_view) . '">Vào lớp</a> ';
                    echo '<button type="button" class="button button-danger azac-delete-btn" data-id="' . esc_attr($c->ID) . '">Xóa lớp</button>';
                    echo '</div>';
                } elseif (in_array('az_teacher', $user->roles, true)) {
                    echo '<div class="azac-card-actions">';
                    if ($is_pending) {
                        echo '<span class="azac-badge azac-badge-pending">Lớp chưa mở</span>';
                    } else {
                        echo '<a class="button" href="' . esc_url($link_edit) . '">Chỉnh sửa</a> <a class="button button-primary" href="' . esc_url($link_dashboard) . '">Vào điểm danh</a> <a class="button" href="' . esc_url($link_view) . '">Vào lớp</a>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="azac-card-actions"><a class="button button-primary" href="' . esc_url($link_view) . '">Vào lớp</a></div>';
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
        $assigned_teacher = intval(get_post_meta($class_id, 'az_teacher_user', true));
        $can_access = current_user_can('edit_post', $class_id) || $is_student || ($is_teacher && $assigned_teacher === intval($user->ID));
        if (!$can_access) {
            echo '<div class="wrap"><h1>Chi tiết lớp</h1><p>Không đủ quyền.</p></div>';
            return;
        }
        $students = AzAC_Core_Helper::get_class_students($class_id);
        $stats = AzAC_Admin_Stats::get_attendance_stats($class_id);
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
        echo '<button class="button azac-tab-btn" data-target="#azac-mid">Điểm danh giữa giờ</button>';
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
        echo '<script>(function(){function a(t,items){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var d=items&&items[id];if(d){el.checked=!!d.status;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');if(ne){ne.value=d.note||"";}}});}function f(t){var fd=new FormData();fd.append("action","azac_get_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){a(t,res.data.items||{});}}).catch(function(){});}function s(t){var ss=t==="check-in"?".azac-status":".azac-status-mid";var sn=t==="check-in"?".azac-note":".azac-note-mid";var items=[];document.querySelectorAll(ss).forEach(function(el){var id=parseInt(el.getAttribute("data-student"),10)||0;var st=el.checked?1:0;var ne=document.querySelector(sn+\'[data-student="\'+id+\'"]\');var nt=ne?String(ne.value||""):"";items.push({id:id,status:st,note:nt});});var fd=new FormData();fd.append("action","azac_save_attendance");fd.append("nonce",window.azacData.nonce);fd.append("class_id",window.azacData.classId);fd.append("type",t);fd.append("session_date",window.azacData.sessionDate||window.azacData.today);items.forEach(function(it,i){fd.append("items["+i+"][id]",it.id);fd.append("items["+i+"][status]",it.status);fd.append("items["+i+"][note]",it.note);});fetch(window.azacData.ajaxUrl,{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(res){if(res&&res.success){alert("Đã lưu "+res.data.inserted+" bản ghi");f(t);}else{alert("Lỗi lưu");}}).catch(function(){alert("Lỗi mạng");});}var b1=document.getElementById("azac-submit-checkin");if(b1){b1.addEventListener("click",function(){s("check-in");});}var b2=document.getElementById("azac-submit-mid");if(b2){b2.addEventListener("click",function(){s("mid-session");});}document.querySelectorAll(".azac-tab-btn").forEach(function(btn){btn.addEventListener("click",function(){document.querySelectorAll(".azac-tab-btn").forEach(function(b){b.classList.remove("button-primary");});btn.classList.add("button-primary");document.querySelectorAll(".azac-tab").forEach(function(t){t.classList.remove("active");});var tgt=btn.getAttribute("data-target");var el=document.querySelector(tgt);if(el){el.classList.add("active");}f(tgt==="#azac-checkin"?"check-in":"mid-session");});});f("check-in");f("mid-session");})();</script>';
        echo '</div>';
    }
}
