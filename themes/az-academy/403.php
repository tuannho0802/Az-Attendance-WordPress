<?php
get_header();
?>

<div class="error-page-container az-container" style="padding: 100px 20px; text-align: center; min-height: 60vh;">
    <div class="error-icon" style="font-size: 120px; line-height: 1; margin-bottom: 20px; color: #f39c12;">
        <i class="dashicons dashicons-lock" style="font-size: inherit; width: auto; height: auto;"></i>
    </div>
    <h1 style="font-size: 48px; margin-bottom: 15px; color: #2c3e50;">403 - Không có quyền truy cập</h1>
    <p style="font-size: 18px; color: #7f8c8d; max-width: 600px; margin: 0 auto 30px;">
        Rất tiếc, bạn không có quyền truy cập vào trang này. Có thể phiên đăng nhập của bạn đã hết hạn hoặc tài khoản không được phép. Vui lòng đăng nhập lại.
    </p>
    <div class="error-actions">
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="az-btn az-btn-primary" style="padding: 12px 30px; font-size: 16px;">
            Quay lại đăng nhập
        </a>
    </div>
</div>

<?php
get_footer();
?>
