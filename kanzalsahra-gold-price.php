<?php
/*
Plugin Name: Kanzalsahra Gold Price
Description: Fetches live gold prices and updates the custom field for WooCommerce products.
Version: 1.0
Author: Mohamed Elomaeer
*/

function fetch_gold_price() {
    $api_key = 'YDZ5FK5DFWBE27L35UPF259L35UPF';
    $currency = 'SAR';
    $unit = 'g';
    $url = "https://api.metals.dev/v1/latest?api_key={$api_key}&currency={$currency}&unit={$unit}";

    $response = wp_remote_get($url);

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data) && isset($data['metals']['gold'])) {
            $gold_price_per_gram = $data['metals']['gold'];

            // Save the gold price in an option
            update_option('last_gold_price_per_gram', $gold_price_per_gram);

            // Trigger the product price update
            update_product_prices($gold_price_per_gram);
        }
    }
}

function update_product_prices($gold_price_per_gram = null) {
    // Retrieve the gold price from the admin settings if not passed directly
    if ($gold_price_per_gram === null) {
        $gold_price_per_gram = get_option('last_gold_price_per_gram');
    }

    // Get all products, including those without a weight
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $query = new WP_Query($args);

    while ($query->have_posts()) {
        $query->the_post();
        $product_id = get_the_ID();
        $weight = get_post_meta($product_id, '_weight', true);
        $manufacturing_value = get_post_meta($product_id, 'the_manufacturing_value_of_gold', true);

        // Ensure manufacturing_value is treated as a number
        $manufacturing_value = is_numeric($manufacturing_value) ? floatval($manufacturing_value) : 0;

        // Retrieve the current price of the product
        $current_price = get_post_meta($product_id, '_price', true);

        // If the product has weight, calculate price based on gold price and weight
        if ($weight && is_numeric($weight) && $gold_price_per_gram) {
            $new_price = ($gold_price_per_gram * $weight) + $manufacturing_value;
        } else {
            // For products without weight, use the existing price
            $new_price = is_numeric($current_price) ? floatval($current_price) : 0;
        }

        // Apply the 2.5% increase to the price
        $new_price_with_increase = $new_price * 1.025;

        // Update the product's price in WooCommerce
        update_post_meta($product_id, '_price', $new_price_with_increase);
        update_post_meta($product_id, '_regular_price', $new_price_with_increase);

        // Logging for debugging purposes (optional)
        error_log("Updated Product ID: $product_id, New Price: $new_price_with_increase");
    }

    wp_reset_postdata();
}

function add_gold_price_admin_menu() {
    add_menu_page(
        'Gold Price',
        'Gold Price',
        'manage_options',
        'gold-price',
        'gold_price_admin_page',
        'dashicons-admin-site',
        20
    );
}
add_action('admin_menu', 'add_gold_price_admin_menu');

function gold_price_admin_page() {
    if (isset($_POST['update_gold_price'])) {
        fetch_gold_price();
        echo '<div class="updated"><p>Gold price updated and product prices recalculated!</p></div>';
    }

    $gold_price = get_option('last_gold_price_per_gram');
    echo '<div class="wrap">';
    echo '<h1>Gold Price</h1>';
    if ($gold_price) {
        echo '<table class="form-table" style="width: 50%; border-collapse: collapse;">';
        echo '<tr style="background-color: #f9f9f9;">';
        echo '<th style="padding: 10px; border: 1px solid #ddd;">Current Gold Price (SAR/gram):</th>';
        echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong style="color: #FF9900; font-size: 1.2em;">' . esc_html($gold_price) . '</strong></td>';
        echo '</tr>';
        echo '</table>';
    } else {
        echo '<p>Gold price not available.</p>';
    }
    echo '<form method="post" action="" style="margin-top: 20px;">';
    echo '<input type="hidden" name="update_gold_price" value="1">';
    echo '<button type="submit" class="button button-primary" style="background-color: #FF9900; border-color: #FF9900;">Update Price</button>';
    echo '</form>';
    echo '</div>';
}

function schedule_gold_price_cron_job() {
    if (!wp_next_scheduled('fetch_gold_price_cron_job')) {
        wp_schedule_event(time(), 'ten_minutes', 'fetch_gold_price_cron_job');
    }
}
add_action('wp', 'schedule_gold_price_cron_job');

function add_custom_cron_intervals($schedules) {
    $schedules['ten_minutes'] = array(
        'interval' => 600,
        'display'  => __('Every 10 Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_custom_cron_intervals');

add_action('fetch_gold_price_cron_job', 'fetch_gold_price');

function deactivate_gold_price_cron_job() {
    $timestamp = wp_next_scheduled('fetch_gold_price_cron_job');
    wp_unschedule_event($timestamp, 'fetch_gold_price_cron_job');
}
register_deactivation_hook(__FILE__, 'deactivate_gold_price_cron_job');















