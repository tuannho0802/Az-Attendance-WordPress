<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header style="background:#15345a;color:#fff;padding:15px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;">
        <div class="site-branding">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo-link">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="Az Academy"
                    class="site-logo">
            </a>
        </div>

        <div class="header-right" style="display:flex;align-items:center;gap:20px;">
            <?php if (has_nav_menu('primary')): ?>
                <nav class="main-navigation">
                    <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'az-menu', 'fallback_cb' => false]); ?>
                </nav>
            <?php endif; ?>

            <div class="header-auth">
                <?php if (is_user_logged_in()): ?>
                    <?php $current_user = wp_get_current_user(); ?>
                    <span style="margin-right:10px;font-size:14px;">Chào,
                        <strong><?php echo esc_html($current_user->display_name); ?></strong></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=azac-attendance')); ?>"
                        class="az-btn az-btn-primary az-btn-sm" style="background:#0f6d5e;border-color:#0f6d5e;">Vào trang
                        quản trị</a>
                <?php else: ?>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="az-btn az-btn-outline az-btn-sm"
                        style="color:#fff;border-color:#fff;margin-right:10px;">Đăng nhập</a>
                    <a href="<?php echo esc_url(home_url('/register')); ?>" class="az-btn az-btn-primary az-btn-sm"
                        style="background:#0f6d5e;border-color:#0f6d5e;">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
