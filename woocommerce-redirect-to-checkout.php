<?php
/**
 * Plugin Name: Direct Checkout for Individual WooCommerce Products
 * Plugin URI: https://infinitumform.com/
 * Description: Redirect to checkout for certain products
 * Version: 1.0.0
 * Author: Ivijan-Stefan Sipić
 * Author URI: https://infinitumform.com/
 * Requires at least: 3.8
 * Tested up to: 3.9
 * Requires PHP: 7.0
 * WC requires at least: 3.1.0
 * WC tested up to: 4.0.1
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-redirect-to-checkout
 * Domain Path: /languages
 * Network: true
 * Update URI: https://github.com/CreativForm/woocommerce-redirect-to-checkout/
 *
 * Copyright (C) 2015-2022 Ivijan-Stefan Stipic
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
 
// If someone try to called this file directly via URL, abort.
if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add plugin translations
 */
add_action('plugins_loaded', function () {
	if ( is_textdomain_loaded( 'wc-redirect-to-checkout' ) ) {
		unload_textdomain( 'wc-redirect-to-checkout' );
	}
	
	// Get locale
	$locale = apply_filters( 'wc-redirect-to-checkout-locale', get_locale(), 'wc-redirect-to-checkout' );
	
	// We need standard file
	$mofile = sprintf( '%s-%s.mo', 'wc-redirect-to-checkout', $locale );
	
	// Check first inside `/wp-content/languages/plugins`
	$domain_path = path_join( WP_LANG_DIR, 'plugins' );
	$loaded = load_textdomain( 'wc-redirect-to-checkout', path_join( $domain_path, $mofile ) );
	
	// Or inside `/wp-content/languages`
	if ( ! $loaded ) {
		$loaded = load_textdomain( 'wc-redirect-to-checkout', path_join( WP_LANG_DIR, $mofile ) );
	}
	
	// Or inside `/wp-content/plugin/woocommerce-redirect-to-checkout/languages`
	if ( ! $loaded ) {
		$domain_path = __DIR__ . DIRECTORY_SEPARATOR . 'languages';
		$loaded = load_textdomain( 'wc-redirect-to-checkout', path_join( $domain_path, $mofile ) );
		
		// Or load with only locale without prefix
		if ( ! $loaded ) {
			$loaded = load_textdomain( 'wc-redirect-to-checkout', path_join( $domain_path, "{$locale}.mo" ) );
		}

		// Or old fashion way
		if ( ! $loaded && function_exists('load_plugin_textdomain') ) {
			load_plugin_textdomain( 'wc-redirect-to-checkout', false, $domain_path );
		}
	}
}, 10);

/**
 * Add text inputs to product metabox
 */
add_action( 'woocommerce_product_options_general_product_data', function (){
    global $post;
    echo '<div class="options_group">';
    // Suggested Price
    echo woocommerce_wp_checkbox( array(
		'id' => '_wc_redirect_to_checkout',
		'label' => __( 'Redirect to checkout', 'wc-redirect-to-checkout' ) ,
		'description' => __( 'When this item is added to the cart, re-direct the customer to checkout immediately.', 'wc-redirect-to-checkout' )
	));
    echo '</div>';
} );


/**
 * Save extra meta info
 *
 * @param  WC_Product  $product
 */
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
    if ( isset( $_POST['_wc_redirect_to_checkout'] ) ) {
        $product->update_meta_data( '_wc_redirect_to_checkout', 'yes' );
    } else {
        $product->update_meta_data( '_wc_redirect_to_checkout', 'no' );
    }
} );

/**
 * Check if an item has custom field.
 *
 * @param  WC_Product  $product
 */
function wc_maybe_redirect_to_cart( $product ) {
    return wc_string_to_bool( $product instanceof WC_Product && $product->get_meta( '_wc_redirect_to_checkout', true ) );
}

/**
 * Redirect to checkout
 *
 * @param  string      $url
 * @param  WC_Product  $product
 */
add_filter( 'woocommerce_add_to_cart_redirect', function ( $url, $product ) {
    // If product is one of our special products.
    if ( wc_maybe_redirect_to_cart( $product ) ) {

        // Remove default cart message.
        wc_clear_notices();

        // Redirect to checkout.
        $url = wc_get_checkout_url();
    }
    return $url;
}, 10, 2 );


/**
 * Change add to cart text on product pages and lists
 *
 * @param  string       $text
 * @param  WC_Product   $product
 */
function _wc_redirect_to_checkout__add_to_cart_text($text, $product) {
	if ( wc_maybe_redirect_to_cart( $product ) ) {
		$text = __( 'Order Now!', 'wc-redirect-to-checkout' );
	}
	return $text;
}
add_filter( 'woocommerce_product_single_add_to_cart_text', '_wc_redirect_to_checkout__add_to_cart_text', 10, 2 ); 
add_filter( 'woocommerce_product_add_to_cart_text', '_wc_redirect_to_checkout__add_to_cart_text', 10, 2 );  


/**
 * Empty cart on the individual product.
 * It is not ideal but is only way
 *
 * @param  mixed        $cart_item_data
 * @param  integer      $product_id
 * @param  integer      $variation_id
 */
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id, $variation_id ) {
	$product = new WC_Product($product_id);
	if (
		$product 
		&& wc_maybe_redirect_to_cart( $product ) 
		&& $product->is_sold_individually() 
	) {
		WC()->cart->empty_cart();
	}
    return $cart_item_data;
}, 10, 3 );


/**
 * Force quantity to one product (test purposes)
 *
 * @param  WC_Object  $cart
 *
add_action('woocommerce_before_calculate_totals', function ( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
        return;
	
	// Get specific products
	$products = get_posts(array(
		'post_type' => 'product',
		'post_status' => 'any',
		'meta_query' => array(
			array(
				'key' => '_sold_individually',
				'value' => 'yes', 
				'compare' => '=',
			)
		),  
		'posts_per_page' => -1
	));

    // HERE below define your specific products IDs
    $specific_ids = wp_list_pluck($products, 'ID');
    $new_qty = 1; // New quantity

    // Checking cart items
	if( !empty($specific_ids) ) {
		foreach( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['data']->get_id();
			// Check for specific product IDs and change quantity
			if( in_array( $product_id, $specific_ids ) && $cart_item['quantity'] != $new_qty ){
				$cart->set_quantity( $cart_item_key, $new_qty ); // Change quantity
			}
		}
	}
}, 20, 1 );
 */


/**
 * Force individual product (test purposes)
 *
 * @param  WC_Product  $product
 *
add_filter( 'woocommerce_is_sold_individually', function ( $individually, $product ) {
	if ( $individually !== true && wc_maybe_redirect_to_cart( $product ) ) {
		$individually = true;
	}
	return $individually;
}, 9999, 2 );
 */