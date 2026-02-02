<?php
/**
 * Template Name: Welcome Dashboard
 * Description: Dashboard chào mừng tận dụng CSS class cũ của Az Academy
 */

if (!defined('ABSPATH')) {
    exit;
}
get_header();

// --- PREPARE DATA ---
$current_user = wp_get_current_user();
$display_name = $current_user->display_name ?: $current_user->user_login;
$user_roles = (array) $current_user->roles;

$student_cpt_id = 0;
if (in_array('az_student', $user_roles)) {
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

<section class="az-hero az-dashboard-hero" style="padding: 40px 0; margin-bottom: 20px;">
    <div class="container">
        <div class="az-hero-content" style="text-align: left; max-width: 100%; display: flex; align-items: center; gap: 30px;">
            <div class="az-avatar-wrapper">
                <?php echo get_avatar($current_user->ID, 100, '', '', array('class' => 'az-avatar-img')); ?>
            </div>
            <div class="az-text-wrapper">
                <h1 class="az-hero-title" style="font-size: 2.2rem; margin-bottom: 5px;">Chào mừng,
                    <?php echo esc_html($display_name); ?>! 👋</h1>
                <p class="az-hero-subtitle" style="font-size: 1.1rem; opacity: 0.9;">Hệ thống học tập Az Academy - Chúc bạn một
                    ngày học tập hiệu quả.</p>
            </div>
        </div>
    </div>
</section>

<div class="az-dashboard-main">
    <div class="container">
        <section class="az-section">
            <div class="az-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h2 class="az-section-title">Lớp học của tôi</h2>
                    <p class="az-section-desc">Truy cập nhanh các lớp học bạn đang tham gia</p>
                </div>
                <div class="az-dashboard-actions">
                    <?php do_action('azac_home_buttons'); ?>
                </div>
            </div>

            <?php
            $args = [
                'post_type' => 'az_class',
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ];
            $query = new WP_Query($args);
            $found_classes = 0;
            ?>

            <div class="az-grid az-classes-grid">
                <?php if ($query->have_posts()): ?>
                    <?php while ($query->have_posts()):
                        $query->the_post();
                        $class_id = get_the_ID();
                        $has_access = false;

                        // --- ORIGINAL ACCESS LOGIC ---
                        if (current_user_can('administrator') || current_user_can('manage_options')) {
                            $has_access = true;
                        } elseif (in_array('az_teacher', $user_roles)) {
                            $tid = get_post_meta($class_id, 'az_teacher_user', true);
                            if ($tid == $current_user->ID)
                                $has_access = true;
                        } elseif ($student_cpt_id > 0) {
                            $class_students = get_post_meta($class_id, 'az_students', true);
                            if (is_array($class_students) && in_array($student_cpt_id, $class_students)) {
                                $has_access = true;
                            }
                        }

                        if ($has_access):
                            $found_classes++;
                            $teacher_name = get_post_meta($class_id, 'az_giang_vien', true);
                    ?>
                        <div class="az-card az-class-card">
                            <div class="az-card-body">
                                <h3 class="az-card-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <div class="az-card-meta">
                                        <span class="az-meta-item">
                                            <span class="dashicons dashicons-businessperson"></span>
                                            Giảng viên: <strong><?php echo esc_html($teacher_name ?: 'Đang cập nhật'); ?></strong>
                                        </span>
                                    </div>
                                    <div class="az-card-excerpt">
                                    <?php echo has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 12, '...'); ?>
                                    </div>
                                    </div>
                                    <div class="az-card-footer">
                                <a href="<?php the_permalink(); ?>" class="az-btn az-btn-primary az-btn-block">
                                    Vào học ngay
                                </a>
                            </div>
                        </div>
                    <?php
                        endif;
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </div>
<?php if ($found_classes === 0): ?>
    <div class="az-empty-state" style="text-align: center; padding: 80px 20px;">
        <div class="az-feature-item" style="max-width: 450px; margin: 0 auto;">
            <span class="dashicons dashicons-calendar-alt"
                style="font-size: 50px; height: 50px; width: 50px; color: #ddd; margin-bottom: 20px;"></span>
            <h3>Chưa tìm thấy lớp học</h3>
            <p>Tài khoản của bạn hiện chưa được chỉ định vào lớp học nào trong hệ thống. Vui lòng liên hệ Admin để được hỗ
                trợ.</p>
        </div>
    </div>
<?php endif; ?>
        </section>
    </div>
</div>

<style>
    /* CSS bổ trợ để làm Avatar tròn và Hero đẹp hơn */
    .az-avatar-img { border-radius: 50%; border: 3px solid rgba(255,255,255,0.3); }
    .az-dashboard-hero { background: linear-gradient(135deg, #1e3799 0%, #0984e3 100%) !important; color: #fff !important; }
    .az-dashboard-hero .az-hero-title, .az-dashboard-hero .az-hero-subtitle { color: #fff !important; }
    .az-class-card { transition: all 0.3s ease; }
    .az-class-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
</style>

<?php get_footer(); ?>