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

        // Fetch sessions using existing Core function
        if (!class_exists('AzAC_Core_Sessions')) {
            return '';
        }
        $sessions = AzAC_Core_Sessions::get_class_sessions($class_id);

        if (empty($sessions)) {
            return '<p>Chưa có nội dung buổi học.</p>';
        }

        ob_start();
        ?>
        <div class="az-curriculum-accordion">
            <?php foreach ($sessions as $index => $session): ?>
                <div class="az-accordion-item">
                    <div class="az-accordion-header" onclick="this.parentElement.classList.toggle('active')">
                        <div class="az-accordion-title">
                            <span class="az-accordion-icon">+</span>
                            <span class="az-session-index">Buổi <?php echo esc_html($index + 1); ?>:</span>
                            <span class="az-session-name"><?php echo esc_html($session['title'] ? $session['title'] : 'Buổi học ' . ($index + 1)); ?></span>
                        </div>
                    </div>
                    <div class="az-accordion-content">
                        <div class="az-accordion-inner">
                            <?php 
                            // Only show text content, strip tags if needed, but keeping basic formatting is usually good for descriptions.
                            // User requested "Summary" or "Short description". Since content might be long, we show it all in accordion.
                            // Ensure no attachments are shown (get_class_sessions returns 'attachments' but we just ignore it).
                            echo wp_kses_post($session['content']); 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
