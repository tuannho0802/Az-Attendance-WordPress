<?php
/**
 * Template Name: Front Page
 * Description: Trang chủ Landing Page cho Az Attendance
 */

if (!defined('ABSPATH')) {
    exit;
}
get_header();

// --- PREPARE STUDENT DATA ONCE ---
$current_user = wp_get_current_user();
$student_cpt_id = 0;
if (in_array('az_student', (array) $current_user->roles)) {
    $student_cpts = get_posts([
        'post_type' => 'az_student',
        'meta_key' => 'az_user_id',
        'meta_value' => $current_user->ID,
        'posts_per_page' => 1,
        'fields' => 'ids'
    ]);
    if (!empty($student_cpts)) {
        $student_cpt_id = $student_cpts[0];
    }
}
?>

<!-- Hero Section -->
<section class="az-hero">
    <div class="container">
        <div class="az-hero-content">
            <h1 class="az-hero-title">Trung Tâm Đào Tạo Digital Marketing Thực Chiến</h1>
            <p class="az-hero-subtitle">Học Để Làm Được - Áp dụng ngay vào thực tế công việc kinh doanh. Case study thực
                tế, thực hành trên tài khoản thật.</p>
            <div class="az-hero-actions">
                <?php do_action('azac_home_buttons'); ?>
                <?php if (!has_action('azac_home_buttons')) : // Fallback if plugin is disabled ?>
                    <a href="#az-latest-classes" class="az-btn az-btn-primary az-btn-lg">Xem Khóa Học</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Latest Classes Section -->
<section id="az-latest-classes" class="az-section">
    <div class="container">
        <div class="az-section-header">
            <h2 class="az-section-title">Các Khóa Học Digital Marketing</h2>
            <p class="az-section-desc">Cập nhật lịch khai giảng Facebook Ads, Google Ads, TikTok Ads mới nhất</p>
        </div>

        <?php
        $latest_classes = new WP_Query([
            'post_type' => 'az_class',
            'posts_per_page' => 6,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        ]);
        ?>

        <?php if ($latest_classes->have_posts()): ?>
            <div class="az-grid az-classes-grid">
                <?php while ($latest_classes->have_posts()):
                    $latest_classes->the_post(); ?>
                    <?php
                    $class_id = get_the_ID();
                    $teacher_name = get_post_meta($class_id, 'az_giang_vien', true);
                    $total_sessions = get_post_meta($class_id, 'az_tong_so_buoi', true);

                    // --- CHECK ACCESS ---
                    $has_access = false;
                    if (current_user_can('administrator') || current_user_can('manage_options')) {
                        $has_access = true;
                    } elseif (in_array('az_teacher', (array) $current_user->roles)) {
                        $tid = get_post_meta($class_id, 'az_teacher_user', true);
                        if ($tid == $current_user->ID)
                            $has_access = true;
                    } elseif ($student_cpt_id > 0) {
                        $class_students = get_post_meta($class_id, 'az_students', true);
                        if (is_array($class_students) && in_array($student_cpt_id, $class_students)) {
                            $has_access = true;
                        }
                    }
                    ?>
                    <div class="az-card az-class-card">
                        <div class="az-card-body">
                            <h3 class="az-card-title">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                    <?php if (!$has_access): ?>
                                        <span class="dashicons dashicons-lock"
                                            style="font-size: 18px; color: #999; vertical-align: middle;"
                                            title="Bạn chưa có quyền truy cập"></span>
                                    <?php endif; ?>
                                </a>
                            </h3>
                            <div class="az-card-meta">
                                <span class="az-meta-item">
                                    <span class="dashicons dashicons-businessperson"></span>
                                    Giảng viên: <strong><?php echo esc_html($teacher_name ?: 'Đang cập nhật'); ?></strong>
                                </span>
                            </div>
                            <div class="az-card-excerpt">
                                <?php
                                if (has_excerpt()) {
                                    echo get_the_excerpt();
                                } else {
                                    echo wp_trim_words(get_the_content(), 15, '...');
                                }
                                ?>
                            </div>
                        </div>
                        <div class="az-card-footer">
                            <a href="<?php the_permalink(); ?>"
                                class="az-btn <?php echo $has_access ? 'az-btn-primary' : 'az-btn-outline'; ?> az-btn-block">
                                <?php echo $has_access ? 'Vào lớp học' : 'Xem chi tiết'; ?>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>
        <?php else: ?>
            <div class="az-empty-state">
                <p>Hiện chưa có lớp học nào được mở.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features/Benefits -->
<section class="az-section az-bg-light">
    <div class="container">
        <div class="az-grid az-features-grid">
            <div class="az-feature-item">
                <span class="dashicons dashicons-chart-area"
                    style="font-size: 40px; width: 40px; height: 40px; color: var(--az-primary); margin-bottom: 15px;"></span>
                <h3>Case study thực tế</h3>
                <p>Học trên các dự án thực tế, phân tích số liệu thật để tối ưu hiệu quả kinh doanh.</p>
            </div>
            <div class="az-feature-item">
                <span class="dashicons dashicons-laptop"
                    style="font-size: 40px; width: 40px; height: 40px; color: var(--az-primary); margin-bottom: 15px;"></span>
                <h3>Thực hành tài khoản thật</h3>
                <p>Thực hành trực tiếp trên tài khoản quảng cáo, không dạy lý thuyết suông.</p>
            </div>
            <div class="az-feature-item">
                <span class="dashicons dashicons-update"
                    style="font-size: 40px; width: 40px; height: 40px; color: var(--az-primary); margin-bottom: 15px;"></span>
                <h3>Cập nhật liên tục</h3>
                <p>Nội dung giáo trình được cập nhật liên tục theo thay đổi của nền tảng.</p>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
?>