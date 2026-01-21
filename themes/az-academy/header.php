<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header style="background:#15345a;color:#fff">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between">
        <div><a href="<?php echo esc_url(home_url('/')); ?>" style="color:#fff;text-decoration:none">Az Academy</a></div>
        <?php if (has_nav_menu('primary')) {
            wp_nav_menu(['theme_location'=>'primary','container'=>'nav','menu_class'=>'menu','fallback_cb'=>false]);
        } ?>
    </div>
</header>
