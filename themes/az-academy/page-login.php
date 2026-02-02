<?php
/**
 * Template Name: Login Page
 */

// Điều hướng nếu đã đăng nhập
if (is_user_logged_in()) {
    wp_redirect(admin_url());
    exit;
}

// Xử lý gửi Form
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['azac_login_submit'])) {

    // 1. KIỂM TRA HONEYPOT (BẪY BOT)
    // Nếu ô này không trống, nghĩa là Bot đã tự động điền vào
    if (!empty($_POST['confirm_user_extra_field'])) {
        $login_error = 'Phát hiện hành động bất thường. Vui lòng thử lại.';
    }
    // 2. Kiểm tra Nonce bảo mật
    else if (!isset($_POST['azac_login_nonce']) || !wp_verify_nonce($_POST['azac_login_nonce'], 'azac_login_action')) {
        $login_error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $creds = array(
            'user_login' => sanitize_text_field($_POST['user_login']),
            'user_password' => $_POST['user_pass'],
            'remember' => isset($_POST['rememberme']),
        );

        // Thực hiện đăng nhập
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
    <?php
    if (function_exists('wp_body_open')) {
        wp_body_open();
    }
    ?>

    <div class="auth-container">
        <div class="auth-branding">
            <div class="auth-branding-content">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="Az Academy Logo"
                    class="auth-branding-logo" />
                <h1>Chào mừng bạn quay lại!</h1>
                <p>Kết nối với hệ thống quản lý học tập chuyên nghiệp của Az Academy để theo dõi tiến độ và tham gia lớp học.</p>
            </div>
        </div>

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
                        <div id="login-message" class="auth-message error"
                            style="display:block; color: #e11d48; background: #fff1f2; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; border: 1px solid #fda4af;">
                            <?php echo $login_error; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display:none !important; visibility:hidden !important; position:absolute; left:-9999px;">
                        <input type="text" name="confirm_user_extra_field" id="confirm_user_extra_field" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" name="azac_login_submit" class="btn-primary" id="btn-login" style="cursor: pointer;">
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