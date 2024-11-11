<?php
/*
Plugin Name: Limited Time Discount
Description: Adds a countdown timer to the cart offering a limited-time discount.
Version: 1.0
Author: Jason Sadiki
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_enqueue_scripts', 'ltd_enqueue_scripts');
function ltd_enqueue_scripts() {
    if ( is_cart() ) {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ltd-countdown',
            plugin_dir_url(__FILE__) . 'js/countdown.js',
            array('jquery'),
            '1.0',
            true
        );
        wp_enqueue_style(
            'ltd-styles',
            plugin_dir_url(__FILE__) . 'css/styles.css'
        );
    }
}

add_action('woocommerce_before_cart', 'ltd_display_countdown_timer');
function ltd_display_countdown_timer() {
    // Set the timer duration in seconds (e.g., 15 minutes)
    $duration = 15 * 60;

    // Check if expiry time is already set in the session
    if ( ! WC()->session->get('ltd_expiry_time') ) {
        $expiry_time = time() + $duration;
        WC()->session->set('ltd_expiry_time', $expiry_time);
    } else {
        $expiry_time = WC()->session->get('ltd_expiry_time');
    }

    // Pass the expiry time and AJAX URL to the JavaScript file
    wp_localize_script('ltd-countdown', 'ltd_params', array(
        'expiry_time' => $expiry_time,
        'ajax_url'    => admin_url('admin-ajax.php'),
        'expired_message' => __('The special discount has expired.', 'ltd'),
    ));

    echo '<div id="ltd-countdown-timer">
            <p>' . __('Complete your purchase within', 'ltd') . ' <span id="ltd-timer"></span> ' . __('to get a special discount!', 'ltd') . '</p>
          </div>';
}

add_action('woocommerce_before_calculate_totals', 'ltd_apply_discount');
function ltd_apply_discount( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') )
        return;

    // Check if the timer is still active
    $expiry_time = WC()->session->get('ltd_expiry_time');
    if ( ! $expiry_time || time() > $expiry_time ) {
        // Timer expired or not set, do not apply discount
        return;
    }

    // Apply a 10% discount
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $original_price = $product->get_regular_price();

        // Skip if original price is not set
        if ( ! $original_price ) {
            continue;
        }

        $discounted_price = $original_price * 0.9; // 10% discount
        $product->set_price( $discounted_price );
    }
}

add_action('wp_ajax_ltd_remove_discount', 'ltd_remove_discount');
add_action('wp_ajax_nopriv_ltd_remove_discount', 'ltd_remove_discount');
function ltd_remove_discount() {
    // Clear the expiry time from the session
    WC()->session->__unset('ltd_expiry_time');

    // Recalculate totals without discount
    WC()->cart->calculate_totals();

    wp_die();
}
