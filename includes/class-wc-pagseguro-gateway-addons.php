<?php
/**
 * WooCommerce PagSeguro Gateway Addons class
 *
 * @package WooCommerce_PagSeguro/Classes/Gateway
 * @version 2.11.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_PagSeguro_Gateway_Addons class.
 */
class WC_PagSeguro_Gateway_Addons extends WC_PagSeguro_Gateway {

	/**
	 * Class Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		}
	}

	/**
	 * Process the payment
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( $this->is_subscription( $order_id ) ) {
			return parent::process_payment( $order_id );
		}
	}

	public function process_subscription_payment( $order = '', $amount = 0 ) {}


	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		// Define some callbacks if the first attempt fails.
		$retry_callbacks = array(
			'remove_order_source_before_retry',
			'remove_order_customer_before_retry',
		);

		while ( 1 ) {
			$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

			if ( is_wp_error( $response ) ) {
				if ( 0 === sizeof( $retry_callbacks ) ) {
					$renewal_order->update_status( 'failed', sprintf( __( 'PagSeguro Transaction Failed (%s)', 'woocommerce-pagseguro' ), $response->get_error_message() ) );
					break;
				} else {
					$retry_callback = array_shift( $retry_callbacks );
					call_user_func( array( $this, $retry_callback ), $renewal_order );
				}
			} else {
				// Successful
				break;
			}
		}
	}

	/**
	 * It answers the question, is $order_id a subscription?
	 * 
	 * @param int $order_id Subscription Order ID
	 * 
	 * @return boolean true|false
	 */
	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}
}
