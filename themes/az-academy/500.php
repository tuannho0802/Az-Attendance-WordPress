<?php
/**
 * Custom 500 Error Page
 * Note: This file can be used as a template or as a static page for server-level error handling.
 */
get_header();
?>

<div class="error-page-container az-container" style="padding: 100px 20px; text-align: center; min-height: 60vh;">
    <div class="error-icon" style="font-size: 120px; line-height: 1; margin-bottom: 20px; color: #7f8c8d;">
        <i class="dashicons dashicons-admin-generic" style="font-size: inherit; width: auto; height: auto;"></i>
    </div>
    <h1 style="font-size: 48px; margin-bottom: 15px; color: #2c3e50;">500 - Lỗi máy chủ nội bộ</h1>
    <p style="font-size: 18px; color: #7f8c8d; max-width: 600px; margin: 0 auto 30px;">
        Đã xảy ra lỗi máy chủ. Vui lòng thử lại sau hoặc liên hệ quản trị viên.
    </p>
    <div class="error-actions">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="az-btn az-btn-primary" style="padding: 12px 30px; font-size: 16px;">
            Quay về trang chủ
        </a>
    </div>
</div>

<?php
get_footer();
?>
