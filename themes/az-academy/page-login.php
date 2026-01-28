<?php
/**
 * Template Name: Login Page
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(admin_url());
    exit;
}

// Handle Form Submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['azac_login_submit'])) {
    // Check nonce
    if (!isset($_POST['azac_login_nonce']) || !wp_verify_nonce($_POST['azac_login_nonce'], 'azac_login_action')) {
        $login_error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $creds = array(
            'user_login' => sanitize_text_field($_POST['user_login']),
            'user_password' => $_POST['user_pass'],
            'remember' => isset($_POST['rememberme']),
        );

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            $login_error = 'Thông tin đăng nhập không chính xác.';
            if ($user->get_error_code() == 'empty_username' || $user->get_error_code() == 'empty_password') {
                $login_error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
            }
        } else {
            wp_redirect(admin_url());
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/css/auth-style.css?v=1.1">
    <?php wp_head(); ?>
</head>

<body>

    <div class="auth-container">
        <!-- Left Side: Branding -->
        <div class="auth-branding">
            <div class="auth-branding-content">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="Az Academy Logo"
                    class="auth-branding-logo" />
                <h1>Chào mừng bạn quay lại!</h1>
                <p>Kết nối với hệ thống quản lý học tập chuyên nghiệp của Az Academy để theo dõi tiến độ và tham gia lớp
                    học.</p>
                <!-- Register button removed -->
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="auth-form-wrapper">
            <div class="auth-form-content">
                <div class="auth-header">
                    <h2>Đăng nhập</h2>
                    <p>Nhập thông tin tài khoản của bạn</p>
                </div>

                <form id="azac-login-form" method="post" action="">
                    <?php wp_nonce_field('azac_login_action', 'azac_login_nonce'); ?>

                    <div class="form-group">
                        <label for="user_login">Email hoặc Tên đăng nhập</label>
                        <input type="text" name="user_login" id="user_login" class="form-control" required
                            placeholder="nhap_email@example.com"
                            value="<?php echo isset($_POST['user_login']) ? esc_attr($_POST['user_login']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="user_pass">Mật khẩu</label>
                        <input type="password" name="user_pass" id="user_pass" class="form-control" required
                            placeholder="••••••••">
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="rememberme" id="rememberme" value="forever">
                        <label for="rememberme" style="margin:0; font-weight:400;">Ghi nhớ đăng nhập</label>
                    </div>

                    <?php if ($login_error): ?>
                        <div id="login-message" class="auth-message error" style="display:block;">
                            <?php echo $login_error; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="azac_login_submit" class="btn-primary" id="btn-login">
                        <span class="btn-text">Đăng nhập</span>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="<?php echo home_url(); ?>"
                        style="color: #64748b; text-decoration: none; font-size: 0.9rem; margin-right: 15px;">← Quay lại Trang chủ</a>
                    <a href="<?php echo wp_lostpassword_url(); ?>"
                        style="color: #64748b; text-decoration: none; font-size: 0.9rem;">Quên mật khẩu?</a>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>

</html>