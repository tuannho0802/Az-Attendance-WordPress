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
        $is_manager = in_array('az_manager', $roles); // Added Manager
        $is_teacher = in_array('az_teacher', $roles);
        $is_student = in_array('az_student', $roles);

        // Admin Guide (Admin & Manager) - Priority 93
        if ($is_admin || $is_manager) {
            add_menu_page(
                'Hướng dẫn Admin',
                'Hướng dẫn Admin',
                'manage_options',
                'az-admin-guide',
                function () {
                    self::render_page_from_slug('huong-dan-admin', 'Hướng dẫn Admin');
                },
                'dashicons-shield',
                1
            );
        }

        // Student Guide - Priority 90
        if ($is_admin || $is_manager || $is_student) {
            add_menu_page(
                'Hướng dẫn Học viên',
                'Hướng dẫn Học viên',
                'read', // Capability required
                'az-student-guide',
                function () {
                    self::render_page_from_slug('huong-dan-hoc-vien', 'Hướng dẫn Học viên');
                },
                'dashicons-book',
                1
            );
        }

        // Teacher Guide - Priority 91
        if ($is_admin || $is_manager || $is_teacher) {
            add_menu_page(
                'Hướng dẫn Giảng viên',
                'Hướng dẫn Giảng viên',
                'read',
                'az-teacher-guide',
                function () {
                    self::render_page_from_slug('huong-dan-giang-vien', 'Hướng dẫn Giảng viên');
                },
                'dashicons-welcome-learn-more',
                1
            );
        }

        // Technical Docs (Admin & Manager) - Priority 92
        if ($is_admin || $is_manager) {
            add_menu_page(
                'Tài liệu Kỹ thuật',
                'Tài liệu Kỹ thuật',
                'manage_options',
                'az-admin-docs',
                function () {
                    self::render_page_from_slug('huong-dan-ky-thuat', 'Tài liệu Kỹ thuật');
                },
                'dashicons-media-code',
                1
            );
        }
    }

    /**
     * Render guide content from a WordPress Page based on slug
     */
    public static function render_page_from_slug($slug, $title)
    {
        $page = get_page_by_path($slug);
        $is_admin = current_user_can('manage_options');

        ?>
        <div class="wrap az-guide-page">
            <h1 class="wp-heading-inline" style="color: #0f6d5e; font-weight: 700; margin-bottom: 20px;">
                <?php echo esc_html($title); ?>
            </h1>
        <?php if ($is_admin && $page): ?>
            <a href="<?php echo get_edit_post_link($page->ID); ?>" class="page-title-action" target="_blank">
                <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span> Chỉnh sửa nội dung trang này
            </a>
        <?php endif; ?>
            <hr class="wp-header-end">
        
            <div class="az-guide-content-wrapper"
                style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 1200px; margin-top: 10px;">
                <?php if ($page): ?>
                    <div class="az-guide-body">
                        <?php echo apply_filters('the_content', $page->post_content); ?>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning inline" style="margin: 0;">
                        <p>
                            Trang hướng dẫn chưa được tạo. Vui lòng tạo Page với slug <code><?php echo esc_html($slug); ?></code> để
                        hiển thị nội dung tại đây.
                    </p>
                            <?php if ($is_admin): ?>
                                <p>
                                    <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-primary">Tạo
                                        trang mới</a>
                                </p>
                            <?php endif; ?>
                            </div>
                <?php endif; ?>
            </div>
        </div>
        <style>
            /* Ensure admin styles don't break content */
            .az-guide-body {
                font-size: 15px;
                line-height: 1.6;
                color: #333;
            }
        
            .az-guide-body img {
                max-width: 100%;
                height: auto;
            }
        
            .az-guide-body h2 {
                color: #0b3d3b;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-top: 1.5em;
            }
        
            .az-guide-body ul,
            .az-guide-body ol {
                margin-left: 20px;
            }
        
            .az-guide-body a {
                color: #0f6d5e;
                text-decoration: none;
            }
        
            .az-guide-body a:hover {
                text-decoration: underline;
            }
        
            /* Reset some WP Admin styles inside the content */
            .az-guide-body h1,
            .az-guide-body h2,
            .az-guide-body h3,
            .az-guide-body h4,
            .az-guide-body h5,
            .az-guide-body h6 {
                font-weight: 600;
                margin-bottom: 0.5em;
            }
        </style>
        <?php
    }
}
