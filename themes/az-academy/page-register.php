<?php
/**
 * Template Name: Register Page
 */

// Auto-redirect removed to allow Admin/Manager access
// Controlled by AzAC_Core_Security::block_register_page_access

// Handle Registration Submission
$reg_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['azac_register_submit'])) {
    // Check nonce
    if (!isset($_POST['azac_register_nonce']) || !wp_verify_nonce($_POST['azac_register_nonce'], 'azac_register_action')) {
        $reg_error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $password = $_POST['user_pass'];
        $confirm = $_POST['user_pass_confirm'];
        $phone = sanitize_text_field($_POST['az_phone']);
        $business = sanitize_text_field($_POST['az_business_field']);

        if ($password !== $confirm) {
            $reg_error = 'Mật khẩu nhập lại không khớp.';
        } elseif (username_exists($username) || email_exists($email)) {
            $reg_error = 'Tên đăng nhập hoặc Email đã tồn tại.';
        } else {
            // Create user
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => $password,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
                'role' => 'az_student',
            ]);

            if (is_wp_error($user_id)) {
                $reg_error = $user_id->get_error_message();
            } else {
                // Update User Meta
                update_user_meta($user_id, 'az_phone', $phone);
                update_user_meta($user_id, 'az_business_field', $business);

                // Auto Login
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                // Redirect
                wp_redirect(admin_url());
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - <?php bloginfo('name'); ?></title>
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
                <h1>Tham gia cùng chúng tôi!</h1>
                <p>Tạo tài khoản ngay hôm nay để bắt đầu hành trình học tập và phát triển cùng Az Academy.</p>
                <a href="<?php echo home_url('/login'); ?>" class="btn-outline">Đã có tài khoản? Đăng nhập</a>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="auth-form-wrapper">
            <div class="auth-form-content">
                <div class="auth-header">
                    <h2>Tạo tài khoản mới</h2>
                    <p>Điền thông tin bên dưới để đăng ký</p>
                </div>

                <form id="azac-register-form" method="post" action="">
                    <?php wp_nonce_field('azac_register_action', 'azac_register_nonce'); ?>

                    <div class="form-group">
                        <label for="first_name">Họ và tên lót *</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required
                            placeholder="Nguyễn Văn"
                            value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Tên *</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required placeholder="A"
                            value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="user_login">Tên đăng nhập *</label>
                        <input type="text" name="user_login" id="user_login" class="form-control" required
                            placeholder="username123"
                            value="<?php echo isset($_POST['user_login']) ? esc_attr($_POST['user_login']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="user_email">Email *</label>
                        <input type="email" name="user_email" id="user_email" class="form-control" required
                            placeholder="email@example.com"
                            value="<?php echo isset($_POST['user_email']) ? esc_attr($_POST['user_email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="az_phone">Số điện thoại *</label>
                        <input type="text" name="az_phone" id="az_phone" class="form-control" required
                            placeholder="0912345678"
                            value="<?php echo isset($_POST['az_phone']) ? esc_attr($_POST['az_phone']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="az_business_field">Lĩnh vực kinh doanh</label>
                        <input type="text" name="az_business_field" id="az_business_field" class="form-control"
                            placeholder="Marketing, Bất động sản..."
                            value="<?php echo isset($_POST['az_business_field']) ? esc_attr($_POST['az_business_field']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="user_pass">Mật khẩu *</label>
                        <input type="password" name="user_pass" id="user_pass" class="form-control" required
                            placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label for="user_pass_confirm">Nhập lại mật khẩu *</label>
                        <input type="password" name="user_pass_confirm" id="user_pass_confirm" class="form-control"
                            required placeholder="••••••••">
                    </div>

                    <?php if ($reg_error): ?>
                        <div id="register-message" class="auth-message error" style="display:block;">
                            <?php echo $reg_error; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="azac_register_submit" class="btn-primary" id="btn-register">
                        <span class="btn-text">Đăng ký tài khoản</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const regForm = document.getElementById('azac-register-form');
            const msgBox = document.getElementById('register-message');
            const btnSubmit = document.getElementById('btn-register');

            regForm.addEventListener('submit', function (e) {
                // Basic Client-side Validation
                const pass = document.getElementById('user_pass').value;
                const confirm = document.getElementById('user_pass_confirm').value;

                if (pass !== confirm) {
                    e.preventDefault();
                    if (!msgBox) { // Create if not exists (though php renders it if error)
                        // Just alert for now or let PHP handle it mostly
                        alert('Mật khẩu nhập lại không khớp.');
                    } else {
                        msgBox.className = 'auth-message error';
                        msgBox.style.display = 'block';
                        msgBox.innerHTML = 'Mật khẩu nhập lại không khớp.';
                    }
                    return;
                }
            });
        });
    </script>

    <?php wp_footer(); ?>
</body>

</html>