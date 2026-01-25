<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Column 1: Brand & Mission -->
            <div class="footer-col">
                <div class="footer-brand">
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="Az Academy"
                        class="footer-logo">
                </div>
                <p class="footer-desc">AZ Academy – Trung Tâm Đào Tạo Digital Marketing "Học Để Làm Được". Chúng tôi cam
                    kết mang lại kiến thức thực chiến và cập nhật mới nhất.</p>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="footer-col">
                <h3 class="footer-title">Liên kết nhanh</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo esc_url(home_url('/classes')); ?>">Danh sách Lớp học</a></li>
                    <li><a href="<?php echo esc_url(home_url('/teachers')); ?>">Đội ngũ Giảng viên</a></li>
                    <li><a href="<?php echo esc_url(home_url('/guide')); ?>">Hướng dẫn sử dụng</a></li>
                    <li><a href="<?php echo esc_url(home_url('/privacy')); ?>">Chính sách bảo mật</a></li>
                </ul>
            </div>

            <!-- Column 3: Contact Info -->
            <div class="footer-col">
                <h3 class="footer-title">Thông tin liên hệ</h3>
                <ul class="footer-contact">
                    <li><strong>Email:</strong> daotao@azacademy.vn</li>
                    <li><strong>Hotline:</strong> 0382.052.711</li>
                    <li><strong>Địa chỉ:</strong> Tầng 8 , Phú Mỹ Tower.</li>
                    <li><strong></strong> 27 Đinh Bộ Lĩnh, Phường Bình Thạnh, TP.HCM.</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> Az Academy. All rights reserved.</p>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>