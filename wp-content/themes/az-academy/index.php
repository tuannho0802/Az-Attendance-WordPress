<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="container">
    <main>
        <?php if (is_front_page()): ?>
            <h1>Danh sách lớp học</h1>
            <?php
            $classes = get_posts([
                'post_type' => 'az_class',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            ?>
            <?php if ($classes): ?>
                <div class="az-grid">
                    <?php foreach ($classes as $c): ?>
                        <?php
                        $gv = get_post_meta($c->ID, 'az_giang_vien', true);
                        $tsb = get_post_meta($c->ID, 'az_tong_so_buoi', true);
                        $shv = get_post_meta($c->ID, 'az_so_hoc_vien', true);
                        $link = get_permalink($c->ID);
                        ?>
                        <div class="az-card">
                            <div class="az-card-title"><?php echo esc_html(get_the_title($c)); ?></div>
                            <div>
                                <div>Giảng viên: <?php echo esc_html($gv); ?></div>
                                <div>Tổng số buổi: <?php echo esc_html($tsb); ?></div>
                                <div>Sĩ số: <?php echo esc_html($shv); ?></div>
                            </div>
                            <div class="az-card-actions"><a class="button" href="<?php echo esc_url($link); ?>">Xem lớp</a></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Chưa có lớp học.</p>
            <?php endif; ?>
        <?php else: ?>
            <?php if (have_posts()):
                while (have_posts()):
                    the_post(); ?>
                    <article <?php post_class(); ?>>
                        <h1><?php the_title(); ?></h1>
                        <div><?php the_content(); ?></div>
                    </article>
                <?php endwhile; else: ?>
                <p>Không có nội dung.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<?php
get_footer();
?>