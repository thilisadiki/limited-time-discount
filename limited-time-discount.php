<?php
/*
Plugin Name: Limited Time Discount
Description: Adds a countdown timer to the cart offering a limited-time discount.
Version: 1.1
Author: Your Name
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue scripts and styles
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

// Display the countdown timer on the cart page
add_action('woocommerce_before_cart', 'ltd_display_countdown_timer');
function ltd_display_countdown_timer() {
    // Get the timer duration from settings
    $duration_minutes = get_option('ltd_timer_duration', 15); // Default to 15 minutes
    $duration_seconds = $duration_minutes * 60;

    // Check if expiry time is already set in the session
    if ( ! WC()->session->get('ltd_expiry_time') ) {
        $expiry_time = time() + $duration_seconds;
        WC()->session->set('ltd_expiry_time', $expiry_time);
    } else {
        $expiry_time = WC()->session->get('ltd_expiry_time');
    }

    // Pass the expiry time and AJAX URL to the JavaScript file
    wp_localize_script('ltd-countdown', 'ltd_params', array(
        'expiry_time'      => $expiry_time,
        'ajax_url'         => admin_url('admin-ajax.php'),
        'expired_message'  => __('The special discount has expired.', 'ltd'),
    ));

    echo '<div id="ltd-countdown-timer">
            <p>' . __('Complete your purchase within', 'ltd') . ' <span id="ltd-timer"></span> ' . __('to get a special discount!', 'ltd') . '</p>
          </div>';
}

// Apply the discount while the timer is active
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

    // Get the discount percentage from settings
    $discount_percentage = get_option('ltd_discount_percentage', 10); // Default to 10%

    // Apply the discount
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $original_price = $product->get_regular_price();

        if ( ! $original_price ) {
            continue;
        }

        $discount_multiplier = (100 - $discount_percentage) / 100;
        $discounted_price = $original_price * $discount_multiplier;
        $product->set_price( $discounted_price );
    }
}

// Handle AJAX request to remove discount when timer expires
add_action('wp_ajax_ltd_remove_discount', 'ltd_remove_discount');
add_action('wp_ajax_nopriv_ltd_remove_discount', 'ltd_remove_discount');
function ltd_remove_discount() {
    // Clear the expiry time from the session
    WC()->session->__unset('ltd_expiry_time');

    // Recalculate totals without discount
    WC()->cart->calculate_totals();

    wp_die();
}

// Add settings page under WooCommerce menu
add_action('admin_menu', 'ltd_add_settings_page');
function ltd_add_settings_page() {
    add_submenu_page(
        'woocommerce',               // Parent slug
        'Limited Time Discount',     // Page title
        'Limited Time Discount',     // Menu title
        'manage_options',            // Capability
        'ltd-settings',              // Menu slug
        'ltd_render_settings_page'   // Callback function
    );
}

// Render the settings page
function ltd_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Limited Time Discount Settings', 'ltd'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ltd_settings_group');
            do_settings_sections('ltd-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings and add settings fields
add_action('admin_init', 'ltd_register_settings');
function ltd_register_settings() {
    // Register settings
    register_setting('ltd_settings_group', 'ltd_discount_percentage', 'absint');
    register_setting('ltd_settings_group', 'ltd_timer_duration', 'absint');

    // Add settings section
    add_settings_section(
        'ltd_main_settings',
        __('Main Settings', 'ltd'),
        'ltd_main_settings_callback',
        'ltd-settings'
    );

    // Add discount percentage field
    add_settings_field(
        'ltd_discount_percentage',
        __('Discount Percentage (%)', 'ltd'),
        'ltd_discount_percentage_field',
        'ltd-settings',
        'ltd_main_settings'
    );

    // Add timer duration field
    add_settings_field(
        'ltd_timer_duration',
        __('Timer Duration (minutes)', 'ltd'),
        'ltd_timer_duration_field',
        'ltd-settings',
        'ltd_main_settings'
    );
}

// Settings section callback
function ltd_main_settings_callback() {
    echo __('Configure the settings for the Limited Time Discount plugin.', 'ltd');
}

// Discount percentage field callback
function ltd_discount_percentage_field() {
    $discount = get_option('ltd_discount_percentage', 10); // Default to 10%
    ?>
    <input type="number" name="ltd_discount_percentage" value="<?php echo esc_attr($discount); ?>" min="1" max="100" /> %
    <?php
}

// Timer duration field callback
function ltd_timer_duration_field() {
    $duration = get_option('ltd_timer_duration', 15); // Default to 15 minutes
    ?>
    <input type="number" name="ltd_timer_duration" value="<?php echo esc_attr($duration); ?>" min="1" /> <?php _e('minutes', 'ltd'); ?>
    <?php
}
