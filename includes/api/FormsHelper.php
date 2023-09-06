<?php

namespace Bricksforge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class FormsHelper
{
    public $utils;

    /**
     * Constructor
     */

    public function __construct()
    {
        $this->utils = new \Bricksforge\Api\Utils();
    }

    public function create_post($form_settings, $form_data, $current_post_id, $dynamic_post_id, $form_files)
    {
        $post_status;
        $post_categories;
        $post_taxonomies;
        $post_title;
        $post_content;
        $custom_fields;
        $post_thumbnail;
        $post_author;

        $post_status = $form_settings['pro_forms_post_action_post_create_post_status'] ? $form_settings['pro_forms_post_action_post_create_post_status'] : 'draft';
        $post_author = isset($form_settings['pro_forms_post_action_post_create_author']) ? $this->get_form_field_by_id($form_settings['pro_forms_post_action_post_create_author'], $form_data) : null;

        $post_categories = $form_settings['pro_forms_post_action_post_create_categories'] ? $form_settings['pro_forms_post_action_post_create_categories'] : [];

        // Loop trough categories and create an array with only the "category" key
        foreach ($post_categories as $key => $value) {
            $value['category'] = $this->get_form_field_by_id($value['category'], $form_data);

            $post_categories[$key] = $value['category'];

            // Get the category id from the category slug
            $post_categories[$key] = get_category_by_slug($post_categories[$key])->term_id;
        }

        // Handle taxonomies
        $post_taxonomies = $form_settings['pro_forms_post_action_post_create_taxonomies'] ? $form_settings['pro_forms_post_action_post_create_taxonomies'] : [];

        // Loop through taxonomies and create an array with taxonomy names as keys and arrays of term IDs as values
        $temp_post_taxonomies = [];
        foreach ($post_taxonomies as $key => $value) {
            $taxonomy_slug = $value['taxonomy'];
            $term_slugs = array_map('trim', explode(',', $value['term']));

            foreach ($term_slugs as $term_slug) {
                $term_slug = $this->get_form_field_by_id($term_slug, $form_data);
                $term = get_term_by('slug', $term_slug, $taxonomy_slug);

                if (!isset($temp_post_taxonomies[$taxonomy_slug])) {
                    $temp_post_taxonomies[$taxonomy_slug] = [];
                }

                $temp_post_taxonomies[$taxonomy_slug][] = $term->term_id;
            }
        }

        $post_taxonomies = $temp_post_taxonomies;

        $post_title = $form_settings['pro_forms_post_action_post_create_title'];
        $post_content = $form_settings['pro_forms_post_action_post_create_content'];
        $post_thumbnail = $form_settings['pro_forms_post_action_post_create_thumbnail'] ? $form_settings['pro_forms_post_action_post_create_thumbnail'] : false;

        if ($post_thumbnail) {
            // Handle Thumbnail. Returns the attachment ID
            $post_thumbnail = $this->handle_file($post_thumbnail, $form_settings, $form_files);
        }

        $post_title = $this->get_form_field_by_id($post_title, $form_data);
        $post_content = $this->get_form_field_by_id($post_content, $form_data);

        foreach ($form_settings['pro_forms_post_action_post_create_custom_fields'] as $custom_field) {
            $custom_field['name'] = $this->adjust_meta_field_name($custom_field['name']);
            $custom_fields[$custom_field['name']] = $this->get_form_field_by_id($custom_field['value'], $form_data, null, $form_data, $form_files);
        }

        $post = array(
            'post_title'    => $post_title ? bricks_render_dynamic_data($post_title) : 'Untitled',
            'post_content'  => $post_content ? bricks_render_dynamic_data($post_content) : '',
            'post_status'   => $post_status,
            'post_type'     => $form_settings['pro_forms_post_action_post_create_pt'] ? $form_settings['pro_forms_post_action_post_create_pt'] : 'post',
            'meta_input'    => $custom_fields ? $custom_fields : array(),
            'post_category' => $post_categories ? $post_categories : array(),
            'post_author'   => $post_author ? $post_author : null,
        );

        $post_id = wp_insert_post($post);

        if ($post_thumbnail) {
            set_post_thumbnail($post_id, $post_thumbnail);
        }

        // Set taxonomy terms for the newly created post
        foreach ($post_taxonomies as $taxonomy => $term_ids) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        }

