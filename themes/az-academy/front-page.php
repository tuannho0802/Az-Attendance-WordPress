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
            <h1 class="az-hero-title">Nền tảng Quản lý Lớp học & Điểm danh Chuyên nghiệp</h1>
            <p class="az-hero-subtitle">Giải pháp toàn diện dành cho Giảng viên và Học viên tại Az Academy. Theo dõi
                tiến độ, điểm danh nhanh chóng và hiệu quả.</p>
            <div class="az-hero-actions">
                <a href="#az-latest-classes" class="az-btn az-btn-primary az-btn-lg">Khám phá Lớp học</a>
                <?php if (!is_user_logged_in()): ?>
                    <a href="<?php echo esc_url(home_url('/register')); ?>" class="az-btn az-btn-outline az-btn-lg">Đăng ký
                        ngay</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Latest Classes Section -->
<section id="az-latest-classes" class="az-section">
    <div class="container">
        <div class="az-section-header">
            <h2 class="az-section-title">Lớp học Mới nhất</h2>
            <p class="az-section-desc">Cập nhật các lớp học đang và sắp diễn ra tại Az Academy</p>
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

<!-- Features/Benefits (Optional placeholder for layout balance) -->
<section class="az-section az-bg-light">
    <div class="container">
        <div class="az-grid az-features-grid">
            <div class="az-feature-item">
                <h3>Điểm danh Online</h3>
                <p>Check-in nhanh chóng qua QR Code hoặc hệ thống quản lý.</p>
            </div>
            <div class="az-feature-item">
                <h3>Thống kê Chi tiết</h3>
                <p>Theo dõi tỷ lệ chuyên cần và tiến độ học tập trực quan.</p>
            </div>
            <div class="az-feature-item">
                <h3>Kết nối Dễ dàng</h3>
                <p>Tương tác giữa Giảng viên và Học viên thuận tiện hơn.</p>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
?>