<?php
if (!defined('ABSPATH')) {
    exit;
}

// Task: Security Force Redirect (Header Level)
if (strpos($_SERVER['REQUEST_URI'], '/register') !== false) {
    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('az_manager'))) {
        wp_redirect(home_url());
        exit;
    }
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="site-header">
    <div class="container site-header-container">
        <div class="site-branding">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo-link">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="Az Academy"
                    class="site-logo">
            </a>
        </div>

        <div class="header-right header-right-group">
            <?php if (has_nav_menu('primary')): ?>
                <nav class="main-navigation">
                    <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'az-menu', 'fallback_cb' => false]); ?>
                </nav>
            <?php endif; ?>

            <div class="header-auth">
                <?php if (is_user_logged_in()): ?>
                    <?php $current_user = wp_get_current_user(); ?>
                    <span class="header-user-welcome">Chào,
                        <strong><?php echo esc_html($current_user->display_name); ?></strong></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=azac-attendance')); ?>"
                        class="az-btn az-btn-primary az-btn-sm header-btn-admin">Vào trang
                        quản trị</a>
                <?php else: ?>
                        <a href="<?php echo esc_url(wp_login_url()); ?>" class="az-btn az-btn-outline az-btn-sm header-btn-login">Đăng nhập</a>
                <?php endif; ?>
                
                <?php
                // Task 1: Only show Register link for Admin/Manager
                if (current_user_can('administrator') || current_user_can('az_manager')):
                    ?>
                    <a href="<?php echo esc_url(home_url('/register')); ?>" class="az-btn az-btn-primary az-btn-sm header-btn-register">Đăng ký học viên mới</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
