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
        add_action('add_meta_boxes', [__CLASS__, 'add_class_meta_boxes']);
        add_action('add_meta_boxes', [__CLASS__, 'add_class_students_meta_box']);
        add_action('save_post_az_class', [__CLASS__, 'save_class_meta'], 10, 2);
        add_action('save_post_az_class', [__CLASS__, 'save_class_students_meta'], 10, 2);
        add_filter('manage_az_class_posts_columns', [__CLASS__, 'columns_az_class']);
        add_action('manage_az_class_posts_custom_column', [__CLASS__, 'column_content_az_class'], 10, 2);
        add_filter('manage_az_student_posts_columns', [__CLASS__, 'columns_az_student']);
        add_action('manage_az_student_posts_custom_column', [__CLASS__, 'column_content_az_student'], 10, 2);
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
        $giang_vien = get_post_meta($post->ID, 'az_giang_vien', true);
        $tong_so_buoi = get_post_meta($post->ID, 'az_tong_so_buoi', true);
        $so_hoc_vien = get_post_meta($post->ID, 'az_so_hoc_vien', true);
        $teacher_user = get_post_meta($post->ID, 'az_teacher_user', true);
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', $user->roles, true);
        $disabled = $is_admin ? '' : ' disabled';
        echo '<p><label for="az_giang_vien">Giảng viên</label><br />';
        echo '<input type="text" id="az_giang_vien" name="az_giang_vien" class="regular-text" value="' . esc_attr($giang_vien) . '"' . $disabled . ' /></p>';
        echo '<p><label for="az_tong_so_buoi">Tổng số buổi</label><br />';
        echo '<input type="number" id="az_tong_so_buoi" name="az_tong_so_buoi" min="0" value="' . esc_attr($tong_so_buoi) . '"' . $disabled . ' /></p>';
        echo '<p><label for="az_so_hoc_vien">Số học viên</label><br />';
        echo '<input type="number" id="az_so_hoc_vien" name="az_so_hoc_vien" min="0" value="' . esc_attr($so_hoc_vien) . '"' . $disabled . ' /></p>';
        $teachers = get_users(['role' => 'az_teacher']);
        echo '<p><label for="az_teacher_user">Giảng viên (User)</label><br />';
        echo '<select id="az_teacher_user" name="az_teacher_user"' . $disabled . '>';
        echo '<option value="">-- Chọn giảng viên --</option>';
        foreach ($teachers as $t) {
            $selected = selected(intval($teacher_user), intval($t->ID), false);
            echo '<option value="' . esc_attr($t->ID) . '" ' . $selected . '>' . esc_html($t->display_name) . '</option>';
        }
        echo '</select></p>';
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
        $students = get_posts([
            'post_type' => 'az_student',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if ($is_admin) {
            echo '<div style="margin-bottom:12px;"><input type="text" id="azac_new_student_name" class="regular-text" placeholder="Họ và Tên học viên" /> ';
            echo '<input type="email" id="azac_new_student_email" class="regular-text" placeholder="Email (tùy chọn)" /> ';
            echo '<button type="button" class="button" id="azac_add_student_btn" data-class="' . esc_attr($post->ID) . '">Thêm học viên</button></div>';
            echo '<div id="azac_students_grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
            foreach ($students as $s) {
                $checked = in_array($s->ID, $selected, true) ? 'checked' : '';
                echo '<label><input type="checkbox" name="az_students[]" value="' . esc_attr($s->ID) . '" ' . $checked . ' /> ' . esc_html($s->post_title) . '</label>';
            }
            echo '</div>';
            $nonce = wp_create_nonce('azac_add_student');
            echo '<script>window.azacClassEditData=' . wp_json_encode([
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
            ]) . ';</script>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
            foreach ($students as $s) {
                $checked = in_array($s->ID, $selected, true) ? '✓ ' : '';
                echo '<div>' . esc_html($checked . $s->post_title) . '</div>';
            }
            echo '</div>';
        }
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
        $user = wp_get_current_user();
        if (!in_array('administrator', $user->roles, true)) {
            return;
        }
        if (isset($_POST['az_giang_vien'])) {
            update_post_meta($post_id, 'az_giang_vien', sanitize_text_field($_POST['az_giang_vien']));
        }
        if (isset($_POST['az_tong_so_buoi'])) {
            update_post_meta($post_id, 'az_tong_so_buoi', absint($_POST['az_tong_so_buoi']));
        }
        if (isset($_POST['az_so_hoc_vien'])) {
            update_post_meta($post_id, 'az_so_hoc_vien', absint($_POST['az_so_hoc_vien']));
        }
        if (isset($_POST['az_teacher_user'])) {
            update_post_meta($post_id, 'az_teacher_user', absint($_POST['az_teacher_user']));
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
        update_post_meta($post_id, 'az_students', array_values(array_filter($ids)));
        update_post_meta($post_id, 'az_so_hoc_vien', count($ids));
    }
}
