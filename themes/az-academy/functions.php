<?php
if (!defined('ABSPATH')) { exit; }
add_theme_support('title-tag');
add_theme_support('post-thumbnails');
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('az-theme', get_stylesheet_uri(), [], '0.1.0');
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
    echo '<script>(function(){var p=document.getElementById("user_pass");if(!p)return;var wrap=p.parentNode;if(wrap)wrap.style.position="relative";var b=document.createElement("button");b.type="button";b.className="azac-pass-toggle";b.setAttribute("aria-label","Show/Hide password");b.innerHTML="&#128065;";var show=false;b.addEventListener("click",function(){show=!show;p.setAttribute("type",show?"text":"password");b.classList.toggle("on",show);});wrap.appendChild(b);})();</script>';
}
add_action('login_footer', 'azac_login_footer_script');
