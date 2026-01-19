<?php
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<div class="container">
    <main>
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>
                <div class="az-meta">
                    <?php
                    $teacher = get_post_meta(get_the_ID(), 'az_giang_vien', true);
                    $sessions = get_post_meta(get_the_ID(), 'az_tong_so_buoi', true);
                    $size = get_post_meta(get_the_ID(), 'az_so_hoc_vien', true);
                    ?>
                    <p>Giảng viên: <?php echo esc_html($teacher ?: 'Chưa gán'); ?></p>
                    <p>Tổng số buổi: <?php echo esc_html($sessions ?: 0); ?></p>
                    <p>Sĩ số: <?php echo esc_html($size ?: 0); ?></p>
                </div>
                <div class="az-content"><?php the_content(); ?></div>
            </article>
        <?php endwhile; else: ?>
            <p>Không có nội dung.</p>
        <?php endif; ?>
    </main>
    <aside>
        <?php dynamic_sidebar('primary'); ?>
    </aside>
    <?php if (current_user_can('manage_options')): ?>
    <div style="margin-top:24px">
        <a class="button" href="<?php echo esc_url(admin_url('post.php?post='.get_the_ID().'&action=edit')); ?>">Chỉnh sửa lớp</a>
    </div>
    <?php endif; ?>
<?php
get_footer();
?>
