<?php
if (!defined('ABSPATH')) {
    exit;
}
class AzAC_Admin_Assets
{
    public static function enqueue_admin_assets($hook)
    {
        wp_enqueue_style('azac-admin-style', AZAC_CORE_URL . 'admin/css/admin-style.css', [], AZAC_CORE_VERSION);

        // Teacher View Independent Styles
        $user = wp_get_current_user();
        if (in_array('az_teacher', $user->roles, true)) {
            wp_enqueue_style('azac-teacher-view', AZAC_CORE_URL . 'admin/css/azac-teacher-view.css', [], AZAC_CORE_VERSION);
        }

        // Enqueue Add Student JS only on the specific page
        if (isset($_GET['page']) && $_GET['page'] === 'azac-add-student') {
            wp_enqueue_script('azac-add-student-js', AZAC_CORE_URL . 'admin/js/azac-add-student.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_localize_script('azac-add-student-js', 'azac_obj', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_ajax_nonce')
            ]);
        }

        wp_enqueue_script('azac-admin-js', AZAC_CORE_URL . 'admin/js/admin.js', ['jquery'], AZAC_CORE_VERSION, true);
        // Global Toast assets on all admin pages
        if (!wp_script_is('azac-toast-js', 'enqueued')) {
            wp_enqueue_style('azac-toast-style', AZAC_CORE_URL . 'admin/css/azac-toast.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-toast-js', AZAC_CORE_URL . 'admin/js/azac-toast.js', [], AZAC_CORE_VERSION, true);
        }
        // Global Modal assets on all admin pages
        if (!wp_script_is('azac-modal-js', 'enqueued')) {
            wp_enqueue_style('azac-modal-style', AZAC_CORE_URL . 'admin/css/azac-modal.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-modal-js', AZAC_CORE_URL . 'admin/js/azac-modal.js', ['jquery'], AZAC_CORE_VERSION, true);
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'az_class' && in_array($hook, ['post.php', 'post-new.php'], true)) {
            $handle = 'azac-class-edit-js';
            wp_enqueue_script($handle, AZAC_CORE_URL . 'admin/js/class-edit.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_style('azac-class-edit-style', AZAC_CORE_URL . 'admin/css/class-edit.css', [], AZAC_CORE_VERSION);
            
            $user = wp_get_current_user();
            $class_id = get_the_ID();
            if (!$class_id && isset($_GET['post'])) {
                $class_id = absint($_GET['post']);
            }

            $azac_params = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_ajax_nonce'),
                'classId' => $class_id,
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isManager' => current_user_can('edit_posts'),
            ];

            // Primary method
            wp_localize_script($handle, 'azac_params', $azac_params);

            // Fallback method: Inline Script
            $inline_script = 'window.azac_params = ' . json_encode($azac_params) . ';';
            wp_add_inline_script($handle, $inline_script, 'before');
        }
        if ($hook === 'toplevel_page_azac-attendance') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            
            // Enqueue Select2
            wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

            // Enqueue Datepicker
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2');
            wp_enqueue_script('jquery-ui-datepicker');

            $user = wp_get_current_user();
            $can_manage = current_user_can('administrator') || current_user_can('manager') || current_user_can('edit_posts');
            $azac_list = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('azac_create_class'),
                'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                'isTeacher' => in_array('az_teacher', $user->roles, true),
                'isAdmin' => $can_manage,
                'isRealAdmin' => in_array('administrator', (array) $user->roles, true) ? true : false,
                'canManage' => $can_manage,
                'isStudent' => in_array('az_student', $user->roles, true),
                'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                'deleteSessionNonce' => wp_create_nonce('azac_delete_session'),
                'bulkDeleteNonce' => wp_create_nonce('azac_bulk_delete_nonce'),
                'palette' => AzAC_Core_Admin::$palette,
            ];
            wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $azac_list);
        }
        if ($hook === 'azac-attendance_page_azac-classes-list') {
            $cid = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
            if ($cid) {
                wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
                if (!wp_script_is('chartjs', 'enqueued')) {
                    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
                }

                // Enqueue jQuery UI Datepicker & CSS
                wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2');
                wp_enqueue_script('jquery-ui-datepicker');

                // Toast Modules (Independent)
                wp_enqueue_style('azac-toast-style', plugins_url('../admin/css/azac-toast.css', __FILE__), [], time());
                wp_enqueue_script('azac-toast-js', plugins_url('../admin/js/azac-toast.js', __FILE__), [], time(), true);

                wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-session-js', AZAC_CORE_URL . 'admin/js/attendance-session.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-charts-js', AZAC_CORE_URL . 'admin/js/attendance-charts.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-api-js', AZAC_CORE_URL . 'admin/js/attendance-api.js', ['jquery', 'azac-attendance-utils', 'azac-attendance-charts-js'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-mid-js', AZAC_CORE_URL . 'admin/js/attendance-mid.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-ui-js', AZAC_CORE_URL . 'admin/js/attendance-ui.js', ['jquery', 'azac-attendance-utils', 'azac-attendance-session-js', 'azac-attendance-api-js', 'azac-attendance-mid-js'], AZAC_CORE_VERSION, true);
                $user = wp_get_current_user();
                $review_url = AzAC_Core_Helper::get_qr_review_url($cid);
                wp_localize_script('azac-attendance-mid-js', 'AZAC_MID', [
                    'reviewUrl' => $review_url,
                    'isTeacher' => in_array('az_teacher', $user->roles, true),
                    'isAdmin' => in_array('administrator', $user->roles, true),
                ]);
            } else {
                wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
                wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                if (!in_array('az_student', $user->roles, true)) {
                    wp_enqueue_script('azac-attendance-list-stats-js', AZAC_CORE_URL . 'admin/js/attendance-list-stats.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                }
                $user = wp_get_current_user();
                $azac_list_cls = [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('azac_create_class'),
                    'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                    'isTeacher' => in_array('az_teacher', $user->roles, true),
                    'isAdmin' => $can_manage,
                    'isRealAdmin' => current_user_can('manage_options'),
                    'canManage' => $can_manage,
                    'isStudent' => in_array('az_student', $user->roles, true),
                    'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                    'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
                    'palette' => AzAC_Core_Admin::$palette, // Unified Palette (Confirmed)
                ];
                wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $azac_list_cls);
                if (!in_array('az_student', $user->roles, true)) {
                    wp_localize_script('azac-attendance-list-stats-js', 'AZAC_LIST', $azac_list_cls);
                }
            }
        } elseif ($hook === 'azac-attendance_page_azac-reviews') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            if (!wp_script_is('chartjs', 'enqueued')) {
                wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            }
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-ui-js', AZAC_CORE_URL . 'admin/js/attendance-ui.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-reviews-js', AZAC_CORE_URL . 'admin/js/reviews.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
        }
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page === 'azac-classes-list') {
            // Enqueue Teacher View CSS if user is teacher
            $user = wp_get_current_user();
            if (in_array('az_teacher', (array) $user->roles, true)) {
                wp_enqueue_style('azac-teacher-view-style', AZAC_CORE_URL . 'admin/css/azac-teacher-view.css', [], AZAC_CORE_VERSION);
            }

            $cid = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
            if ($cid) {
                wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
                if (!wp_script_is('chartjs', 'enqueued')) {
                    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
                }

                // Enqueue jQuery UI Datepicker & CSS
                wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2');
                wp_enqueue_script('jquery-ui-datepicker');

                // Toast Modules (Independent) - Added here also for safety
                if (!wp_script_is('azac-toast-js', 'enqueued')) {
                    wp_enqueue_style('azac-toast-style', AZAC_CORE_URL . 'admin/css/azac-toast.css', [], time());
                    wp_enqueue_script('azac-toast-js', AZAC_CORE_URL . 'admin/js/azac-toast.js', [], time(), true);
                }

                wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-session-js', AZAC_CORE_URL . 'admin/js/attendance-session.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-charts-js', AZAC_CORE_URL . 'admin/js/attendance-charts.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-api-js', AZAC_CORE_URL . 'admin/js/attendance-api.js', ['jquery', 'azac-attendance-utils', 'azac-attendance-charts-js'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-mid-js', AZAC_CORE_URL . 'admin/js/attendance-mid.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-ui-js', AZAC_CORE_URL . 'admin/js/attendance-ui.js', ['jquery', 'azac-attendance-utils', 'azac-attendance-session-js', 'azac-attendance-api-js', 'azac-attendance-mid-js'], AZAC_CORE_VERSION, true);
                $user = wp_get_current_user();
                $review_url2 = AzAC_Core_Helper::get_qr_review_url($cid);
                wp_localize_script('azac-attendance-mid-js', 'AZAC_MID', [
                    'reviewUrl' => $review_url2,
                    'isTeacher' => in_array('az_teacher', $user->roles, true),
                    'isAdmin' => in_array('administrator', $user->roles, true),
                ]);
            } else {
                wp_enqueue_style('azac-attendance-list-style', AZAC_CORE_URL . 'admin/css/attendance-list.css', [], AZAC_CORE_VERSION);
                wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-list-sessions-js', AZAC_CORE_URL . 'admin/js/attendance-list-sessions.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                wp_enqueue_script('azac-attendance-list-stats-js', AZAC_CORE_URL . 'admin/js/attendance-list-stats.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
                $user = wp_get_current_user();
                $obj = [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('azac_create_class'),
                    'listSessionsNonce' => wp_create_nonce('azac_list_sessions'),
                    'isTeacher' => in_array('az_teacher', $user->roles, true),
                    'isAdmin' => in_array('administrator', $user->roles, true),
                    'isManager' => in_array('az_manager', (array) $user->roles),
                    'isStudent' => in_array('az_student', $user->roles, true),
                    'updateStatusNonce' => wp_create_nonce('azac_update_class_status'),
                    'deleteClassNonce' => wp_create_nonce('azac_delete_class'),
                    'studentStatsNonce' => wp_create_nonce('azac_student_stats'),
                    'palette' => AzAC_Core_Admin::$palette, // Unified Palette
                ];
                wp_localize_script('azac-attendance-list-sessions-js', 'AZAC_LIST', $obj);
                wp_localize_script('azac-attendance-list-stats-js', 'AZAC_LIST', $obj);
            }
        } elseif ($page === 'azac-reviews') {
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            if (!wp_script_is('chartjs', 'enqueued')) {
                wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            }
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-ui-js', AZAC_CORE_URL . 'admin/js/attendance-ui.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
            if (!wp_script_is('azac-reviews-js', 'enqueued')) {
                wp_enqueue_script('azac-reviews-js', AZAC_CORE_URL . 'admin/js/reviews.js', ['jquery', 'chartjs'], AZAC_CORE_VERSION, true);
            }
        } elseif ($page === 'azac-teacher-attendance') {
            // Enqueue Teacher View CSS
            $user = wp_get_current_user();
            if (in_array('az_teacher', (array) $user->roles, true)) {
                wp_enqueue_style('azac-teacher-view-style', AZAC_CORE_URL . 'admin/css/azac-teacher-view.css', [], AZAC_CORE_VERSION);
            }
            wp_enqueue_style('azac-attendance-style', AZAC_CORE_URL . 'admin/css/attendance.css', [], AZAC_CORE_VERSION);
            wp_enqueue_script('azac-attendance-utils', AZAC_CORE_URL . 'admin/js/attendance-utils.js', ['jquery'], AZAC_CORE_VERSION, true);
            wp_enqueue_script('azac-attendance-session-js', AZAC_CORE_URL . 'admin/js/attendance-session.js', ['jquery', 'azac-attendance-utils'], AZAC_CORE_VERSION, true);
        }
    }
}
