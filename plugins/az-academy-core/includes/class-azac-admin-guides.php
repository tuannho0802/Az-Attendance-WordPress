<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Admin_Guides
{
    public static function register()
    {
        add_action('admin_menu', [__CLASS__, 'add_guide_menus']);
    }

    public static function add_guide_menus()
    {
        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        // Determine visibility based on roles
        $is_admin = in_array('administrator', $roles);
        $is_teacher = in_array('az_teacher', $roles);
        $is_student = in_array('az_student', $roles);

        // Admin Guide (Admin Only) - Priority 93
        if ($is_admin) {
            add_menu_page(
                'Hướng dẫn Admin',
                'Hướng dẫn Admin',
                'manage_options',
                'az-admin-guide',
                function () {
                    self::render_page('az_guide_admin_private', 'Hướng dẫn Admin');
                },
                'dashicons-shield',
                1
            );
        }

        // Student Guide - Priority 90
        if ($is_admin || $is_student) {
            add_menu_page(
                'Hướng dẫn Học viên',
                'Hướng dẫn Học viên',
                'read', // Capability required
                'az-student-guide',
                function () {
                    self::render_page('az_guide_student', 'Hướng dẫn Học viên');
                },
                'dashicons-book',
                1
            );
        }

        // Teacher Guide - Priority 91
        if ($is_admin || $is_teacher) {
            add_menu_page(
                'Hướng dẫn Giảng viên',
                'Hướng dẫn Giảng viên',
                'read',
                'az-teacher-guide',
                function () {
                    self::render_page('az_guide_teacher', 'Hướng dẫn Giảng viên');
                },
                'dashicons-welcome-learn-more',
                1
            );
        }

        // Technical Docs (Admin Only) - Priority 92
        if ($is_admin) {
            add_menu_page(
                'Tài liệu Kỹ thuật',
                'Tài liệu Kỹ thuật',
                'manage_options',
                'az-admin-docs',
                function () {
                    self::render_page('az_guide_admin', 'Tài liệu Kỹ thuật');
                },
                'dashicons-media-code',
                1
            );
        }
    }

    public static function render_page($option_key, $title)
    {
        // Handle Save (Admin Only)
        if (current_user_can('manage_options') && isset($_POST['az_guide_content']) && isset($_POST['az_guide_nonce'])) {
            if (wp_verify_nonce($_POST['az_guide_nonce'], 'save_guide_' . $option_key)) {
                $content = wp_kses_post($_POST['az_guide_content']);
                update_option($option_key, $content);
                echo '<div class="notice notice-success is-dismissible"><p>Đã cập nhật hướng dẫn thành công!</p></div>';
            }
        }

        $content = get_option($option_key, '');

        ?>
        <div class="wrap az-guide-page">
            <h1 class="wp-heading-inline" style="color: #0f6d5e; font-weight: 700; margin-bottom: 20px;">
                <?php echo esc_html($title); ?>
            </h1>
            <hr class="wp-header-end">

            <div class="az-guide-content-wrapper"
                style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 1200px; margin-top: 10px;">
                <?php if (current_user_can('manage_options')): ?>
                    <!-- Admin View: Editor -->
                    <form method="post">
                        <?php wp_nonce_field('save_guide_' . $option_key, 'az_guide_nonce'); ?>
                        <?php
                        wp_editor($content, 'az_guide_content', [
                            'textarea_name' => 'az_guide_content',
                            'media_buttons' => true,
                            'textarea_rows' => 20,
                            'teeny' => false,
                            'quicktags' => true
                        ]);
                        ?>
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Cập nhật hướng dẫn">
                        </p>
                    </form>
                <?php else: ?>
                    <!-- User View: Read Only -->
                    <div class="az-guide-body" style="line-height: 1.6; font-size: 15px; color: #333;">
                        <?php
                        if (!empty($content)) {
                            echo wp_kses_post($content); // wp_kses_post allows safe HTML
                        } else {
                            echo '<p><em>Chưa có nội dung hướng dẫn.</em></p>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <style>
            .az-guide-body h2 {
                font-size: 1.5em;
                margin-top: 1.5em;
                color: #0b3d3b;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }

            .az-guide-body h3 {
                font-size: 1.3em;
                margin-top: 1.2em;
                color: #0f6d5e;
            }

            .az-guide-body ul,
            .az-guide-body ol {
                margin-left: 20px;
            }

            .az-guide-body img {
                max-width: 100%;
                height: auto;
                border: 1px solid #ddd;
                padding: 5px;
                border-radius: 4px;
            }

            .az-guide-body blockquote {
                border-left: 4px solid #0f6d5e;
                margin: 0;
                padding-left: 15px;
                color: #666;
                font-style: italic;
                background: #f9f9f9;
                padding: 10px;
            }
        </style>
        <?php
    }
}
