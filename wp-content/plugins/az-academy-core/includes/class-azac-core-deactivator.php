<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzAC_Core_Deactivator
{
    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}
