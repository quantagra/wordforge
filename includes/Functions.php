<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Register AJAX actions
add_action('wp_ajax_bricksforge_send_mail', 'bricksforge_send_mail');
add_action('wp_ajax_nopriv_bricksforge_send_mail', 'bricksforge_send_mail');
add_action('wp_ajax_bricksforge_update_option', 'bricksforge_update_option');
add_action('wp_ajax_nopriv_bricksforge_update_option', 'bricksforge_update_option');
add_action('wp_ajax_bricksforge_delete_option', 'bricksforge_delete_option');
add_action('wp_ajax_nopriv_bricksforge_delete_option', 'bricksforge_delete_option');

function bricksforge_send_mail($template_id = null, $to = null, $subject = null, $message = null, $headers = '', $attachments = array())
{
    // If we're not ready yet, return.
    if (!did_action('wp') && !wp_doing_ajax()) {
        return;
    }

    $confirmed_ajax_call = wp_doing_ajax() && $_POST['nonce'] && $_POST['to'];

    // Check if is an ajax call
    if ($confirmed_ajax_call) {

        if (!isset($_POST['nonce'])) {
            return;
        }

        // Check the nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bricksforge_ajax')) {
            return;
        }

        $template_id = isset($_POST['template']) ? $_POST['template'] : null;
        $to          = isset($_POST['to']) ? $_POST['to'] : get_option('admin_email');
        $subject     = isset($_POST['subject']) ? $_POST['subject'] : "";
        $message     = isset($_POST['message']) ? $_POST['message'] : "";
        $attachments = isset($_POST['attachments']) ? $_POST['attachments'] : array();
    }

    // We prepend the template id to the message as ###BRFTEMPLATEID:ID###
    $message = "###BRFTEMPLATEID:{$template_id}###" . $message;

    // Prepare the email data
    $email_data = array(
        'to'          => $to,
        'subject'     => $subject,
        'message'     => $message,
        'headers'     => $headers,
        'attachments' => $attachments,
    );

    // Send the email using wp_mail function
    $result = wp_mail($email_data['to'], $email_data['subject'], $email_data['message'], $email_data['headers'], $email_data['attachments']);

    if ($confirmed_ajax_call) {
        // Return a response
        wp_send_json_success(array('message' => 'Email sent successfully'));

        wp_die();
    }

    return $result;
}

function bricksforge_update_option()
{
    // If we're not ready yet, return.
    if (!wp_doing_ajax()) {
        return;
    }

    if (!isset($_POST['nonce'])) {
        return;
    }

    // Check the nonce
    if (!wp_verify_nonce($_POST['nonce'], 'bricksforge_ajax')) {
        return;
    }

    $option_name  = isset($_POST['option_name']) ? $_POST['option_name'] : false;
    $option_value = isset($_POST['option_value']) ? $_POST['option_value'] : false;

    try {
        if ($option_name && $option_value) {
            $result = update_option($option_name, $option_value);

            // Return a response
            wp_send_json_success(array('message' => get_option($option_name)));
        }
    } catch (Exception $e) {
        // Log the error message
        error_log($e->getMessage());
    }

    wp_die();
}

function bricksforge_delete_option()
{
    // If we're not ready yet, return.
    if (!wp_doing_ajax()) {
        return;
    }

    if (!isset($_POST['nonce'])) {
        return;
    }

    // Check the nonce
    if (!wp_verify_nonce($_POST['nonce'], 'bricksforge_ajax')) {
        return;
    }

    $option_name = isset($_POST['option_name']) ? $_POST['option_name'] : false;

    try {
        if ($option_name) {
            $result = delete_option($option_name);

            // Return a response
            wp_send_json_success(array('message' => "Option deleted"));
        }
    } catch (Exception $e) {
        // Log the error message
        error_log($e->getMessage());
    }

    wp_die();
}
