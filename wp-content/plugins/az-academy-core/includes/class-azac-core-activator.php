<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Activator
{
    public static function activate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'az_attendance';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            session_date date NOT NULL,
            attendance_type varchar(20) NOT NULL,
            status tinyint(1) NOT NULL DEFAULT 0,
            note text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY student_id (student_id),
            KEY session_date (session_date),
            UNIQUE KEY uniq_record (class_id, student_id, session_date, attendance_type)
        ) {$charset_collate};";

        $sessions_table = $wpdb->prefix . 'az_sessions';
        $sql_sessions = "CREATE TABLE {$sessions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL,
            session_date date NOT NULL,
            session_time time NULL,
            title varchar(191) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY session_date (session_date),
            UNIQUE KEY uniq_class_date (class_id, session_date)
        ) {$charset_collate};";

        dbDelta($sql);
        dbDelta($sql_sessions);

        add_option('azac_core_version', AZAC_CORE_VERSION);
        flush_rewrite_rules();

        add_role('az_teacher', 'Teacher', [
            'read' => true,
            'edit_posts' => true,
            'upload_files' => true,
            'az_manage_attendance' => true,
            'az_take_attendance' => true,
        ]);
        add_role('az_student', 'Student', [
            'read' => true,
            'az_take_attendance' => true,
        ]);
        $roles_to_remove = ['author', 'editor', 'contributor', 'subscriber'];
        foreach ($roles_to_remove as $role_slug) {
            $users = get_users(['role' => $role_slug, 'fields' => ['ID']]);
            foreach ($users as $u) {
                $uid = intval($u->ID);
                if ($uid && !user_can($uid, 'administrator')) {
                    $user_obj = new WP_User($uid);
                    $user_obj->set_role('az_student');
                    $existing = get_posts([
                        'post_type' => 'az_student',
                        'numberposts' => 1,
                        'meta_key' => 'az_user_id',
                        'meta_value' => $uid,
                        'post_status' => 'any',
                    ]);
                    if (!$existing) {
                        $display = $user_obj->display_name ?: $user_obj->user_login;
                        $post_id = wp_insert_post([
                            'post_type' => 'az_student',
                            'post_title' => $display,
                            'post_status' => 'publish',
                        ]);
                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, 'az_user_id', $uid);
                        }
                    }
                }
            }
            remove_role($role_slug);
        }
    }
}
