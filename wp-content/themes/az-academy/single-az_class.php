<?php
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<div class="container">
    <main>
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>
                <?php
                $post_id = get_the_ID();
                $teacher = get_post_meta($post_id, 'az_giang_vien', true);
                if (!$teacher) {
                    $teacher_user_id = intval(get_post_meta($post_id, 'az_teacher_user', true));
                    if ($teacher_user_id) {
                        $u = get_userdata($teacher_user_id);
                        if ($u) {
                            $teacher = $u->display_name ?: $u->user_login;
                        }
                    }
                }
                $sessions_total = intval(get_post_meta($post_id, 'az_tong_so_buoi', true));
                $size = intval(get_post_meta($post_id, 'az_so_hoc_vien', true));
                $current_sessions = 0;
                if (class_exists('AzAC_Core_Sessions')) {
                    $list = AzAC_Core_Sessions::get_class_sessions($post_id);
                    $current_sessions = is_array($list) ? count($list) : 0;
                }
                $progress_percent = ($sessions_total > 0) ? min(100, max(0, round($current_sessions * 100 / $sessions_total))) : 0;
                ?>
                                <div class="az-class-card">
                                    <table class="az-class-table">
                                        <tr>
                                            <th>Giảng viên</th>
                                            <td><?php echo esc_html($teacher ?: 'Chưa gán'); ?></td>
                                </tr>
                                <tr>
                                    <th>Tổng số buổi</th>
                                    <td><?php echo esc_html($sessions_total); ?></td>
                                </tr>
                                <tr>
                                    <th>Số buổi đã tạo</th>
                                    <td><?php echo esc_html($current_sessions); ?></td>
                                </tr>
                                <tr>
                                    <th>Sĩ số</th>
                                    <td><?php echo esc_html($size); ?></td>
                                </tr>
                                <tr>
                                    <th>Tiến độ</th>
                                    <td>
                                        <div class="az-progress">
                                            <div class="az-progress-fill" style="width: <?php echo esc_attr($progress_percent); ?>%"></div>
                                        </div>
                                        <div class="az-progress-text"><?php echo esc_html($progress_percent); ?>%
                                            (<?php echo esc_html($current_sessions); ?>/<?php echo esc_html(max(0, $sessions_total)); ?> buổi)
                                        </div>
                                    </td>
                                </tr>
                            </table>
                </div>
                <div class="az-content"><?php the_content(); ?></div>
            </article>
        <?php endwhile; else: ?>
            <p>Không có nội dung.</p>
        <?php endif; ?>
    </main>
    <?php if (current_user_can('manage_options')): ?>
    <div class="az-class-actions" style="margin-top:16px">
        <a class="button" href="<?php echo esc_url(admin_url('post.php?post='.get_the_ID().'&action=edit')); ?>">Chỉnh sửa lớp</a>
    </div>
    <?php endif; ?>
    </div>
<?php
get_footer();
?>
