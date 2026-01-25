<?php
if (!defined('ABSPATH')) {
    exit;
}
add_theme_support('title-tag');
add_theme_support('post-thumbnails');
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('az-theme', get_stylesheet_uri(), [], time()); // Force cache clear
});
register_nav_menus([
    'primary' => 'Primary Menu'
]);

function azac_login_enqueue_styles()
{
    wp_enqueue_style('azac-custom-login', get_template_directory_uri() . '/assets/css/custom-login.css', [], '0.1.0');
}
add_action('login_enqueue_scripts', 'azac_login_enqueue_styles');

function azac_login_logo_url()
{
    return home_url('/');
}
add_filter('login_headerurl', 'azac_login_logo_url');

function azac_login_logo_title()
{
    return 'Chào mừng bạn đến với Az Academy';
}
add_filter('login_headertext', 'azac_login_logo_title');

function azac_login_error_msg()
{
    return 'Thông tin đăng nhập không chính xác, vui lòng kiểm tra lại.';
}
add_filter('login_errors', 'azac_login_error_msg');

function azac_login_footer_script()
{
    echo '<script>(function(){var p=document.getElementById("user_pass");if(!p)return;var wrap=(p.closest&&p.closest(".wp-pwd"))||p.parentNode;if(wrap){try{wrap.style.position="relative";}catch(e){}}if(wrap&& !wrap.querySelector(".azac-pass-toggle")){var b=document.createElement("button");b.type="button";b.className="azac-pass-toggle";b.setAttribute("aria-label","Show/Hide password");b.innerHTML="&#128065;";var show=false;b.addEventListener("click",function(){show=!show;p.setAttribute("type",show?"text":"password");b.classList.toggle("on",show);});wrap.appendChild(b);}})();</script>';
    echo '<script>(function(){var p=document.getElementById("user_pass");if(!p)return;var wrap=p.parentNode;if(wrap)wrap.style.position="relative";var b=document.createElement("button");b.type="button";b.className="azac-pass-toggle";b.setAttribute("aria-label","Show/Hide password");b.innerHTML="&#128065;";var show=false;b.addEventListener("click",function(){show=!show;p.setAttribute("type",show?"text":"password");b.classList.toggle("on",show);});wrap.appendChild(b);})();</script>';
}

function azac_theme_favicon()
{
    $url = get_template_directory_uri() . '/assets/img/favicon.png';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($url) . '"/>';
    echo '<link rel="shortcut icon" type="image/png" href="' . esc_url($url) . '"/>';
}
add_action('wp_head', 'azac_theme_favicon', 99);
add_action('admin_head', 'azac_theme_favicon', 99);
add_action('login_head', 'azac_theme_favicon', 99);

/**
 * 1. Custom URL Rewrite Rules for Auth Pages
 */
function azac_custom_rewrite_rules()
{
    add_rewrite_rule('^login/?$', 'index.php?az_auth_page=login', 'top');
    add_rewrite_rule('^register/?$', 'index.php?az_auth_page=register', 'top');
}
add_action('init', 'azac_custom_rewrite_rules');

function azac_query_vars($vars)
{
    $vars[] = 'az_auth_page';
    return $vars;
}
add_filter('query_vars', 'azac_query_vars');

/**
 * 2. Template Loader
 */
function azac_template_loader($template)
{
    $auth_page = get_query_var('az_auth_page');
    if ($auth_page == 'login') {
        $new_template = get_template_directory() . '/page-login.php';
        if (file_exists($new_template))
            return $new_template;
    }
    if ($auth_page == 'register') {
        $new_template = get_template_directory() . '/page-register.php';
        if (file_exists($new_template))
            return $new_template;
    }
    return $template;
}
add_filter('template_include', 'azac_template_loader');

/**
 * 3. Redirect wp-login.php & Change URLs
 */
function azac_redirect_wp_login()
{
    global $pagenow;
    if ($pagenow == 'wp-login.php' && $_SERVER['REQUEST_METHOD'] == 'GET') {
        if (isset($_GET['action']) && $_GET['action'] == 'logout')
            return;
        wp_redirect(home_url('/login'));
        exit;
    }
}
add_action('init', 'azac_redirect_wp_login');

add_filter('login_url', function ($url) {
    return home_url('/login');
});
add_filter('register_url', function ($url) {
    return home_url('/register');
});

/**
 * 4. Flush Rules (Run once then comment out)
 */
function azac_flush_rules_once()
{
    if (!get_option('azac_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('azac_rewrite_flushed', true);
    }
}
add_action('init', 'azac_flush_rules_once');

/**
 * 5. Custom Admin Footer (Branding)
 */
function azac_custom_admin_footer_text()
{
    $logo_url = get_template_directory_uri() . '/assets/img/logo.png';
    return '<span class="azac-footer-credit" style="display: flex; align-items: center; gap: 8px;">
        <img src="' . esc_url($logo_url) . '" alt="Az Academy" style="height: 16px; width: auto;" />
        <span>Bản quyền thuộc về <strong style="color:#15345a;">Az Academy</strong></span>
    </span>';
}
add_filter('admin_footer_text', 'azac_custom_admin_footer_text', 20);

// Xóa số phiên bản WordPress
add_filter('update_footer', '__return_empty_string', 20);
