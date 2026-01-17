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

