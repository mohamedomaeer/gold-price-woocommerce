<?php

/**
* Plugin Name: Kanz Alsahra Gold Price
* Plugin URI:        https://momaeer.com/
* Description:       Add a global price per 1 gram of Gold and then use the weight of each product to automatically calculate its price based on this rate.
* Version:           1.1
* Author:            Mohamed Elomaeer
* Author URI:        https://momaeer.com/
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       my-textdomain
* Domain Path:       /languages
*/


function get_gold_rate() {
    $cache_file = 'path/to/your/cache/file.txt';
    $cache_time = 600; // 10 minutes

    // Check if cache file exists and is within the cache time
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $response = file_get_contents($cache_file);
    } else {
        // If cache does not exist or is stale, make a new API request
        $api_url = 'https://metals-api.com/api/latest?access_key=your_access_key&base=SAR&symbols=XAU';
        $response = file_get_contents($api_url);

        // Save response to cache file
        file_put_contents($cache_file, $response);
    }

    return json_decode($response, true);
}

function update_product_prices() {
    $gold_rate_data = get_gold_rate();
    if ($gold_rate_data['success']) {
        $gold_rate = $gold_rate_data['rates']['XAU'];
        
        // Loop through products and update prices
        $products = get_products(); // Your function to get products
        foreach ($products as $product) {
            $weight = get_post_meta($product->ID, 'weight', true);
            $manufacturing_value = get_post_meta($product->ID, 'the_manufacturing_value_of_gold', true);
            if ($weight && $gold_rate) {
                $new_price = ($gold_rate * $weight) + $manufacturing_value;
                update_post_meta($product->ID, '_price', $new_price);
                // Log the updated price
                error_log("Updated Product ID: {$product->ID}, New Price: {$new_price}");
            }
        }
    }
}
