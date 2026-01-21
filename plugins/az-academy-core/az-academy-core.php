<?php
/*
Plugin Name: Az Academy Core
Description: Hệ thống điểm danh Az Academy tích hợp WordPress Admin.
Version: 0.1.0
Author: Az Academy
*/

if (!defined('ABSPATH')) {
    exit;
}

define('AZAC_CORE_VERSION', '0.1.3');
define('AZAC_CORE_DIR', plugin_dir_path(__FILE__));
define('AZAC_CORE_URL', plugin_dir_url(__FILE__));

require_once AZAC_CORE_DIR . 'includes/class-azac-core-activator.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-deactivator.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-helper.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-cpt.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-rbac.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-classes.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-attendance.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-sessions.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-mid.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-admin-pages.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-admin-assets.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-admin-stats.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-core-admin.php';
require_once AZAC_CORE_DIR . 'includes/class-azac-shortcodes.php';

register_activation_hook(__FILE__, ['AzAC_Core_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['AzAC_Core_Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    AzAC_Core_Admin::register();
    AzAC_Core_CPT::register();
    AzAC_Core_RBAC::register();
    AzAC_Core_Classes::register();
    AzAC_Core_Attendance::register();
    AzAC_Core_Sessions::register();
    AzAC_Admin_Stats::register();
    AzAC_Core_Mid::register();
    AzAC_Shortcodes::register();
});
add_action('init', function () {
    $ver = get_option('azac_core_version');
    if ($ver !== AZAC_CORE_VERSION) {
        update_option('azac_core_version', AZAC_CORE_VERSION);
        flush_rewrite_rules();
    }
}, 20);

