<?php
/**
 * Plugin Name: LeanCart Payment Gateway Fee
 * Plugin URI:  https://walkintublab.com/
 * Description: Define separate surcharges for methods such as credit card, bank transfer, and cash on delivery. The amount updates instantly when the payment method changes.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: payment-fee
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_PAYMENT_FEE_DEMO_SITE' ) ) {
	define( 'LEANCART_PAYMENT_FEE_DEMO_SITE', 'https://walkintublab.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Payment_Fee {

	const SLUG = 'payment-fee';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Payment Gateway Fee',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_PAYMENT_FEE_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'payment-fee', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Payment Gateway Fee' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'payment-fee' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'fees', __('Fees', 'payment-fee'), __('One per line: gateway_id|amount. Fixed amount or percentage (%5 or 5%); -2 for a negative fixed discount. E.g.: cod|5, bacs|-2, cod|%2, card|3%', 'payment-fee'), 'textarea' );
	}


	public static function hooks() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'fee' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'script' ) );
	}

	public static function fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
		if ( ! function_exists( 'WC' ) || ! WC()->session ) { return; }
		$method = WC()->session->get( 'chosen_payment_method' );
		if ( ! $method ) { return; }
		$title = $method;
		if ( WC()->payment_gateways ) {
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			if ( isset( $gateways[ $method ] ) && method_exists( $gateways[ $method ], 'get_title' ) ) {
				$title = $gateways[ $method ]->get_title();
			}
		}
		foreach ( explode( "\n", LeanCart_Framework::setting( 'fees' ) ) as $line ) {
			$line = trim( $line );
			if ( false === strpos( $line, '|' ) ) { continue; }
			list( $id, $amount ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( $id === $method ) {
				$percent = '%' === substr( $amount, 0, 1 ) || '%' === substr( $amount, -1 );
				if ( '%' === substr( $amount, 0, 1 ) ) { $amount = trim( substr( $amount, 1 ) ); }
				elseif ( '%' === substr( $amount, -1 ) ) { $amount = trim( substr( $amount, 0, -1 ) ); }
				if ( is_numeric( $amount ) ) {
					$fee = $percent ? (float) $cart->get_subtotal() * ( (float) $amount / 100 ) : (float) $amount;
					$cart->add_fee( sprintf( __( '%s processing fee', 'payment-fee' ), $title ), $fee, true );
				}
				break;
			}
		}
	}

	public static function script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
		LeanCart_Framework::enqueue_js( "jQuery(document).on('change', 'input[name=payment_method]', function(){ jQuery('body').trigger('update_checkout'); });" );
	}


}

LeanCart_Payment_Fee::init();

