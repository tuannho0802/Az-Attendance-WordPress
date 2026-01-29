<?php
if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Loading
{

    public static function init()
    {
        // Admin Hooks
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 9999);
        add_action('admin_head', [__CLASS__, 'render_loader'], 1);

        // Frontend Hooks
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 9999);
        add_action('wp_body_open', [__CLASS__, 'render_loader'], 1);
    }

    public static function enqueue_assets()
    {
        // Enqueue Styles and Scripts
        wp_enqueue_style('azac-loading-style', AZAC_CORE_URL . 'admin/css/azac-loading.css', [], AZAC_CORE_VERSION);
        wp_enqueue_script('azac-loading-js', AZAC_CORE_URL . 'admin/js/azac-loading.js', ['jquery'], AZAC_CORE_VERSION, true);
    }

    public static function render_loader()
    {
        // Scope Control: Ensure it runs on valid pages
        if (!self::should_display_loader()) {
            return;
        }

        // Determine Page Title based on context
        $page_title = '';

        if (is_admin()) {
            global $title;
            $page_title = isset($title) ? $title : get_admin_page_title();
        } else {
            // Frontend: Handle Single Posts, Pages, Archives
            if (is_singular() || is_page()) {
                $page_title = get_the_title();
            } elseif (is_archive()) {
                $page_title = get_the_archive_title();
            } else {
                $page_title = wp_get_document_title();
            }
        }

        if (empty($page_title)) {
            $page_title = 'Az Academy';
        }

        ?>
        <div id="azac-global-loader">
            <div class="azac-loader-content">
                <div class="azac-spinner-wrapper">
                    <div class="azac-spinner-circle"></div>
                    <div class="azac-loader-logo">
                        <img src="<?php echo esc_url(content_url('themes/az-academy/assets/img/logo.png')); ?>"
                            alt="Az Academy Logo" class="azac-logo-img">
                    </div>
                </div>
                <h3 class="azac-loading-text">Đang chuẩn bị dữ liệu cho <?php echo esc_html($page_title); ?>...</h3>
                <p class="azac-loading-subtext">Vui lòng đợi trong giây lát trong khi chúng tôi chuẩn bị dữ liệu.</p>
                <div class="azac-progress-bar">
                    <div class="azac-progress-fill"></div>
                </div>
                <div class="azac-progress-text">
                    <span>Loading...</span>
                    <span class="azac-progress-percent">0%</span>
                </div>
            </div>
        </div>
        <?php
        $flash = function_exists('AzAC_Core_Helper::get_flash_toast') ? AzAC_Core_Helper::get_flash_toast() : null;
        if ($flash) {
            $msg = isset($flash['message']) ? $flash['message'] : '';
            $type = isset($flash['type']) ? $flash['type'] : 'info';
            echo '<script>window.AZAC_FLASH_TOAST={message:' . json_encode($msg) . ',type:' . json_encode($type) . '};(function(){function fire(){if(window.azacToast){azacToast.show(window.AZAC_FLASH_TOAST.message,window.AZAC_FLASH_TOAST.type);window.AZAC_FLASH_TOAST=null;}}if(document.body.classList.contains("azac-loaded")){setTimeout(fire,50);}else{var iv=setInterval(function(){if(document.body.classList.contains("azac-loaded")){clearInterval(iv);setTimeout(fire,50);}},50);}})();</script>';
        }
    }

    /**
     * Check if loader should be displayed
     */
    private static function should_display_loader()
    {
        if (is_admin()) {
            return true;
        }

        // Frontend Logic: Run on everything as requested "ALL pages"
        return true;
    }
}
