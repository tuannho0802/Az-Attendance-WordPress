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

        // Avatar Upload Support
        add_action('user_edit_form_tag', [__CLASS__, 'add_enctype_to_user_profile']);
        add_filter('get_avatar', [__CLASS__, 'custom_avatar_filter'], 10, 6);
        add_filter('get_avatar_url', [__CLASS__, 'custom_avatar_url_filter'], 10, 3);
    }
    
    /**
     * Add enctype to user profile form to support file upload
     */
    public static function add_enctype_to_user_profile()
    {
        echo ' enctype="multipart/form-data"';
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
        $avatar_id = get_user_meta($user->ID, 'az_custom_avatar', true);
        $avatar_url = $avatar_id ? wp_get_attachment_url($avatar_id) : '';
        ?>
        <h3>Thông tin bổ sung Az Academy</h3>
        <table class="form-table">
            <tr>
                <th><label for="az_custom_avatar_upload">Ảnh đại diện</label></th>
                <td>
                    <?php if ($avatar_url): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar"
                                style="max-width: 150px; height: auto; border-radius: 50%; border: 2px solid #ddd; padding: 2px;" />
                        </div>
                    <?php endif; ?>
                    <input type="file" name="az_custom_avatar_upload" id="az_custom_avatar_upload"
                        accept="image/jpeg,image/png,image/gif" />
                    <p class="description">Định dạng cho phép: .jpg, .png, .gif. Dung lượng tối đa: 2MB.</p>
                </td>
                </tr>
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

        // Save Text Fields
        if (isset($_POST['az_phone'])) {
            update_user_meta($user_id, 'az_phone', sanitize_text_field($_POST['az_phone']));
        }
        
        if (isset($_POST['az_business_field'])) {
            update_user_meta($user_id, 'az_business_field', sanitize_text_field($_POST['az_business_field']));
        }

        // Handle Avatar Upload
        if (isset($_FILES['az_custom_avatar_upload']) && !empty($_FILES['az_custom_avatar_upload']['name'])) {
            $file = $_FILES['az_custom_avatar_upload'];

            // Validate File Size (2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                // Add error notice if possible, or just skip
                return;
            }

            // Validate File Type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                return;
            }

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            // Delete Old Avatar
            $old_avatar_id = get_user_meta($user_id, 'az_custom_avatar', true);
            if ($old_avatar_id) {
                wp_delete_attachment($old_avatar_id, true);
            }

            // Rename File before Upload
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $first_name = !empty($_POST['first_name']) ? $_POST['first_name'] : $user_info->first_name;
                $last_name = !empty($_POST['last_name']) ? $_POST['last_name'] : $user_info->last_name;
                if (empty($first_name))
                    $first_name = $user_info->user_login;

                $roles = $user_info->roles;
                $role = !empty($roles) ? ucfirst($roles[0]) : 'User';

                // Helper to clean string
                $clean_str = function ($str) {
                    $str = remove_accents($str);
                    $str = preg_replace('/[^a-zA-Z0-9]/', '', $str);
                    return ucfirst(strtolower($str));
                };

                $f_name_clean = $clean_str($first_name);
                $l_name_clean = $clean_str($last_name);
                $role_clean = $clean_str($role);

                $new_base_name = $f_name_clean;
                if ($l_name_clean) {
                    $new_base_name .= '_' . $l_name_clean;
                }
                $new_base_name .= '_' . $role_clean;

                $path_parts = pathinfo($_FILES['az_custom_avatar_upload']['name']);
                $ext = isset($path_parts['extension']) ? $path_parts['extension'] : '';

                if ($new_base_name && $ext) {
                    $_FILES['az_custom_avatar_upload']['name'] = $new_base_name . '.' . $ext;
                }
            }

            // Upload New Avatar
            $attachment_id = media_handle_upload('az_custom_avatar_upload', 0); // 0 = not attached to a post

            if (!is_wp_error($attachment_id)) {
                update_user_meta($user_id, 'az_custom_avatar', $attachment_id);
            }
        }
    }

    /**
     * Filter get_avatar_url to use custom image
     */
    public static function custom_avatar_url_filter($url, $id_or_email, $args)
    {
        $user_id = 0;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user)
                $user_id = $user->ID;
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif ($id_or_email instanceof WP_User) {
            $user_id = $id_or_email->ID;
        }

        if ($user_id > 0) {
            $custom_avatar_id = get_user_meta($user_id, 'az_custom_avatar', true);
            if ($custom_avatar_id) {
                $custom_url = wp_get_attachment_image_url($custom_avatar_id, 'thumbnail');
                if (!$custom_url) {
                    $custom_url = wp_get_attachment_url($custom_avatar_id);
                }
                if ($custom_url) {
                    return $custom_url;
                }
            }
        }

        return $url;
    }

    /**
     * Filter get_avatar to use custom image
     */
    public static function custom_avatar_filter($avatar, $id_or_email, $size, $default, $alt, $args = null)
    {
        $user_id = 0;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user)
                $user_id = $user->ID;
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif ($id_or_email instanceof WP_User) {
            $user_id = $id_or_email->ID;
        }

        if ($user_id > 0) {
            $custom_avatar_id = get_user_meta($user_id, 'az_custom_avatar', true);
            if ($custom_avatar_id) {
                $custom_avatar_url = wp_get_attachment_image_url($custom_avatar_id, 'thumbnail'); // Use thumbnail size for efficiency
                if (!$custom_avatar_url) {
                    $custom_avatar_url = wp_get_attachment_url($custom_avatar_id); // Fallback to full url
                }

                if ($custom_avatar_url) {
                    $class = array('avatar', 'avatar-' . (int) $size, 'photo');
                    if (!empty($args['class'])) {
                        if (is_array($args['class'])) {
                            $class = array_merge($class, $args['class']);
                        } else {
                            $class[] = $args['class'];
                        }
                    }

                    $avatar = sprintf(
                        '<img alt="%s" src="%s" class="%s" height="%d" width="%d" decoding="async" />',
                        esc_attr($alt),
                        esc_url($custom_avatar_url),
                        esc_attr(implode(' ', $class)),
                        (int) $size,
                        (int) $size
                    );
                }
            }
        }

        return $avatar;
    }
}
