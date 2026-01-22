<?php

class AzAC_User_Manager
{

    public static function register()
    {
        // User Meta Fields in Admin
        add_filter('manage_users_columns', [__CLASS__, 'add_user_columns']);
        add_filter('manage_users_custom_column', [__CLASS__, 'show_user_column_content'], 10, 3);
        add_action('show_user_profile', [__CLASS__, 'add_custom_user_profile_fields']);
        add_action('edit_user_profile', [__CLASS__, 'add_custom_user_profile_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_custom_user_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_custom_user_profile_fields']);
    }

    /**
     * Add Custom Columns to Admin User List
     */
    public static function add_user_columns($columns)
    {
        $columns['az_phone'] = 'Số điện thoại';
        $columns['az_business_field'] = 'Lĩnh vực';
        return $columns;
    }

    public static function show_user_column_content($value, $column_name, $user_id)
    {
        if ($column_name == 'az_phone') {
            return esc_html(get_user_meta($user_id, 'az_phone', true));
        }
        if ($column_name == 'az_business_field') {
            return esc_html(get_user_meta($user_id, 'az_business_field', true));
        }
        return $value;
    }

    /**
     * Add Custom Fields to User Profile
     */
    public static function add_custom_user_profile_fields($user)
    {
        ?>
        <h3>Thông tin bổ sung Az Academy</h3>
        <table class="form-table">
            <tr>
                <th><label for="az_phone">Số điện thoại</label></th>
                <td>
                    <input type="text" name="az_phone" id="az_phone"
                        value="<?php echo esc_attr(stripslashes(get_user_meta($user->ID, 'az_phone', true))); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="az_business_field">Lĩnh vực kinh doanh</label></th>
                <td>
                    <input type="text" name="az_business_field" id="az_business_field"
                        value="<?php echo esc_attr(stripslashes(get_user_meta($user->ID, 'az_business_field', true))); ?>"
                        class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_custom_user_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['az_phone'])) {
            update_user_meta($user_id, 'az_phone', sanitize_text_field($_POST['az_phone']));
        }

        if (isset($_POST['az_business_field'])) {
            update_user_meta($user_id, 'az_business_field', sanitize_text_field($_POST['az_business_field']));
        }
    }
}