        return $post_id;
    }

    public function add_option($form_settings, $form_data)
    {
        $option_data = $form_settings['pro_forms_post_action_option_add_option_data'];

        $option_data = array_map(function ($item) {
            return array(
                'name'  => bricks_render_dynamic_data($item['name']),
                'value' => bricks_render_dynamic_data($item['value']),
            );
        }, $option_data);

        // Add Option for each $option_data
        foreach ($option_data as $option) {
            $option_name = $option['name'];
            $option_value = $option['value'];

            if (!isset($option_name) || !isset($option_value)) {
                continue;
            }

            $option_name = $this->get_form_field_by_id($option_name, $form_data);
            $option_value = $this->get_form_field_by_id($option_value, $form_data);

            $option_value = $this->sanitize_value($option_value);

            add_option($option_name, $option_value);
        }

        return true;
    }

    public function update_option($form_settings, $form_data)
    {
        $option_data = $form_settings['pro_forms_post_action_option_update_option_data'];

        $option_data = array_map(function ($item) {
            return array(
                'id' => $item['id'],
                'name'         => bricks_render_dynamic_data($item['name']),
                'value'        => bricks_render_dynamic_data($item['value']),
                'type'         => $item['type'],
                'selector'     => $item['selector'],
                'number_field' => bricks_render_dynamic_data($item['number_field'], $post_id),
            );
        }, $option_data);

        $updated_values = array();

        // Update Option for each $option_data
        foreach ($option_data as $option) {
            $option_name = $option['name'];
            $option_value = $option['value'];
            $option_type = $option['type'];
            $option_selector = $option['selector'];
            $option_number_field = $option['number_field'];

            if (!isset($option_name) || !isset($option_value)) {
                continue;
            }

            $option_name = $this->get_form_field_by_id($option_name, $form_data);
            $option_value = $this->get_form_field_by_id($option_value, $form_data);

            $new_option_value;
            $current_value = get_option($option_name);

            switch ($option_type) {
                case 'replace':
                    $new_option_value = $option_value;
                    break;
                case 'increment':
                    $new_option_value = intval($current_value) + 1;
                    break;
                case 'decrement':
                    $new_option_value = intval($current_value) - 1;
                    break;
                case 'increment_by_number':
                    $option_number_field = $this->get_form_field_by_id($option_number_field, $form_data);
                    $new_option_value = intval($current_value) + intval($option_number_field);
                    break;
                case 'decrement_by_number':
                    $option_number_field = $this->get_form_field_by_id($option_number_field, $form_data);
                    $new_option_value = intval($current_value) - intval($option_number_field);
                    break;
                case 'add_to_array':
                    // If the current value is not an array, make it one and add the new value
                    if (!is_array($current_value)) {
                        $new_option_value = array($current_value, $option_value);
                    } else {
                        $new_option_value = array_merge($current_value, array($option_value));
                    }
                    break;
                case 'remove_from_array':
                    // If the current value is not an array, make it one and remove the new value
                    if (is_array($current_value)) {
                        $new_option_value = array_diff($current_value, array($option_value));
                    }
                    break;
                default:
                    $new_option_value = $option_value;
                    break;
            }

            $new_option_value = $this->sanitize_value($new_option_value);

            update_option($option_name, $new_option_value);

            $allow_live_update = $option_type === 'add_to_array' || $option_type === 'remove_from_array' ? false : true;

            array_push(
                $updated_values,
                array(
                    'name'     => $option_name,
                    'value'    => $new_option_value,
                    'selector' => $option_selector,
                    'live'     => $allow_live_update,
                    'data' => $option
                )
            );
        }

        return $updated_values;
    }

    public function delete_option($form_settings, $form_data)
    {
        $option_data = $form_settings['pro_forms_post_action_option_delete_option_data'];

        $option_data = array_map(function ($item) {
            return array(
                'name' => bricks_render_dynamic_data($item['name']),
            );
        }, $option_data);

        // Delete Option for each $option_data
        foreach ($option_data as $option) {
            $option_name = $option['name'];

            if (!isset($option_name)) {
                continue;
            }

            delete_option($option_name);
        }

        return true;
    }

    public function update_post($form_settings, $form_data, $current_post_id, $dynamic_post_id, $form_files)
    {
        $post_id;
        $post_title;
        $post_content;
        $post_status;
        $post_excerpt;
        $post_date;
        $post_thumbnail;

        $post_id = $form_settings['pro_forms_post_action_post_update_post_id'] ? $form_settings['pro_forms_post_action_post_update_post_id'] : $post_id;
        $post_id = $this->get_form_field_by_id($post_id, $form_data);

        if (!$post_id || !is_numeric($post_id)) {
            $post_id = $current_post_id;
        }

        $post_id = absint($post_id);

        if (!$post_id && isset($dynamic_post_id) && $dynamic_post_id) {
            $post_id = absint($dynamic_post_id);
        }

        $post_title = $form_settings['pro_forms_post_action_post_update_title'] ? $form_settings['pro_forms_post_action_post_update_title'] : $post_title;
        $post_title = $this->get_form_field_by_id($post_title, $form_data);

        $post_content = $form_settings['pro_forms_post_action_post_update_content'] ? $form_settings['pro_forms_post_action_post_update_content'] : $post_content;
        $post_content = $this->get_form_field_by_id($post_content, $form_data);

        $post_status = $form_settings['pro_forms_post_action_post_update_status'] ? $form_settings['pro_forms_post_action_post_update_status'] : $post_status;
        $post_status = $this->get_form_field_by_id($post_status, $form_data);

        $post_excerpt = $form_settings['pro_forms_post_action_post_update_excerpt'] ? $form_settings['pro_forms_post_action_post_update_excerpt'] : $post_excerpt;
        $post_excerpt = $this->get_form_field_by_id($post_excerpt, $form_data);

        $post_date = $form_settings['pro_forms_post_action_post_update_date'] ? $form_settings['pro_forms_post_action_post_update_date'] : $post_date;
        $post_date = $this->get_form_field_by_id($post_date, $form_data);

        $post_thumbnail = $form_settings['pro_forms_post_action_post_update_thumbnail'] ? $form_settings['pro_forms_post_action_post_update_thumbnail'] : $post_thumbnail;

        if ($post_thumbnail) {
            // Handle Thumbnail. Returns the attachment ID
            $post_thumbnail = $this->handle_file($post_thumbnail, $form_settings, $form_files);
        }

        if ($post_date) {
            $post_date = date('Y-m-d H:i:s', strtotime($post_date));
        }

        // Sanitize
        $post_title = sanitize_text_field($post_title);
        $post_content = wp_kses_post($post_content);
        $post_status = sanitize_key($post_status);
        $post_excerpt = sanitize_text_field($post_excerpt);
        $post_date = sanitize_text_field($post_date);

        $post_data = array(
            'ID'           => $post_id,
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => $post_status,
            'post_excerpt' => $post_excerpt,
            'post_date'    => $post_date,
        );

        $post_data = array_filter($post_data);

        $result = wp_update_post($post_data, true);

        if ($post_thumbnail && $post_id) {
            set_post_thumbnail($post_id, $post_thumbnail);
        }

        return $result;
    }

    public function update_post_meta($form_settings, $form_data, $post_id, $dynamic_post_id, $form_files)
    {
        if (isset($dynamic_post_id) && $dynamic_post_id) {
            $dynamic_post_id = $this->get_form_field_by_id($dynamic_post_id, $form_data);
            $dynamic_post_id = absint($dynamic_post_id);
        }

        $post_meta_data = $form_settings['pro_forms_post_action_update_post_meta_data'];

        $post_meta_data = array_map(function ($item) use ($post_id, $dynamic_post_id) {
            $post_id = isset($item['post_id']) && $item['post_id'] ? intval($item['post_id']) : intval($post_id);

            $post_id = $dynamic_post_id ? $dynamic_post_id : $post_id;

            return array(
                'id' => $item['id'],
                'post_id'      => $post_id,
                'name'         => bricks_render_dynamic_data($item['name'], $post_id),
                'value'        => bricks_render_dynamic_data($item['value'], $post_id),
                'type'         => $item['type'],
                'ignore_empty' => $item['ignore_empty'],
                'selector'     => bricks_render_dynamic_data($item['selector'], $post_id),
                'number_field' => bricks_render_dynamic_data($item['number_field'], $post_id),
            );
        }, $post_meta_data);

        $updated_values = array();

        // Update Post Meta for each $post_meta_data
        foreach ($post_meta_data as $post_meta) {
            $post_id = $post_meta['post_id'];
            $post_meta_name = $post_meta['name'];
            $post_meta_value = $post_meta['value'];
            $post_meta_type = $post_meta['type'];
            $post_meta_ignore_empty = $post_meta['ignore_empty'];
            $post_meta_selector = $post_meta['selector'];
            $post_meta_number_field = $post_meta['number_field'];

            if (!isset($post_meta_name) || !isset($post_meta_value)) {
                continue;
            }

            $post_meta_value = $this->get_form_field_by_id($post_meta_value, $form_data, null, $form_settings, $form_files);

            if (empty($post_meta_value) && $post_meta_ignore_empty) {
                continue;
            }

            $new_post_meta_value;
            $current_value = get_post_meta($post_id, $post_meta_name, true);

            switch ($post_meta_type) {
                case 'replace':
                    $new_post_meta_value = $post_meta_value;
                    break;
                case 'increment':
                    $new_post_meta_value = intval($current_value) + 1;
                    break;
                case 'decrement':
                    $new_post_meta_value = intval($current_value) - 1;
                    break;
                case 'increment_by_number':
                    $post_meta_number_field = $this->get_form_field_by_id($post_meta_number_field, $form_data);
                    $new_post_meta_value = intval($current_value) + intval($post_meta_number_field);
                    break;
                case 'decrement_by_number':
                    $post_meta_number_field = $this->get_form_field_by_id($post_meta_number_field, $form_data);
                    $new_post_meta_value = intval($current_value) - intval($post_meta_number_field);
                    break;
                case 'add_to_array':
                    // Add the new value to the array
                    if (!is_array($current_value)) {
                        if (!empty(trim($current_value))) {
                            $new_post_meta_value = array($current_value, $post_meta_value);
                        } else {
                            $new_post_meta_value = array($post_meta_value);
                        }
                    } else {
                        $new_post_meta_value = array_merge($current_value, array($post_meta_value));
                    }

                    break;
                case 'remove_from_array':
                    // If the current value is not an array, make it one and remove the new value
                    if (is_array($current_value)) {
                        $new_post_meta_value = array_diff($current_value, array($post_meta_value));
                    }

                    break;
                default:
                    $new_post_meta_value = $post_meta_value;
                    break;
            }

            $new_post_meta_value = $this->sanitize_value($new_post_meta_value);

            if (class_exists('ACF')) {
                $this->update_acf_field($post_meta_name, $new_post_meta_value, $post_id);
            } else {
                update_post_meta($post_id, $post_meta_name, $new_post_meta_value);
            }

            // Allow Live Update if post_meta_type is not array related
            $allow_live_update = $post_meta_type === 'add_to_array' || $post_meta_type === 'remove_from_array' ? false : true;

            array_push($updated_values, [
                'selector' => $post_meta_selector,
                'value'    => $new_post_meta_value,
                'live'     => $allow_live_update,
                'data' => $post_meta
            ]);
        }

        return $updated_values;
    }

    public function update_user_meta($form_settings, $form_data, $post_id, $form_id, $dynamic_post_id, $form_files)
    {
        $data = $form_settings['pro_forms_post_action_update_user_meta_data'];

        $data = array_map(function ($item) use ($form_data, $post_id, $form_settings, $form_files) {
            return array(
                'id'         => $this->get_form_field_by_id(isset($item['user_id']) ? $item['user_id'] : get_current_user_id(), $form_data),
                'key'        => $this->get_form_field_by_id($item['key'], $form_data),
                'value'        => $this->get_form_field_by_id($item['value'], $form_data, null, $form_settings, $form_files),
                'type'         => $item['type'],
                'ignore_empty' => $item['ignore_empty'],
                'selector'     => bricks_render_dynamic_data($item['selector'], $post_id),
                'number_field' => $this->get_form_field_by_id($item['number_field'], $form_data, $post_id),
            );
        }, $data);

        $updated_values = array();

        foreach ($data as $d) {
            $id = $d['id'];
            $key = $d['key'];
            $value = $d['value'];
            $type = $d['type'];
            $ignore_empty = $d['ignore_empty'];
            $selector = $d['selector'];
            $number_field = $d['number_field'];

            if (!isset($key) || !isset($value) || !isset($id)) {
                continue;
            }

            $key = $this->get_form_field_by_id($key, $form_data);

            if (empty($value) && $ignore_empty) {
                continue;
            }

            $id = absint($id);
            $key = $this->sanitize_value($key);
            $value = $this->sanitize_value($value);

            $new_value;
            $current_value = get_user_meta($id, $key, true);

            switch ($type) {
                case 'replace':
                    $new_value = $value;
                    break;
                case 'increment':
                    $new_value = intval($current_value) + 1;
                    break;
                case 'decrement':
                    $new_value = intval($current_value) - 1;
                    break;
                case 'increment_by_number':
                    $number_field = $this->get_form_field_by_id($number_field, $form_data);
                    $new_value = intval($current_value) + intval($number_field);
                    break;
                case 'decrement_by_number':
                    $number_field = $this->get_form_field_by_id($number_field, $form_data);
                    $new_value = intval($current_value) - intval($number_field);
                    break;
                case 'add_to_array':
                    // Add the new value to the array
                    if (!is_array($current_value)) {
                        if (!empty(trim($current_value))) {
                            $new_value = array($current_value, $value);
                        } else {
                            $new_value = array($value);
                        }
                    } else {
                        $new_value = array_merge($current_value, array($value));
                    }

                    break;
                case 'remove_from_array':
                    // If the current value is not an array, make it one and remove the new value
                    if (is_array($current_value)) {
                        $new_value = array_diff($current_value, array($value));
                    }

                    break;
                default:
                    $new_value = $value;
                    break;
            }

            $new_value = $this->sanitize_value($new_value);

            update_user_meta($id, $key, $new_value);

            $allow_live_update = $type === 'add_to_array' || $type === 'remove_from_array' ? false : true;

            array_push($updated_values, [
                'selector' => $selector,
                'id'     => $id,
                'key'    => $key,
                'value'     => $new_value,
                'live'     => $allow_live_update,
            ]);
        }

        return $updated_values;
    }

    public function reset_user_password($form_settings, $form_data, $post_id, $form_id)
    {
        $method = $form_settings['resetUserPasswordMethod'];
        $email_field = $form_settings['resetUserPasswordEmail'];
        $email = $this->get_form_field_by_id($email_field, $form_data);
        $user = get_user_by('email', $email);

        switch ($method) {
            case 'request':
                if ($user) {

                    $result = retrieve_password($user->user_login);

                    if (is_wp_error($result)) {
                        error_log($result->get_error_message());
                    }
                } else {
                    error_log("User with email $email does not exist");
                }
                break;
            case 'update':
                $verify_password_confirmation = isset($form_settings['resetUserPasswordVerifyPasswordConfirmation']) ? $form_settings['resetUserPasswordVerifyPasswordConfirmation'] : false;
                $verify_current_password = isset($form_settings['resetUserPasswordVerifyCurrentPassword']) ? $form_settings['resetUserPasswordVerifyCurrentPassword'] : false;
                $strong_passwords = isset($form_settings['resetUserPasswordAllowOnlyStrongPasswords']) ? $form_settings['resetUserPasswordAllowOnlyStrongPasswords'] : false;

                $current_password = isset($form_settings['resetUserPasswordCurrentPasswordValue']) ? $form_settings['resetUserPasswordCurrentPasswordValue'] : null;
                if ($current_password) {
                    $current_password = $this->get_form_field_by_id($current_password, $form_data);
                    $current_password = $this->sanitize_value($current_password);
                }

                $new_password = isset($form_settings['resetUserPasswordNewPassword']) ? $form_settings['resetUserPasswordNewPassword'] : null;
                if ($new_password) {
                    $new_password = $this->get_form_field_by_id($new_password, $form_data);
                    $new_password = $this->sanitize_value($new_password);
                }

                $new_password_confirm = isset($form_settings['resetUserPasswordPasswordConfirmationValue']) ? $form_settings['resetUserPasswordPasswordConfirmationValue'] : null;
                if ($new_password_confirm) {
                    $new_password_confirm = $this->get_form_field_by_id($new_password_confirm, $form_data);
                    $new_password_confirm = $this->sanitize_value($new_password_confirm);
                }

                $note_enter_new_password = isset($form_settings['resetUserPasswordNotificationNewPassword']) ? $form_settings['resetUserPasswordNotificationNewPassword'] : "Please enter a new password.";
                $note_current_password_incorrect = isset($form_settings['resetUserPasswordNotificationCurrentPasswordIncorrect']) ? $form_settings['resetUserPasswordNotificationCurrentPasswordIncorrect'] : "The current password is incorrect.";
                $note_passwords_do_not_match = isset($form_settings['resetUserPasswordNotificationPasswordsDoNotMatch']) ? $form_settings['resetUserPasswordNotificationPasswordsDoNotMatch'] : "Passwords do not match.";

                if (!isset($new_password) || empty($new_password)) {
                    return wp_send_json_error(array(
                        'message' => __($note_enter_new_password, 'bricks'),
                    ));
                }

                if ($verify_password_confirmation == true) {
                    // Compare passwords
                    if ($new_password != $new_password_confirm) {
                        return wp_send_json_error(array(
                            'message' => __($note_passwords_do_not_match, 'bricks'),
                        ));
                    }
                }

                if ($strong_passwords) {
                    $password_strength = $this->check_password_strength($new_password);
                    if ($password_strength['score'] < 3) {
                        return wp_send_json_error(array(
                            'message' => implode(" ", $password_strength['reasons']),
                        ));
                    }
                }

                if ($user) {
                    if (isset($verify_current_password) && $verify_current_password == true) {
                        if (wp_check_password($current_password, $user->data->user_pass, $user->ID)) {
                            $this->reset_password($new_password, $user->ID);
                        } else {
                            return wp_send_json_error(array(
                                'message' => __($note_current_password_incorrect, 'bricks'),
                            ));
                        }
                    } else {
                        $this->reset_password($new_password, $user->ID);
                    }
                } else {
                    return wp_send_json_error(array(
                        'message' => __("User not found", 'bricks'),
                    ));
                }

                break;
            default:
                break;
        }
    }

    public function check_password_strength($password)
    {
        $score = 0;
        $reasons = array();

        // Check length
        if (strlen($password) < 8) {
            $score = 0;
            $reasons[] = "Password should be at least 8 characters long.";
        } else {
            $score++;
        }

        // Check uppercase and lowercase letters
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $score = 0;
            $reasons[] = "Password should include both uppercase and lowercase letters.";
        } else {
            $score++;
        }

        // Check numbers
        if (!preg_match('/[0-9]/', $password)) {
            $score = 0;
            $reasons[] = "Password should include at least one number.";
        } else {
            $score++;
        }

        // Check special characters
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $score = 0;
            $reasons[] = "Password should include at least one special character.";
        } else {
            $score++;
        }

        // Check for common patterns
        $common_patterns = array(
            'password',
            '123456',
            'qwerty',
            'admin',
            'letmein',
            'welcome',
            'football',
        );
        if (in_array(strtolower($password), $common_patterns)) {
            $score = 0;
            $reasons[] = "Password is too common or easily guessable.";
        } else {
            $score++;
        }

        return array(
            'score' => $score,
            'reasons' => $reasons,
        );
    }

    public function reset_password($new_password, $user_id)
    {
        wp_set_password($new_password, $user_id);
        wp_set_auth_cookie($user_id);
    }

    public function set_storage_item($form_settings, $form_data, $post_id)
    {
        $option_data = $form_settings['pro_forms_post_action_set_storage_item_data'];

        $option_data = array_map(function ($item) use ($post_id) {
            return array(
                'id' => $item['id'],
                'name'         => bricks_render_dynamic_data($item['name']),
                'value'        => bricks_render_dynamic_data($item['value']),
                'type'         => $item['type'],
                'selector'     => $item['selector'],
                'number_field' => bricks_render_dynamic_data($item['number_field'], $post_id),
            );
        }, $option_data);

        $updated_values = array();

        // Update Option for each $option_data
        foreach ($option_data as $option) {
            $option_name = $option['name'];
            $option_value = $option['value'];
            $option_type = $option['type'];
            $option_selector = $option['selector'];
            $option_number_field = $option['number_field'];

            if (!isset($option_name) || !isset($option_value)) {
                continue;
            }

            // Loop trough the form_data object
            $option_value = $this->get_form_field_by_id($option_value, $form_data);

            $new_option_value;
            $current_value = 0;

            switch ($option_type) {
                case 'replace':
                    $new_option_value = $option_value;
                    break;
                case 'increment':
                    $new_option_value = 1;
                    break;
                case 'decrement':
                    $new_option_value = 1;
                    break;
                case 'increment_by_number':
                    $option_number_field = $this->get_form_field_by_id($option_number_field, $form_data);
                    $new_option_value = intval($option_number_field);
                    break;
                case 'decrement_by_number':
                    $option_number_field = $this->get_form_field_by_id($option_number_field, $form_data);
                    $new_option_value = intval($option_number_field);
                    break;
                case 'add_to_array':
                    $new_option_value = $option_value;
                    break;
                case 'remove_from_array':
                    $new_option_value = $option_value;

                    break;
                default:
                    $new_option_value = $option_value;
                    break;
            }

            $allow_live_update = $option_type === 'add_to_array' || $option_type === 'remove_from_array' ? false : true;

            array_push($updated_values, [
                'name'     => $option_name,
                'value'    => $new_option_value,
                'live'     => $allow_live_update,
                'selector' => $option_selector,
                'type'     => $option_type,
                'data' => $option
            ]);
        }

        return $updated_values;
    }

    public function wc_add_to_cart($form_settings, $form_data, $post_id, $form_id)
    {
        // If WooCommerce is not active, return
        if (!class_exists('WooCommerce')) {
            return;
        }

        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';

        if (is_null(\WC()->cart)) {
            wc_load_cart();
        }

        $product = $form_settings['pro_forms_post_action_add_to_cart_product'];
        $product_id = $form_settings['pro_forms_post_action_add_to_cart_product_id'];
        $quantity = $form_settings['pro_forms_post_action_add_to_cart_quantity'];
        $price = isset($form_settings['pro_forms_post_action_add_to_cart_price']) ? $form_settings['pro_forms_post_action_add_to_cart_price'] : false;
        $is_total_price = $form_settings['pro_forms_post_action_add_to_cart_is_total_price'];
        $consider_variations = $form_settings['pro_forms_post_action_add_to_cart_consider_variations'];
        $custom_fields = $form_settings['pro_forms_post_action_add_to_cart_custom_fields'];

        if (empty($product) && empty($product_id)) {
            return;
        }

        if (empty($quantity)) {
            return;
        }

        if ($product !== 'custom') {
            $product_id = $product;
        } else {
            $product_id = $this->get_form_field_by_id($product_id, $form_data);
        }

        $quantity = $this->get_form_field_by_id($quantity, $form_data);

        if ($price) {
            $price = $this->get_form_field_by_id($price, $form_data);

            // If form_settings['fields'] contains a field['type'] == 'calculation', we need to re-calculate the price
            $validate = $this->wc_add_to_cart_validate($form_settings, $form_data);

            if ($validate[0] === false) {
                wp_send_json_error([
                    'message' => __('Product could not be added to cart.', 'bricksforge'),
                    'data' => $form_data
                ]);
            }

            // If validate[1] is not null
            if ($validate[1] !== null) {
                $price = $validate[1];
            }

            // The price should match the format of the WooCommerce price
            $price = wc_format_decimal($price, wc_get_price_decimals());
            $price = floatval($price);

            // If the quantity is > 1, we need to divide the price by the quantity
            if ($quantity > 1 && $is_total_price === true) {
                $price = $price / $quantity;
            }
        }

        // Custom Fields are an array. We need to loop trough them and get the values
        $custom_fields = array_map(function ($item) use ($form_data) {
            $item['label'] = $this->get_form_field_by_id($item['label'], $form_data);
            $item['value'] = $this->get_form_field_by_id($item['value'], $form_data);
            return $item;
        }, $custom_fields);

        // Convert formats
        $product_id = intval($product_id);
        $quantity = intval($quantity);

        $product = wc_get_product($product_id);
        $is_variable_product = $product->is_type('variable');

        // Access the cart object from $woocommerce
        $cart = \WC()->cart;

        // Set Session
        $cart->set_session();

        // Generate a unique cart item key
        $unique_cart_item_key = uniqid();

        // Set the cart item meta data
        $cart_item_data = array(
            'brf_product_id' => $product_id,
            BRICKSFORGE_WC_CART_ITEM_KEY => $unique_cart_item_key,
            'brf_custom_fields' => []
        );

        if ($price) {
            $cart_item_data['brf_custom_price'] = $price;
        }

        // For each custom field, add it to the cart item data in the format like the 'brf_color' above
        foreach ($custom_fields as $custom_field) {
            // Build a key from the label and add 'brf' as prefix. Example: Product Color should be 'brf_product_color'
            $cf_key = 'brf_' . strtolower(str_replace(' ', '_', $custom_field['label']));

            // Add the field to 'brf_custom_fields' array of the cart item data
            $cart_item_data['brf_custom_fields'][$cf_key] = [
                'label' => $custom_field['label'],
                'value' => $custom_field['value']
            ];
        }

        // Handle Variable Products
        if ($is_variable_product === true) {
            // Find the matching variation ID if applicable
            if (!empty($cart_item_data['brf_custom_fields']) && $consider_variations === true) {
                $variation_id = $this->find_matching_variation_id($product_id, $cart_item_data['brf_custom_fields']);

                if (!$variation_id) {
                    wp_send_json_error(array(
                        'message' => __('No matching variation found', 'bricks-for-woocommerce')
                    ));
                }

                $cart_item_data['variation_id'] = $variation_id;

                // Update Price
                $variation = wc_get_product($variation_id);
                $price = $variation->get_price();
                $cart_item_data['brf_custom_price'] = $price;
            } else {
                wp_send_json_error(array(
                    'message' => __('Product could not be added to cart', 'bricksforge')
                ));
            }
        }

        $cart_item_key = $cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);

        if (!$cart_item_key) {
            wp_send_json_error(array(
                'message' => __('Product could not be added to cart', 'bricksforge')
            ));
        }

        do_action('woocommerce_ajax_added_to_cart', $product_id);

        // Store the unique cart item key in the session array
        $stored_unique_keys = \WC()->session->get(BRICKSFORGE_WC_CART_ITEM_KEY, array());

        // If its a string, convert it to an array
        if (is_string($stored_unique_keys)) {
            $stored_unique_keys = array($stored_unique_keys);
        }

        $stored_unique_keys[$cart_item_key] = $unique_cart_item_key;

        \WC()->session->set(BRICKSFORGE_WC_CART_ITEM_KEY, $stored_unique_keys);

        $stored_custom_fields = WC()->session->get('brf_custom_fields', array());
        $stored_custom_fields[$cart_item_key] = $cart_item_data['brf_custom_fields'];
        WC()->session->set('brf_custom_fields', $stored_custom_fields);

        return true;
    }

    private function wc_add_to_cart_validate($form_settings, $form_data)
    {
        $passed = true;
        $price = null;

        if (!empty($form_settings['fields'])) {
            $fields = $form_settings['fields'];

            foreach ($fields as $key => $field) {
                if ($field['type'] == 'calculation') {
                    $formula = bricks_render_dynamic_data($field['formula']);
                    $result = $this->calculate_formula($formula, $form_data, $field);

                    if ($result !== null && is_numeric($result)) {
                        $empty_message = isset($field['emptyMessage']) ? $field['emptyMessage'] : '';
                        $price = $result;
                    }
                }

                // Type select, radio, checkbox
                if ($field['type'] == 'select' || $field['type'] == 'radio' || $field['type'] == 'checkbox') {
                    // Check if the field value is available in the field options
                    $field_value = $this->get_form_field_by_id($field['id'], $form_data);

                    // Field Options syntax: 5|S\n10|M\n15|L
                    $field_options = $field['options'];
                    $field_options = explode("\n", $field_options);

                    // Check if $field_options is an array and contains the field_value. But we need to check for the value before the pipe
                    $field_options = array_map(function ($item) {
                        $item = explode('|', $item);
                        return $item[0];
                    }, $field_options);

                    if (!in_array($field_value, $field_options)) {
                        $passed = false;
                    }
                }
            }
        }

        return [$passed, $price];
    }

    private function find_matching_variation_id($product_id, $custom_fields)
    {

        $product = wc_get_product(intval($product_id));

        if (!$product) {
            return 0;
        }

        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            $variation_attributes = $variation['attributes'];
            $match = true;

            foreach ($custom_fields as $custom_field) {
                $attribute_key = 'attribute_' . sanitize_title($custom_field['label']);
                $attribute_value = $custom_field['value'];

                if (!isset($variation_attributes[$attribute_key]) || $variation_attributes[$attribute_key] !== $attribute_value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return $variation['variation_id'];
            }
        }

        return 0; // Return 0 if no matching variation is found
    }

    public function get_variation_price($product_id, $custom_fields, $form_data)
    {
        // Check if product is variation type
        $product = wc_get_product($product_id);

        // If no product is found, return false
        if (!$product) {
            return false;
        }

        if (!$product->is_type('variable')) {
            return false;
        }

        // For each custom field['value'], call $this->get_form_field_by_id($field['id'], $form_data). Use arraymap
        $custom_fields = array_map(function ($item) use ($form_data) {
            $item['value'] = $this->get_form_field_by_id($item['value'], $form_data);
            return $item;
        }, $custom_fields);

        $variation_id = $this->find_matching_variation_id($product_id, $custom_fields);

        if (!$variation_id) {
            return false;
        }

        $variation = wc_get_product($variation_id);
        $price = $variation->get_price();

        // Be sure to match WooCommerce price format with a native WooCommerce function
        $price = wc_format_decimal($price, wc_get_price_decimals());
        $price = wc_price($price);

        return $price;
    }

    public function webhook($form_settings, $form_data, $post_id, $form_id)
    {
        $webhooks = isset($form_settings['pro_forms_post_action_webhooks']) ? $form_settings['pro_forms_post_action_webhooks'] : array();

        if (empty($webhooks)) {
            return;
        }

        $results = array();

        foreach ($webhooks as $webhook) {
            $debug_show_response_in_console = isset($webhook['debug_show_response_in_console']) ? $webhook['debug_show_response_in_console'] : false;

            if ($debug_show_response_in_console && isset($webhook['url']) && !empty($webhook['url'])) {
                $results[] = [
                    'url' => $webhook['url'],
                    'response' => $this->send_webhook($webhook, $form_data)
                ];
            } else {
                $this->send_webhook($webhook, $form_data);
            }
        }

        return $results;
    }

    private function send_webhook($webhook, $form_data)
    {
        $url = isset($webhook['url']) ? $webhook['url'] : '';
        $method = isset($webhook['method']) ? strtoupper($webhook['method']) : 'POST';
        $contentType = isset($webhook['content_type']) ? $webhook['content_type'] : 'json';
        $data = isset($webhook['data']) ? $webhook['data'] : array();
        $headers = isset($webhook['headers']) ? $webhook['headers'] : array();
        $needs_hmac = isset($webhook['add_hmac']) ? $webhook['add_hmac'] : false;
        $hmac_secret = isset($webhook['hmac_key']) ? $webhook['hmac_key'] : '';
        $hmac_header_name = isset($webhook['hmac_header_name']) ? $webhook['hmac_header_name'] : 'HMAC';

        $webhook_data = array();

        if (empty($url)) {
            wp_send_json_error(array(
                'message' => __('Webhook could not be sent. No URL was provided.', 'bricksforge'),
            ));
        }

        foreach ($data as $d) {
            $keys = explode('.', $d['key']); // Split key into components
            $value = $this->get_form_field_by_id($d['value'], $form_data, null, null, null, false);
            $currentArray = &$webhook_data;

            $lastKeyIndex = count($keys) - 1;

            if ($value == "[]") {
                $value = [];
            }

            if ($value == "{}") {
                $value = new \stdClass();
            }

            foreach ($keys as $index => $key) {
                $match = [];
                $isArray = preg_match('/(.*)(\[(\d+)\])/', $key, $match);

                if ($isArray) {
                    $key = $match[1]; // The key name
                    $arrayIndex = (int)$match[3]; // The array index
                }

                if ($index === $lastKeyIndex) { // If this is the last key
                    if ($isArray) {
                        if (!isset($currentArray[$key]) || !is_array($currentArray[$key])) {
                            $currentArray[$key] = [];
                        }
                        $currentArray[$key][$arrayIndex] = $value;
                    } else {
                        $currentArray[$key] = $value;
                    }
                    break;
                }

                if (!isset($currentArray[$key]) || (!is_array($currentArray[$key]) && !$isArray)) {
                    $currentArray[$key] = [];
                }
                if ($isArray && !isset($currentArray[$key][$arrayIndex])) {
                    $currentArray[$key][$arrayIndex] = [];
                }

                if ($isArray) {
                    $tempArray = &$currentArray[$key];
                    $currentArray = &$tempArray[$arrayIndex];
                } else {
                    $currentArray = &$currentArray[$key];
                }
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $contentType == 'json' ? json_encode($webhook_data) : http_build_query($webhook_data));

        // Default headers
        $webhook_headers = $contentType == 'json' ? array('Content-Type:application/json') : array('Content-Type:application/x-www-form-urlencoded');

        foreach ($headers as $header) {
            $key = $header['key'];
            $value = $header['value'];
            $webhook_headers[] = "$key: $value";
        }

        if ($needs_hmac && !empty($hmac_secret)) {
            // HMac Secret Key
            $secret_key = $hmac_secret;

            // Generate HMAC
            $hmac_payload = $contentType == 'json' ? json_encode($webhook_data) : http_build_query($webhook_data);
            $hmac = hash_hmac('sha256', $hmac_payload, $hmac_secret);

            $webhook_headers[] = "$hmac_header_name: $hmac";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $webhook_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            wp_send_json_error(array(
                'message' => __('Webhook could not be sent. An error occurred.', 'bricksforge'),
            ));
        }

        curl_close($ch);

        return json_decode($result, true);
    }

    public function create_submission($form_settings, $form_data, $post_id, $form_id, $form_files)
    {
        global $wpdb;
        $form_fields = $this->get_form_fields_from_ids($form_settings, $form_data);

        if (isset($form_settings['submission_prevent_duplicates']) && $form_settings['submission_prevent_duplicates']) {
            $is_duplicate = $this->check_for_duplicates($form_settings, $form_data, $form_id);
            if ($is_duplicate[0] === true) {
                return [
                    'status'  => 'duplicate',
                    'message' => $is_duplicate[1]
                ];
            }
        }

        if (isset($form_settings['submission_max']) && !empty($form_settings['submission_max'])) {
            $max_submissions = intval(sanitize_text_field($form_settings['submission_max']));

            global $wpdb;
            $table_name = $wpdb->prefix . BRICKSFORGE_SUBMISSIONS_DB_TABLE;

            $form_id = sanitize_text_field($form_id);
            $submissions_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE form_id = %s",
                    $form_id
                )
            );

            if ($submissions_count >= $max_submissions) {
                return "Maximum submissions reached";
            }
        }

        $submission_data = array();
        $submission_data['fields'] = array();

        foreach ($form_fields as $field) {
            array_push(
                $submission_data['fields'],
                array(
                    'label' => $field['label'],
                    'value' => $this->get_form_field_by_id($field['id'], $form_data, null, null, $form_files, true, true),
                    'id'    => $field['id']
                )
            );
        }

        $submission_data['post_id'] = $post_id;
        $submission_data['form_id'] = $form_id;

        // Convert submission data to JSON
        $submission_json = json_encode($submission_data);

        // Insert submission data into database
        global $wpdb;
        $table_name = $wpdb->prefix . BRICKSFORGE_SUBMISSIONS_DB_TABLE;
        $result = $wpdb->insert(
            $table_name,
            array(
                'form_id'   => $form_id,
                'post_id'   => $post_id,
                'timestamp' => current_time('mysql'),
                'fields'    => $submission_json
            )
        );

        // Handle Unread Submissions
        $unread_submissions = get_option("brf_unread_submissions", array());
        array_push($unread_submissions, $wpdb->insert_id);
        update_option("brf_unread_submissions", $unread_submissions);

        return $submission_data;
    }

    public function handle_turnstile($form_settings, $form_data, $turnstile_result)
    {
        $key = $this->get_turnstile_secret();

        if (!$key) {
            return true;
        }

        // Get the Turnstile response from the client-side form
        $turnstile_response = $turnstile_result;

        if (!$turnstile_response || empty($turnstile_response)) {
            return false;
        }

        // Verify the Turnstile response with a server-side request
        return $this->verify_turnstile_response($turnstile_response, $key);
    }

    public function get_turnstile_secret()
    {
        $turnstile_settings = array_values(array_filter(get_option('brf_activated_elements'), function ($tool) {
            return $tool->id == 5;
        }));

        if (count($turnstile_settings) === 0) {
            return false;
        }

        $turnstile_settings = $turnstile_settings[0];

        if (!isset($turnstile_settings->settings->useTurnstile) || $turnstile_settings->settings->useTurnstile !== true) {
            return false;
        }

        if (empty($turnstile_settings->settings->turnstileSecret)) {
            return false;
        }

        $decrypted_secret = $this->utils->decrypt($turnstile_settings->settings->turnstileSecret);

        return $decrypted_secret;
    }

    public function verify_turnstile_response($turnstile_response, $secret)
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $turnstile_response
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response);

        return $result && $result->success;
    }

    public function handle_hcaptcha($form_settings, $form_data, $captcha_result)
    {
        $key = $this->get_hcaptcha_key();

        if (!$key) {
            return true;
        }

        // Get the hCaptcha response from the client-side form
        $hcaptcha_response = $captcha_result;

        if (!$hcaptcha_response || empty($hcaptcha_response)) {
            return false;
        }

        // Verify the hCaptcha response with a server-side request
        return $this->verify_hcaptcha_response($hcaptcha_response, $key);
    }

    public function verify_hcaptcha_response($hcaptcha_response, $secret)
    {
        $url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $hcaptcha_response
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response);

        return $result && $result->success;
    }

    public function get_hcaptcha_key()
    {
        $hcaptcha_settings = array_values(array_filter(get_option('brf_activated_elements'), function ($tool) {
            return $tool->id == 5;
        }));

        if (count($hcaptcha_settings) === 0) {
            return false;
        }

        $hcaptcha_settings = $hcaptcha_settings[0];

        if (!$hcaptcha_settings->settings->useHCaptcha) {
            return false;
        }

        if (empty($hcaptcha_settings->settings->hCaptchaSecret)) {
            return false;
        }

        $decrypted_secret = $this->utils->decrypt($hcaptcha_settings->settings->hCaptchaSecret);

        return $decrypted_secret;
    }

    public function check_for_duplicates($form_settings, $form_data, $form_id)
    {
        $is_duplicate = [false, ''];
        $notice = "";
        $data_to_check = $form_settings['submission_prevent_duplicates_data'];

        if (!isset($data_to_check) || empty($data_to_check)) {
            return false;
        }

        foreach ($data_to_check as $data) {
            $field_id = $data['field'];
            $notice = $data['notice'] ? $data['notice'] : 'Error';

            $field_data = $form_data['form-field-' . $field_id];

            global $wpdb;

            $table_name = $wpdb->prefix . BRICKSFORGE_SUBMISSIONS_DB_TABLE;

            $submissions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT fields FROM $table_name WHERE form_id = %s",
                    $form_id
                )
            );

            if (empty($submissions)) {
                continue;
            }

            $submissions = json_decode(json_encode($submissions), true);

            foreach ($submissions as $submission) {
                $submission = json_decode($submission['fields'], true);

                foreach ($submission['fields'] as $submission) {
                    if ($field_data && $submission['id'] == $field_id && $submission['value'] == $field_data) {
                        $is_duplicate = [true, $notice];
                    }
                }
            }
        }

        return $is_duplicate;
    }

    private function get_form_fields_from_ids($form_settings, $form_data)
    {
        $form_fields = array();

        foreach ($form_data as $field_id => $field_value) {
            // Remove "form-field-" prefix from field ID
            $clean_field_id = str_replace('form-field-', '', $field_id);

            // If $field_id contains [] (i.e. it's an array), remove the array brackets
            if (strpos($clean_field_id, '[') !== false) {
                $clean_field_id = str_replace(['[', ']'], '', $clean_field_id);
            }

            // Check whether field ID is included in $form_settings['fields']['id']
            $field = array_filter($form_settings['fields'], function ($field) use ($clean_field_id) {
                return $field['id'] === $clean_field_id;
            });

            // If field is found, add it to $form_fields
            if (count($field) > 0) {
                $field = array_values($field)[0];

                // If $field_value is array, separata by comma. otherwise, just add the value
                if (is_array($field_value)) {
                    $field['value'] = implode(', ', $field_value);
                } else {
                    $field['value'] = $field_value;
                }

                array_push($form_fields, $field);
            }
        }

        return $form_fields;
    }

    public function update_acf_field($field_name, $value, $post_id)
    {
        update_field($field_name, $value, $post_id);
    }

    public function adjust_meta_field_name($field_name)
    {
        // If Field Name contains acf_ prefix, remove it
        $field_name = str_replace('acf_', '', $field_name);

        return $field_name;
    }

    public function get_form_field_by_id($id, $form_data, $current_post_id = null, $form_settings = null, $form_files = null, $implode_array = true, $force_file_url_output = false)
    {

        foreach ($form_data as $key => $value) {

            $form_id = explode('-', $key);
            $form_id = $form_id[2];

            if ($form_id === $id || $form_id === $id  . '[]') {

                // Check if there are files in the form data
                if (isset($form_files) && !empty($form_files)) {

                    // If there are files, check if the current field is a file field
                    foreach ($form_files as $file) {

                        if ($file['field'] === $id) {
                            // If it is a file field, handle this file
                            $file_url = $this->handle_file($id, $form_settings, $form_files, 'url', $force_file_url_output);

                            if ($file_url) {
                                return $file_url;
                            }
                        }
                    }
                }

                // If $value is an empty array, return empty string
                if (is_array($value) && empty($value)) {
                    return '';
                }

                // If $value is an array, return comma separated values
                if (is_array($value) && $implode_array) {
                    return implode(', ', bricks_render_dynamic_data($value, $current_post_id));
                }

                return bricks_render_dynamic_data($value, $current_post_id);
            }
        }

        // If $value is an empty array, return empty string
        return bricks_render_dynamic_data($id, $current_post_id);
    }

    public function render_dynamic_formular_data($formula, $form_data, $field_settings)
    {
        $formula = bricks_render_dynamic_data($formula);

        // Find each word wrapped by {}. For each field, we need the value and replace it with the value returned by get_form_field_by_id()
        preg_match_all('/{([^}]+)}/', $formula, $matches);

        foreach ($matches[1] as $match) {
            $field_value = $this->get_form_field_by_id($match, $form_data);

            if (isset($field_value) && $field_value !== "") {
                $formula = str_replace('{' . $match . '}', $field_value, $formula);
            } else {
                if (isset($field_settings['setEmptyToZero']) && $field_settings['setEmptyToZero']) {
                    $formula = str_replace('{' . $match . '}', 0, $formula);
                }
            }
        }

        return $formula;
    }

    public function sanitize_value($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $sub_value) {
                $value[$key] = $this->sanitize_value($sub_value);
            }
        } elseif (is_numeric($value)) {
            $value = preg_replace('/[^0-9]/', '', $value);
        } else {
            $value = wp_kses_post($value);
        }
        return $value;
    }

    public function initial_sanitization($form_settings, $form_data)
    {
        if (!isset($form_settings['fields'])) {
            return $form_data;
        }

        foreach ($form_settings['fields'] as $field) {
            if (isset($field['stripHTML']) && $field['stripHTML'] === true) {
                $field_id = $field['id'];
                $form_data['form-field-' . $field_id] = wp_strip_all_tags($form_data['form-field-' . $field_id]);
            }
        }

        return $form_data;
    }

    function shunting_yard($infix)
    {
        $infix = trim($infix);

        $output_queue = [];
        $operator_stack = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        // Change the regular expression to handle spaces between negative sign and number
        $tokens = preg_split('/\s*([\+\-\*\/\(\)])\s*/', ' ' . $infix, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $prevToken = '';
        foreach ($tokens as $key => $token) {
            // Handle negative numbers
            if ($token === '-' && (($key === 0) || in_array($prevToken, ['+', '-', '*', '/', '(']))) {
                $next_token = array_shift($tokens);
                $token = $token . $next_token;
            }

            if (is_numeric($token)) {
                $output_queue[] = $token;
            } elseif (in_array($token, ['+', '-', '*', '/'])) {
                while (!empty($operator_stack) && isset($precedence[end($operator_stack)]) && $precedence[end($operator_stack)] >= $precedence[$token]) {
                    $output_queue[] = array_pop($operator_stack);
                }
                $operator_stack[] = $token;
            } elseif ($token == '(') {
                $operator_stack[] = $token;
            } elseif ($token == ')') {
                while (!empty($operator_stack) && end($operator_stack) != '(') {
                    $output_queue[] = array_pop($operator_stack);
                }
                if (!empty($operator_stack) && end($operator_stack) == '(') {
                    array_pop($operator_stack);
                } else {
                    return "Mismatched parentheses in the formula.";
                }
            } else {
                return "Invalid character in the formula.";
            }

            $prevToken = $token;
        }

        while (!empty($operator_stack)) {
            if (end($operator_stack) == '(' || end($operator_stack) == ')') {
                return "Mismatched parentheses in the formula.";
            }
            $output_queue[] = array_pop($operator_stack);
        }

        return $output_queue;
    }

    function evaluate_postfix($postfix)
    {
        $stack = [];

        foreach ($postfix as $token) {
            if (is_numeric($token)) {
                array_push($stack, $token);
            } elseif (in_array($token, ['+', '-', '*', '/'])) {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException("Invalid formula structure.");
                }
                $num2 = array_pop($stack);
                $num1 = array_pop($stack);

                switch ($token) {
                    case '+':
                        array_push($stack, $num1 + $num2);
                        break;
                    case '-':
                        array_push($stack, $num1 - $num2);
                        break;
                    case '*':
                        array_push($stack, $num1 * $num2);
                        break;
                    case '/':
                        if ($num2 == 0) {
                            throw new InvalidArgumentException("Division by zero.");
                        }
                        array_push($stack, $num1 / $num2);
                        break;
                }
            }
        }

        if (count($stack) != 1) {
            throw new InvalidArgumentException("Invalid formula structure.");
        }

        return array_pop($stack);
    }

    public function calculate_formula($formula, $form_data, $field_settings)
    {
        $formula = $this->render_dynamic_formular_data($formula, $form_data, $field_settings);

        $postfix = $this->shunting_yard($formula);

        if (is_string($postfix)) { // Check if the returned value is an error message
            return $postfix;
        }

        $result = $this->evaluate_postfix($postfix);

        if (is_string($result)) { // Check if the returned value is an error message
            return $result;
        }

        if (isset($field_settings['roundValue']) && $field_settings['roundValue']) {
            $result = round($result);
        }

        if (isset($field_settings['hasCurrencyFormat']) && $field_settings['hasCurrencyFormat']) {
            $result = number_format($result, 2, '.', '');
        }

        return $result;
    }

    /**
     * Handle Thumbnail for different actions. 
     * Return the attachment ID
     * @param $thumbnail
     * @param $form_settings
     * @param $form_files
     * @return string
     * 
     */
    public function handle_file($file, $form_settings, $form_files, $format = 'id', $force_url_output = false)
    {
        $uploaded_file = $file;

        // Handle Thumbnail
        if ($uploaded_file && isset($form_files) && count($form_files)) {

            $uploaded_file = array_filter($form_files, function ($item) use ($uploaded_file) {
                return $item['field'] === $uploaded_file;
            });

            // Reset index of array
            $uploaded_file = array_values($uploaded_file);

            if ($uploaded_file && count($uploaded_file)) {

                $file_name = $uploaded_file[0]['name'];

                $file_path = BRICKSFORGE_UPLOADS_DIR . 'temp/' . $file_name;

                if (file_exists($file_path)) {
                    // Read the content of the temporary file
                    $file_content = file_get_contents($file_path);

                    // Use wp_upload_bits() to create a copy of the file in the WordPress uploads directory
                    $uploaded_file = wp_upload_bits($file_name, null, $file_content);

                    if (!$uploaded_file['error']) {
                        $file_path = $uploaded_file['file']; // Update the file path to the new file in the WordPress uploads directory
                        $file_type = wp_check_filetype(basename($file_path), null);

                        $attachment = array(
                            'guid'           => $uploaded_file['url'], // Use the URL of the new file in the WordPress uploads directory
                            'post_mime_type' => $file_type['type'],
                            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file_path)),
                            'post_content'   => '',
                            'post_status'    => 'inherit',
                        );

                        $attach_id = wp_insert_attachment($attachment, $file_path);

                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        $uploaded_file = $attach_id;

                        if ($format === 'url' && !class_exists('ACF') && !class_exists('RW_Meta_Box')) {
                            // Get the URL of the attachment
                            $uploaded_file = wp_get_attachment_url($attach_id);
                        }

                        if ($force_url_output) {
                            $uploaded_file = wp_get_attachment_url($attach_id);
                        }
                    }
                }
            }
        }

        return $uploaded_file;
    }
}
