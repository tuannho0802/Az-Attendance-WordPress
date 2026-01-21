<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Shortcodes
{
    public static function register()
    {
        add_shortcode('az_curriculum', [__CLASS__, 'render_curriculum']);
        add_action('add_meta_boxes', [__CLASS__, 'add_shortcode_hint_meta_box']);
    }

    public static function render_curriculum($atts)
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'az_curriculum');

        $class_id = absint($atts['id']);
        if (!$class_id) {
            return '';
        }

        // Check if class exists
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'az_class') {
            return '';
        }

        // Retrieve Metadata
        $sessions_count = get_post_meta($class_id, 'az_tong_so_buoi', true);
        $teacher_name = get_post_meta($class_id, 'az_giang_vien', true);

        // Retrieve Class Description
        $class_description = apply_filters('the_content', $post->post_content);

        // Fetch sessions using existing Core function
        if (!class_exists('AzAC_Core_Sessions')) {
            return '';
        }
        $sessions = AzAC_Core_Sessions::get_class_sessions($class_id);

        ob_start();
        ?>
                <div class="az-landing-curriculum">
                    <!-- Class Header & Meta -->
                    <div class="az-curriculum-header">
                        <h2 class="az-curriculum-title"><?php echo esc_html($post->post_title); ?></h2>
                <div class="az-curriculum-meta">
                    <?php if ($sessions_count): ?>
                        <span class="az-meta-item">
                            <i class="dashicons dashicons-calendar-alt"></i>
                            Tổng số buổi: <strong><?php echo esc_html($sessions_count); ?></strong>
                        </span>
                    <?php endif; ?>
        
                    <?php if ($teacher_name): ?>
                        <span class="az-meta-item">
                            <span class="az-meta-separator">|</span>
                            <i class="dashicons dashicons-businessman"></i>
                            Giảng viên: <strong><?php echo esc_html($teacher_name); ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        
            <!-- Class Overview Block -->
            <?php if (!empty($class_description)): ?>
                <div class="az-curriculum-overview">
                    <h3 class="az-overview-title">Giới thiệu khóa học</h3>
                    <div class="az-overview-content">
                        <?php echo $class_description; ?>
                    </div>
                </div>
            <?php endif; ?>
        
            <!-- Sessions Accordion -->
            <?php if (!empty($sessions)): ?>
                            <div class="az-curriculum-accordion">
                    <?php foreach ($sessions as $index => $session): ?>
                    <div class="az-accordion-item">
                        <div class="az-accordion-header" onclick="this.parentElement.classList.toggle('active')">
                                <div class="az-accordion-title-wrapper">
                                    <span class="az-session-index">Buổi <?php echo esc_html($index + 1); ?>:</span>
                                    <span
                                        class="az-session-name"><?php echo esc_html($session['title'] ? $session['title'] : 'Buổi học ' . ($index + 1)); ?></span>
                                </div>
                                <span class="az-accordion-icon"></span>
                            </div>
                            <div class="az-accordion-content">
                                <div class="az-accordion-inner">
                                    <?php echo wp_kses_post($session['content']); ?>
                                    </div>
                                    </div>
                                    </div>
                                    <?php endforeach; ?>
                            </div>
                <?php else: ?>
                <p>Chưa có nội dung buổi học.</p>
                <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function add_shortcode_hint_meta_box()
    {
        add_meta_box(
            'az_curriculum_hint',
            'Mã nhúng Landing Page',
            [__CLASS__, 'render_hint_meta_box'],
            'az_class',
            'side',
            'high'
        );
    }

    public static function render_hint_meta_box($post)
    {
        $shortcode = '[az_curriculum id="' . $post->ID . '"]';
        ?>
        <p>Copy mã dưới đây vào Landing Page:</p>
        <input type="text" value="<?php echo esc_attr($shortcode); ?>" class="widefat" readonly onclick="this.select()">
        <?php
    }
}
