<?php
/**
 * WPForms_EPFL_Payonline
 *
 * WPForms EPFL Payonline is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPForms EPFL Payonline is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WPForms EPFL Payonline.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    WPFormsEPFLPayonline
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2019, EPFL
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EPFL Payonline integration.
 *
 * @package WPFormsEPFLPayonline
 */
class WPForms_EPFL_Payonline extends WPForms_Payment {

	const WPFEP_DEBUG = true;

	/**
	 * Initialize.
	 */
	public function init() {

		$this->wp_tested_version = WP_LATEST_VERSION_WPFORMS_EPFL_PAYONLINE;
		$this->wp_min_version    = WP_MIN_VERSION_WPFORMS_EPFL_PAYONLINE;
		$this->version           = WPFORMS_EPFL_PAYONLINE_VERSION;
		$this->plugin_name       = WPFORMS_EPFL_PAYONLINE_NAME;
		$this->name              = 'EPFL Payonline';
		$this->slug              = 'epfl_payonline';
		$this->priority          = 10;
		$this->icon              = plugins_url( 'assets/images/EPFL-Payonline-trans.png', __FILE__ );
		$this->cache_seconds     = 3600; // 43200 = 12 hours cache

		add_action( 'admin_enqueue_scripts', array( $this, 'load_custom_wp_admin_style' ) );

		add_action( 'wpforms_process_complete', array( $this, 'process_payment_to_wordline_saferpay' ), 20, 4 );
		add_action( 'init', array( $this, 'process_return_from_epfl_payonline' ) );

		// (see wpforms/pro/includes/payments/functions.php)
		add_filter( 'wpforms_currencies', array( $this, 'filter_currencies' ), 10, 1 );
		add_filter( 'wpforms_has_payment_gateway', array( $this, 'filter_has_payment_gateway' ), 10, 1 );

		// Define filters for payment details (see wpforms/pro/includes/admin/entries/class-entries-single.php).
		add_action( 'wpforms_entry_payment_sidebar_actions', array( $this, 'action_entry_payment_sidebar' ), 10, 2 );
		add_filter( 'wpforms_entry_details_payment_gateway', array( $this, 'filter_entry_details_payment_gateway' ), 10, 3 );
		add_filter( 'wpforms_entry_details_payment_transaction', array( $this, 'filter_entry_details_payment_transaction' ), 10, 3 );

		// Redefine notifications recipient address if set to blog's admin email ({admin_email} default of WPForms).
		add_filter( 'wpforms_entry_email_atts', array( $this, 'redefine_notification_recipents_addresses' ), 10, 5 );

		// Add additional link to the plugin row.
		add_filter( 'plugin_row_meta', array( $this, 'add_links_to_plugin_row' ), 10, 4 );

		// Plugin updater (https://rudrastyh.com/wordpress/self-hosted-plugin-update.html).
		add_filter( 'site_transient_update_plugins', array( $this, 'wpforms_epfl_payonline_push_update' ), 20, 1 );
		add_action( 'upgrader_process_complete', array( $this, 'wpforms_epfl_payonline_after_update' ), 10, 2 );

		// Plugin details.
		add_filter( 'plugins_api', array( $this, 'wpforms_epfl_payonline_plugin_info' ), 20, 3 );

		// Add payonline.epfl.ch to safe redirect hosts.
		add_filter( 'allowed_redirect_hosts', array( $this, 'wpforms_add_payonline_to_allowed_redirect' ) );
	}

	/**
	 * Export var to error log
	 *
	 * @param unknown $var ...
	 * @param String  $title ...
	 */
	private function log( $var, $title = null ) {
		$log = '-- WPFORMS EPFL Payonline ';
		if ( $title ) {
			$log .= "[$title] ";
		}
		$log  = str_pad( $log, 80, '-' );
		$log .= "\n" . var_export( $var, true );
		error_log( $log );
	}

	/**
	 * Export var to error log if self::WPFEP_DEBUG is true
	 *
	 * @param unknown $var ...
	 * @param String  $title ...
	 */
	private function debug( $var, $title = null ) {
		if ( self::WPFEP_DEBUG ) {
			$this->log( $var, $title );
		}
	}

	/**
	 * Load custom CSS to hide Paypal and Stripe addon
	 *
	 * @param Sting $hook ...
	 */
	public function load_custom_wp_admin_style( $hook ) {
		// no need to load this CSS everywhere.
		if ( 'wpforms_page_wpforms-builder' !== $hook ) {
			return; }
		wp_register_style( 'epfl-payonline', plugins_url( 'assets/css/epfl-payonline.css', __FILE__ ), false, '1.0.0' );
		wp_enqueue_style( 'epfl-payonline' );
	}

	/**
	 * Get value from fields type
	 *
	 * E.g. error_log( var_export($this->getFieldsFromType( $entry->fields, 'email'), true) );
	 *
	 * @param Sting $fields ...
	 * @param Sting $type ...
	 *
	 * @return Array $wanted_fileds
	 */
	private function getFieldsFromType( $fields, $type ) {
		$wanted_arrays = $this->getArraysFromType( $fields, $type );
		$wanted_fileds = array();
		foreach ( $wanted_arrays as $key => $value ) {
			$wanted_fileds[] = $wanted_arrays[ $key ]['value'];
		}
		return $wanted_fileds;
	}

	/**
	 * Get Array from fields type
	 *
	 * E.g. error_log( var_export($this->getArraysFromType( $entry->fields, 'email'), true) );
	 *
	 * @param Sting $fields ...
	 * @param Sting $type ...
	 *
	 * @return Array $wanted_arrays
	 */
	private function getArraysFromType( $fields, $type ) {
		// Test if $fields is JSON.
		if ( ( is_string( $fields ) && ( is_object( json_decode( $fields ) ) || is_array( json_decode( $fields ) ) ) ) ) {
			$fields = json_decode( $fields, true );
		}
		$wanted_arrays = array();
		foreach ( $fields as $key => $value ) {
			if ( $value['type'] === $type ) {
				$wanted_arrays[] = $fields[ $key ];
			}
		}
		return $wanted_arrays;
	}

	/**
	 * Add payonline to allowed redirect hosts
	 *
	 * Note: use `$hosts[] = 'epfl.ch';` for all EPFL
	 *
	 * @param Array $hosts The list of allowed hosts.
	 */
	public function wpforms_add_payonline_to_allowed_redirect( $hosts ) {
		$hosts[] = 'payonline.epfl.ch';
		return $hosts;
	}

	/**
	 * When user submit a form with the EPFL Wordline SaferPay payment activated,
	 * it is sent to saferpay/api/initialize to create the payment link according
	 * to the Terminal, CustomerID, etc.
	 *
	 * @param Array $fields ...
	 * @param Array $entry ...
	 * @param Array $form_data ...
	 * @param Int   $entry_id ...
	 */
	public function process_payment_to_wordline_saferpay( $fields, $entry, $form_data, $entry_id ) {

		// Check an entry was created and passed.
		if ( empty( $entry_id ) ) {
			return;
		}

		// Debugging submission to EPFL Payonline.
		$this->debug( $form_data['payments'], '2payonline: $form_data[payments]' );
		$this->debug( $fields, '2payonline: $fields' );
		$this->debug( $entry, '2payonline: $entry' );

		// Check if payment method exists.
		if ( empty( $form_data['payments'][ $this->slug ] ) ) {
			$this->log( $form_data['payments'], 'No payment method found' );
			return;
		}

		// Check required payment settings.
		$payment_settings = $form_data['payments'][ $this->slug ];
		$this->debug( $payment_settings, "PAYMENT SETTINGS" );

		if (
			empty( $payment_settings['enable'] ) ||
			empty( $payment_settings['email'] ) ||
			empty( $payment_settings['saferpay_customer_id'] ) ||
			empty( $payment_settings['saferpay_api_url'] ) ||
			empty( $payment_settings['saferpay_api_username'] ) ||
			empty( $payment_settings['saferpay_api_password'] ) ||
			empty( $payment_settings['saferpay_terminal_id'] ) ||
			( '1' !== $payment_settings['enable'] )
		) {
			$this->log( $payment_settings, 'Some EPFL Wordline Saferpay settings are missing' );
			return;
		}

		// Check that, despite how the form is configured, the form and
		// entry actually contain payment fields, otherwise no need to proceed.
		$form_has_payments  = wpforms_has_payment( 'form', $form_data );
		$entry_has_paymemts = wpforms_has_payment( 'entry', $fields );
		if ( ! $form_has_payments || ! $entry_has_paymemts ) {
			$this->log( $fields, 'No payment fields' );
			$error = esc_html__( 'EPFL Payonline Payment stopped, missing payment fields', 'wpforms-epfl-payonline' );
		}

		// Check total charge amount.
		$amount = wpforms_get_total_payment( $fields );
		if ( empty( $amount ) || wpforms_sanitize_amount( 0 ) === $amount ) {
			$this->log( $fields, 'No total charge' );
			$error = esc_html__( 'EPFL Payonline Payment stopped, invalid/empty amount', 'wpforms-epfl-payonline' );
		}

		if ( $error ) {
			$this->log( $error, 'Errors found' );
			return;
		}



		// Setup various vars.
		// $items       = wpforms_get_payment_items( $fields );
		// $redirect    = 'https://payonline.epfl.ch/cgi-bin/commande/?';
		// $id_inst     = empty( $payment_settings['id_inst'] ) ? '1234567890' : $payment_settings['id_inst']; // 1234567890 is the test instance
		// $cancel_url  = ! empty( $payment_settings['cancel_url'] ) ? esc_url_raw( $payment_settings['cancel_url'] ) : home_url();
		// $transaction = ! empty( $payment_settings['transaction'] ) && 'donation' === $payment_settings['transaction'] ? '_donations' : '_cart';
		// 
		// $payonline_addr  = $this->getArraysFromType( $fields, 'address' )[0]['address1'];
		// $payonline_addr .= ( trim( $this->getArraysFromType( $fields, 'address' )[0]['address2'] ) === '' ) ? '' : ' / ' . $this->getArraysFromType( $fields, 'address' )[0]['address2'];
		// Setup EPFL Payonline arguments.
		// $payonline_args = array(
		// 	'id_inst'        => $id_inst,
		// 	'Currency'       => strtoupper( wpforms_setting( 'currency', 'USD' ) ),
		// 	// @TODO: Get real entry
		// 	'LastName'       => $this->getArraysFromType( $fields, 'name' )[0]['last'],
		// 	'FirstName'      => $this->getArraysFromType( $fields, 'name' )[0]['first'],
		// 	'Addr'           => $payonline_addr,
		// 	'ZipCode'        => $this->getArraysFromType( $fields, 'address' )[0]['postal'],
		// 	'City'           => $this->getArraysFromType( $fields, 'address' )[0]['city'],
		// 	'Country'        => $this->getArraysFromType( $fields, 'address' )[0]['country'],
		// 	'Email'          => $this->getFieldsFromType( $fields, 'email' )[0],
		// 	'id_transact'    => absint( $entry_id ),
		// 	// 'URL'           => "http://localhost:8080/index.php?commande=OK",
		// 	// 'url'           => "http://localhost:8080/index.php?commande=OK",
		// 	'Total'          => 0, // defined below...
		// 	'wpforms_return' => base64_encode( $query_args ), // Test for return var
		// 	// WPForms default...
		// 	'bn'             => 'WPForms_SP',
		// 	'business'       => trim( $payment_settings['email'] ),
		// 	'cancel_return'  => $cancel_url,
		// 	'cbt'            => get_bloginfo( 'name' ),
		// 	'charset'        => get_bloginfo( 'charset' ),
		// 	'cmd'            => $transaction,
		// 	'currency_code'  => strtoupper( wpforms_setting( 'currency', 'USD' ) ),
		// 	'custom'         => absint( $form_data['id'] ),
		// 	'invoice'        => absint( $entry_id ),
		// 	'no_note'        => isset( $payment_settings['note'] ) ? absint( $payment_settings['note'] ) : null,
		// 	'no_shipping'    => isset( $payment_settings['shipping'] ) ? absint( $payment_settings['shipping'] ) : null,
		// 	'notify_url'     => add_query_arg( 'wpforms-listener', '', home_url( '/' ) ),
		// 	'return'         => $return_url,
		// 	'rm'             => '2',
		// 	'tax'            => 0,
		// 	'upload'         => '1',
		// );

		//$this->debug( $payonline_args, 'payonline_args' );
		//$payonline_args['Total'] = number_format( $payonline_args['Total'], 2, '.', '' );
		// Last change to filter args.
		$payonline_args = apply_filters( 'wpforms_payonline_redirect_args', $payonline_args, $fields, $form_data, $entry_id );

		#
		# INITIALIZE WORDLINE PAYMENT
		#
		$payment_data = array(
			'amount'    => $amount,
			'currency'  => strtolower( wpforms_setting( 'currency', 'CHF' )),
			'FirstName' => $this->getArraysFromType( $fields, 'name' )[0]['first'],
			'LastName'  => $this->getArraysFromType( $fields, 'name' )[0]['last'],
			'Email'     => $this->getFieldsFromType( $fields, 'email' )[0]
		);
		$wpforms_data = array(
			"items" => wpforms_get_payment_items( $fields ),
			"entry_id" => absint( $entry_id ),
			"blog_info" => get_bloginfo( 'name' )
		);

		$payment = new SaferpayPayment(/*settings*/$payment_settings, /*data, total and user info*/$payment_data, $wpforms_data);
		$payment_init_result = $payment->paymentPageInitialize();
		
		$this->debug( $payment_init_result->RedirectUrl, 'preparing redirection to saferpay' );


		// Build query.
		// $redirect .= http_build_query( $payonline_args );
		// $redirect  = str_replace( '&amp;', '&', $redirect );

		// Redirect to EPFL Payonline.
		if ( wp_http_validate_url($payment_init_result->RedirectUrl) ) {
			// Update entry to include payment details.
			$entry_data = array(
				'status' => 'pending',
				'type'   => 'payment',
				'meta'   => wp_json_encode(
					array(
						'payment_type'      => $this->slug,
						'payment_recipient' => sanitize_email( trim( $payment_settings['email'] ) ), // ????
						'payment_total'     => $amount,
						'payment_currency'  => strtolower( wpforms_setting( 'currency', 'CHF' ) ),
						'wordline_specifics' => $payment_init_result,
						//'payment_saferpay'  => $payment_init_result,
						// 'payment_mode'      => esc_html( $payment_settings['mode'] ),
					)
				)
			);
			wpforms()->entry->update( $entry_id, $entry_data, '', '', array( 'cap' => false ) );
			$this->debug( $payment_init_result->RedirectUrl, 'redirecting to saferpay' );
			wp_redirect( $payment_init_result->RedirectUrl );
			exit;
		} else {
			$this->log( $payment_init_result->RedirectUrl, 'Error redirecting to saferpay' );
			exit;
		}

		/*
			NOTE: from here there is now way to know what happens with the payment.
			      The buyer might stop the process, or go though it completly.
		*/
	}


	/**
	 * Verify SSHA hash
	 *
	 * See https://www.openldap.org/faq/data/cache/347.html
	 * and https://github.com/anhofmann/php-ssha/blob/master/ssha.php
	 *
	 * @param string $token ...
	 * @param string $input_hash ($id_transact . ':' . $id_inst).
	 * @return boolean
	 */
	private function ssha_password_verify( $token, $input_hash ) {
		list($salt, $hash) = explode( ':', $token );
		$ohash             = base64_decode( substr( $hash, 6 ) );
		$osalt             = substr( $ohash, 20 );
		$ohash             = substr( $ohash, 0, 20 );
		$nhash             = pack( 'H*', sha1( $input_hash . $osalt ) );
		if ( $ohash === $nhash ) {
			$this->debug( 'ssha_password_verify (' . $token . ' - ' . $input_hash . '): success', 'ssha_password_verify' );
			return true;
		} else {
			$this->log( 'ssha_password_verify (' . $token . ' - ' . $input_hash . '): failed', 'ssha_password_verify' );
			return false;
		}
	}

	/**
	 * Handle the POSTed data from payonline
	 *
	 * See https://www.openldap.org/faq/data/cache/347.html
	 * and https://github.com/anhofmann/php-ssha/blob/master/ssha.php
	 *
	 * @return void
	 */
	public function process_return_from_epfl_payonline() {
		// Anything like wp_url/index.php?EPFLPayonline.
		if ( ! isset( $_GET['EPFLPayonline'] ) || ! isset( $_GET['entry_id'] ) ) {
			return;
		} else {
			$this->debug( "process_return_from_epfl_payonline: Get hit on '/?EPFLPayonline' for  " . $_GET['entry_id'] );
		}
		$entry_id = absint( $_GET['entry_id'] );
		// Verify that the returned hash is legit.
		// if ( ! $this->ssha_password_verify( $data['token'], $data['id_transact'] . ':' . $data['id_inst'] ) ) {
		// 	$this->log( 'Error: Hash can not be verified', 'process_return_from_epfl_payonline' );
		// 	return;
		// }

		// TODO: do something clever here.
		$entry = wpforms()->entry->get( $entry_id );
		$this->debug( $entry_id, "Trying to get info for..." );

		$form_data = wpforms()->form->get(
			$entry->form_id,
			array(
				'content_only' => true,
			)
		);

		$this->debug( $form_data, "Trying to get info for..." );
		$payment_settings = $form_data['payments'][ $this->slug ];

		// If payment or form doesn't exist, bail.
		if ( empty( $entry ) || empty( $form_data ) ) {
			$this->log( 'Error: Missing $payment or $form_data in process_return_from_epfl_payonline()', 'process_return_from_epfl_payonline' );
			return;
		}

		$entry_meta = json_decode( $entry->meta, true );
		$this->debug( $entry_meta, "entry_meta" );
		$this->debug( $entry_meta['wordline_specifics'], "entry_meta->wordline_specifics" );
		$this->debug( $entry_meta['wordline_specifics']['Token'], "entry_meta->wordline_specifics->token" );

		// Verify payment recipient emails match.
		// if ( empty( $form_data['payments'][ $this->slug ]['email'] ) || strtolower( $data['business'] ) !== strtolower( trim( $form_data['payments'][ $this->slug ]['email'] ) ) ) {
		// 	$error = esc_html__( 'Payment failed: recipient emails do not match', 'wpforms-epfl-payonline' );
		// }
		// if ( 0 === $payment_status ) {
		// 	$error = esc_html__( 'Payment failed: Payonline returned 0', 'wpforms-epfl-payonline' );
		// }

		$payment = new SaferpayPayment(/*settings*/$payment_settings, /*data, total and user info*/null, null);
		$assert = $payment->paymentPageAssert($entry_meta['wordline_specifics']['Token'], $entry_meta['wordline_specifics']['ResponseHeader']['RequestId']);

		if ( $assert && $assert->Transaction->Status !== 'AUTHORIZED' && $assert->Transaction->Status !== 'CAPTURED' ) { // && $assert->Transaction->Type === 'PAYMENT'
			$this->debug( $assert, "ASSERT" );
			$this->debug( $assert->Transaction->Status, "ASSERT" ); // AUTHORIZED
			$this->debug( $assert->Transaction->Type, "ASSERT" ); // PAYMENT

			// THE PAYMENT HAS FAILED
			wpforms_log(
				esc_html__( 'EPFL Wordline Return Error', 'wpforms-epfl-payonline' ),
				sprintf( 'Error - Wordline data: %s', '<pre>' . var_export( $assert, true ) . '</pre>' ),
				array(
					'parent'  => $entry_id,
					'type'    => array( 'error', 'payment' ),
					'form_id' => $entry->form_id,
				)
			);
			$array_assert = (array) $assert; // was an object array
			$entry_meta['wordline_assert'] = $array_assert;
			$entry_data = array(
				'status' => 'failed',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
			wpforms()->entry->update( $entry_id, $entry_data, '', '', array( 'cap' => false ) );
			// $entry = wpforms()->entry->get( $entry_id );
			// $entry_meta = json_decode( $entry->meta, true );
			// $this->debug( $entry_meta, "XXXentry_meta" );
			return;
		}

		if ( $assert && $assert->Transaction->Type === 'PAYMENT' && $assert->Transaction->Status === 'AUTHORIZED' ) {
			// Completed payment.
			// $entry_meta['payment_id_inst']     = $data['id_inst'];      // Payonline instance ID.
			// $entry_meta['payment_id_trans']    = $data['id_trans'];     // Payonline transaction ID.
			// $entry_meta['payment_id_transact'] = $data['id_transact'];  // WPForms EPFL Payonline transaction ID.
			// $entry_meta['payment_transaction'] = $data['PaymentID'];    // PostFinance/Payonline transaction ID.
			// $entry_meta['payment_paymode']     = $data['paymode'];      // Master Card, etc...
			// $entry_meta['payonline']           = $data;                 // Save the returned value by payonline.
			$this->log( $assert, 'wordline assert success' );
			// Set the payment status to completed.
			$array_assert = (array) $assert; // was an object array
			$entry_meta['wordline_assert'] = $array_assert;
			$entry_data = array(
				'status' => 'authorized',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
		} else if ( $assert && $assert->Transaction->Type === 'PAYMENT' && $assert->Transaction->Status === 'CAPTURED' ) {
			$this->log( $assert, 'wordline assert (captured) success' );
			// Set the payment status to completed.
			$array_assert = (array) $assert; // was an object array
			$entry_meta['wordline_assert'] = $array_assert;
			$entry_data = array(
				'status' => 'completed (captured)',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
			return;
		}
		
		// CAPTURE PAYMENT — actual money
		
		
		$capture = $payment->paymentCapture($assert->Transaction->Id, $entry_meta['wordline_specifics']['ResponseHeader']['RequestId']);
		$this->debug( $capture, "CAPTURE" );

		if ( $capture && $capture->Status !== 'CAPTURED' ) { 
			// THE PAYMENT HAS FAILED
			wpforms_log(
				esc_html__( 'EPFL Wordline capture Error', 'wpforms-epfl-payonline' ),
				sprintf( 'Error - Wordline data: %s', '<pre>' . var_export( $capture, true ) . '</pre>' ),
				array(
					'parent'  => $entry_id,
					'type'    => array( 'error', 'payment' ),
					'form_id' => $entry->form_id,
				)
			);
			$array_capture = (array) $capture; // was an object array
			$entry_meta['wordline_capture'] = $array_capture;
			$entry_data = array(
				'status' => 'capture failed',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
			wpforms()->entry->update( $entry_id, $entry_data, '', '', array( 'cap' => false ) );
			// $entry = wpforms()->entry->get( $entry_id );
			// $entry_meta = json_decode( $entry->meta, true );
			// $this->debug( $entry_meta, "XXXentry_meta" );
			return;
		}

		// TODO check $capture->ResponseHeader->RequestId
		if ( $capture && $capture->Status === 'CAPTURED' ) {

			$this->log( $capture, 'wordline capture success' );
			// Set the payment status to completed.
			$array_capture = (array) $capture; // was an object array
			$entry_meta['wordline_capture'] = $array_capture;
			$entry_data = array(
				'status' => 'completed',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
		}
/*
			// Send email to benificiary (payment proof).
			// translators: %1$s blog's name, %2$s form's name
			$email['subject']        = sprintf( esc_html__( '[%1$s] Payment confirmation for "%2$s"', 'wpforms-epfl-payonline' ), get_bloginfo( 'name' ), $form_data['settings']['form_title'] );
			$email['address']        = $this->getFieldsFromType( $entry->fields, 'email' );
			$email['address']        = array_map( 'sanitize_email', $email['address'] );
			$email['sender_address'] = 'noreply@epfl.ch'; // TODO: improve it ! Check https://it.epfl.ch/kb_view_customer.do?sysparm_article=KB0013524  for EPFL SMTP detail.
			$email['sender_name']    = get_bloginfo( 'name' );
			$email['replyto']        = sanitize_email( trim( $form_data['payments'][ $this->slug ]['email'] ) );
			$form_title              = $form_data['settings']['form_title'];
			$email['message']        = "<h1>$form_title</h1>\n\n";
			// @TODO: Template this
			$email['message'] .= '<p>' . __( 'Thanks for your payment!', 'wpforms-epfl-payonline' ) . '</p>';
			$email['message'] .= '<p>' . __( 'Please find below your order details.', 'wpforms-epfl-payonline' ) . '</p>';
			// @TODO learn how to use custom notification for that... ! empty( $notification['message'] ) ? $notification['message'] : '{all_fields}'; 
			$email['message'] .= '{all_fields}';

			// Create new email.
			$emails = new WPForms_WP_Emails();
			$emails->__set( 'form_data', $form_data );
			$emails->__set( 'fields', wpforms_decode( $entry->fields ) );
			$emails->__set( 'entry_id', $entry_id );
			$emails->__set( 'from_name', $email['sender_name'] );
			$emails->__set( 'from_address', $email['sender_address'] );
			$emails->__set( 'reply_to', $email['replyto'] );

			// Go.
			foreach ( $email['address'] as $address ) {
				$emails->send( trim( $address ), $email['subject'], $email['message'] );
			}
			// Send a copy to WPForms EPFL Payonline notification email.
			$emails->send( trim( $form_data['payments'][ $this->slug ]['email'] ), __( '[COPY]', 'wpforms-epfl-payonline' ) . $email['subject'], $email['message'] );
*/

		// Just in case something else is needed...
		do_action( 'wpforms_epfl_payonline_process_complete', wpforms_decode( $entry->fields ), $form_data, $entry_id, $capture );

		// Debug process_return_from_epfl_payonline.
		// $this->debug( $_POST, 'process_return_from_epfl_payonline: POST:' );
		// $this->debug( $_GET, 'process_return_from_epfl_payonline: GET:' );
		// $this->debug( $_SERVER, 'process_return_from_epfl_payonline: SERVER:' );

		return;
		// die();
	}

	/**
	 * Filter currencies to match Payonline needs.
	 *
	 * See https://wiki.epfl.ch/payonline-help
	 *   All payments can be made in Swiss francs (CHF), euro (EUR) and US dollars (USD).
	 *   For activities organized in Switzerland, it is generally recommended to use Swiss francs (CHF).
	 *
	 * @param Array $currencies ...
	 * @return Array $currencies
	 */
	public function filter_currencies( $currencies ) {
		$currencies = array(
			'USD' => array(
				'name'                => esc_html__( 'U.S. Dollar', 'wpforms' ),
				'symbol'              => '&#36;',
				'symbol_pos'          => 'left',
				'thousands_separator' => ',',
				'decimal_separator'   => '.',
				'decimals'            => 2,
			),
			'EUR' => array(
				'name'                => esc_html__( 'Euro', 'wpforms' ),
				'symbol'              => '&euro;',
				'symbol_pos'          => 'right',
				'thousands_separator' => '.',
				'decimal_separator'   => ',',
				'decimals'            => 2,
			),
			'CHF' => array(
				'name'                => esc_html__( 'Swiss Franc', 'wpforms' ),
				'symbol'              => 'CHF',
				'symbol_pos'          => 'left',
				'thousands_separator' => ',',
				'decimal_separator'   => '.',
				'decimals'            => 2,
			),
		);
		return $currencies;
	}

	/**
	 * Add some details in the payment details sidebar
	 *
	 * @param Int   $entry ...
	 * @param Array $form_data ...
	 */
	public function action_entry_payment_sidebar( $entry, $form_data ) {
		$entry_meta = json_decode( $entry->meta );

		if ( ! empty( $entry_meta->payment_paymode ) ) {
			$entry_meta->payment_paymode = ( 'PostFinance Card PostFinance Card' === $entry_meta->payment_paymode ) ? 'PostFinance Card' : $entry_meta->payment_paymode;
			$entry_meta->payment_paymode = ( 'PAYPAL PAYPAL' === $entry_meta->payment_paymode ) ? 'PayPal' : $entry_meta->payment_paymode;
			echo '<p class="wpforms-entry-payment-paymode">';
			echo __( 'Paymode', 'wpforms-epfl-payonline' ) . ': ';
			echo '<strong>' . $entry_meta->payment_paymode . '</strong>';
			echo '</p>';
		}

		if ( ! empty( $entry_meta->payment_id_trans ) || ! empty( $entry_meta->payment_id_inst ) ) {
			echo '<p class="wpforms-entry-payment-id_trans">';
			echo '<strong>' . __( 'Payonline', 'wpforms-epfl-payonline' ) . ': </strong>';
			echo '<br><small>(' . __( 'Note: accessible only for Payonline instance\'s administrators', 'wpforms-epfl-payonline' ) . ')</small>';
			if ( ! empty( $entry_meta->payment_id_trans ) ) {
				echo '<br>&nbsp;&nbsp;•&nbsp;' . __( 'Transaction detail', 'wpforms-epfl-payonline' ) . ': ';
				echo sprintf( '<a href="https://payonline.epfl.ch/cgi-bin/payonline/dettrans?id_trans=%s" target="_blank" rel="noopener noreferrer">%s</a>', $entry_meta->payment_id_trans, $entry_meta->payment_id_trans );
			}
			if ( ! empty( $entry_meta->payment_id_inst ) ) {
				echo '<br>&nbsp;&nbsp;•&nbsp;' . __( 'Payonline transactions list', 'wpforms-epfl-payonline' ) . ': ';
				echo sprintf( '<a href="https://payonline.epfl.ch/cgi-bin/payonline/listtrans?id=%s" target="_blank" rel="noopener noreferrer">%s</a>', $entry_meta->payment_id_inst, $entry_meta->payment_id_inst );
			}
			echo '</p>';
		}

	}

	/**
	 * Enable by default EPFL Payonline payment gateway.
	 *
	 * @param Array $form_data ...
	 */
	public function filter_has_payment_gateway( $form_data ) {
		if ( ! empty( $form_data['payments']['epfl_payonline']['enable'] ) ) {
			return true;
		}
	}

	/**
	 * Define the payment gateway name in a entry detail.
	 *
	 * @param Array $entry_meta ...
	 * @param Array $entry ...
	 * @param Array $form_data ...
	 */
	public function filter_entry_details_payment_gateway( $entry_meta, $entry, $form_data ) {
		return esc_html__( 'EPFL Payonline', 'wpforms-epfl-payonline' );
	}

	/**
	 * Define the payment transaction name in a entry detail.
	 *
	 * @param Array $entry_meta ...
	 * @param Array $entry ...
	 * @param Array $form_data ...
	 */
	public function filter_entry_details_payment_transaction( $entry_meta, $entry, $form_data ) {
		if ( ! empty( $entry['payment_transaction'] ) ) {
			return $entry['payment_transaction']; // Links don't work here!
		} else {
			return '-';
		}
	}

	/**
	 * Redefine notifications recipient address if set to blog's admin email.
	 * Can also be redefined in the plug by the user, but if function as a
	 * fallback in case the default notification uses te `{admin_email}`
	 * WPForms smart tag.
	 *
	 * @param String  $email ...
	 * @param unknown $fields ...
	 * @param Array   $entry ...
	 * @param Array   $form_data ...
	 * @param Int     $notification_id ...
	 */
	public function redefine_notification_recipents_addresses( $email, $fields, $entry, $form_data, $notification_id ) {
		foreach ( $email['address'] as $k => $address ) {
			if ( get_option( 'admin_email' ) === $address ) {
				$email['address'][ $k ] = sanitize_email( trim( $form_data['payments'][ $this->slug ]['email'] ) );
			}
		}
		$this->debug( $email['address'], 'redefine_notification_recipents_addresses' );
		return $email;
	}

	/**
	 * Display content inside the panel content area.
	 */
	public function builder_content() {

		echo '<p class="lead">' .
			sprintf(
				wp_kses(
					/* translators: %s - Addons page URL in admin area. */
					__( 'This addon allows to use <a href="%1$s">EPFL WordLine SaferPay</a> with the <a href="%2$s">WPForms plugin</a>. Please read <a href="%3$s">XXX Help</a> in order to create a payment instance.', 'wpforms-epfl-payonline' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				esc_url( __( 'https://payonline.epfl.ch?lang=en', 'wpforms-epfl-payonline' ) ),
				esc_url( 'https://wpforms.com/' ),
				esc_url( __( 'https://wiki.epfl.ch/payonline-help', 'wpforms-epfl-payonline' ) )
			) .
			'	<div class="notice">
					<p>' . __( 'General Data Protection Regulation (<b>GDPR</b>): By using this addon, you agree to comply with the directives relating to data protection at EPFL and to apply the seven key principles of article 5 of the GDPR.', 'wpforms-epfl-payonline' ) . '</p>
				</div>
				<p>' .
				sprintf(
					/* translators: %1$s plugin github URL, %2$s plugin release URL, %3$s plugin version */
					__( 'WPForms-EPFL-Payonline\'s information, help and sources are available on <a href="%1$s">GitHub</a>. Your are using the version <a href="%2$s">v%3$s</a> of the addon.', 'wpforms-epfl-payonline' ),
					esc_url( 'https://github.com/epfl-si/wpforms-epfl-payonline' ),
					esc_url( 'https://github.com/epfl-si/wpforms-epfl-payonline/releases/tag/v' . WPFORMS_EPFL_PAYONLINE_VERSION ),
					WPFORMS_EPFL_PAYONLINE_VERSION
				)
				. '</p>
				<hr>
			</p>';

		wpforms_panel_field(
			'toggle',
			$this->slug,
			'enable',
			$this->form_data,
			esc_html__( 'Enable EPFL Payonline payments', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => '0',
			)
		);

		echo '<div class="wpforms-epfl_payonline-payment-settings-container">';
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_customer_id',
			$this->form_data,
			esc_html__( 'SaferPay Customer ID', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_customer_id', 'wpforms-epfl-payonline' ),
				'default' => '265887',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_api_url',
			$this->form_data,
			esc_html__( 'SaferPay API URL', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_api_url', 'wpforms-epfl-payonline' ),
				'default' => 'https://test.saferpay.com',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_api_username',
			$this->form_data,
			esc_html__( 'SaferPay API Username', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_api_username, starting with API_...', 'wpforms-epfl-payonline' ),
				'default' => 'API_265887_09805965',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_api_password',
			$this->form_data,
			esc_html__( 'SaferPay API Password', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_api_password', 'wpforms-epfl-payonline' ),
				'default' => 'apiAPI_265887_24207501',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_terminal_id',
			$this->form_data,
			esc_html__( 'SaferPay Terminal ID', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_terminal_id', 'wpforms-epfl-payonline' ),
				'default' => '17755543',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'email',
			$this->form_data,
			esc_html__( 'WPForms Admin Notification Email Address', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'Enter an email address for payments notification, please use a group', 'wpforms-epfl-payonline' ),
			)
		);
		echo '</div>';

		// if ( function_exists( 'wpforms_conditional_logic' ) ) {
		// 	wpforms_conditional_logic()->builder_block(
		// 	// DEPRECATED wpforms_conditional_logic()->conditionals_block( → removed.
		// 		array(
		// 			'form'        => $this->form_data,
		// 			'type'        => 'panel',
		// 			'panel'       => 'epfl_payonline',
		// 			'parent'      => 'payments',
		// 			'actions'     => array(
		// 				'go'   => esc_html__( 'Process', 'wpforms-epfl-payonline' ),
		// 				'stop' => esc_html__( 'Don\'t process', 'wpforms-epfl-payonline' ),
		// 			),
		// 			'action_desc' => esc_html__( 'this charge if', 'wpforms-epfl-payonline' ),
		// 			'reference'   => esc_html__( 'EPFL Payonline Standard payment', 'wpforms-epfl-payonline' ),
		// 		)
		// 	);
		// } else {
		// 	echo '<p class="note">' .
		// 		sprintf(
		// 			wp_kses(
		// 				/* translators: %s - Addons page URL in admin area. */
		// 				__( 'Install the <a href="%s">Conditional Logic addon</a> to enable conditional logic for EPFL Payonline payments.', 'wpforms-epfl-payonline' ),
		// 				array(
		// 					'a' => array(
		// 						'href' => array(),
		// 					),
		// 				)
		// 			),
		// 			admin_url( 'admin.php?page=wpforms-addons' )
		// 		) .
		// 		'</p>';
		// }
	}

	/**
	 * Add additional links for WPForms EPFL Payonline in the plugins list.
	 *
	 * @param String $plugin_meta ...
	 * @param String $plugin_file ...
	 * @param Array  $plugin_data ...
	 * @param String $status ...
	 */
	public function add_links_to_plugin_row( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( $this->plugin_name === $plugin_data['Name'] ) {
			$row_meta = array(
				'view-details'   => sprintf(
					'<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
					esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug . '&TB_iframe=true&width=600&height=550' ) ),
					/* translators: %s plugin name */
					esc_attr( sprintf( __( 'More information about %s' ), $this->name ) ),
					esc_attr( $this->name ),
					__( 'View details' )
				),
				'payonline'      => '<a href="' . esc_url( 'https://payonline.epfl.ch' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Payonline', 'wpforms-epfl-payonline' ) . '</a>',
				'help-payonline' => '<a href="' . esc_url( 'https://wiki.epfl.ch/payonline-help' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Help Payonline', 'wpforms-epfl-payonline' ) . '</a>',
			);
			return array_merge( $plugin_meta, $row_meta );
		}
		return (array) $plugin_meta;
	}

	/**
	 * Allow plugin to offer an update link to update itself
	 *
	 * @param unknown $transient ...
	 */
	public function wpforms_epfl_payonline_push_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// trying to get from cache first, to disable cache comment 10,20,21,22,24.
		$remote = get_transient( 'upgrade_wpforms_epfl_payonline' );
		if ( false === $remote ) {
			// info.json is the file with the actual plugin information on your server.
			$remote = wp_remote_get(
				'https://api.github.com/repos/epfl-si/wpforms-epfl-payonline/releases/latest',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && 200 === $remote['response']['code'] && ! empty( $remote['body'] ) ) {
				set_transient( 'upgrade_wpforms_epfl_payonline', $remote, $this->cache_seconds ); // 43200 ? 12 hours cache.
			}
		}

		if ( $remote ) {
			$remote         = json_decode( $remote['body'] );
			$latest_version = ltrim( $remote->tag_name, 'v' );

			if ( $remote && version_compare( $this->version, $latest_version, '<' ) && version_compare( $this->wp_min_version, get_bloginfo( 'version' ), '<' ) ) {
				$res                                 = new stdClass();
				$res->slug                           = $this->slug;
				$res->plugin                         = dirname( plugin_basename( __FILE__ ) ) . '/wpforms-epfl-payonline.php';
				$res->new_version                    = $latest_version;
				$res->tested                         = $this->wp_tested_version;
				$res->package                        = 'https://github.com/epfl-si/wpforms-epfl-payonline/releases/latest/download/wpforms-epfl-payonline.zip';
				$transient->response[ $res->plugin ] = $res;
			}
		}
		return $transient;
	}

	/**
	 * Delete plugin transcient when updated
	 *
	 * @param Object $upgrader_object ...
	 * @param Array  $options ...
	 *
	 * @TODO: Try to auto reactivate plugin
	 */
	public function wpforms_epfl_payonline_after_update( $upgrader_object, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			// just clean the cache when new plugin version is installed.
			delete_transient( 'upgrade_wpforms_epfl_payonline' );
		}
	}

	/**
	 * Add the "View Details" link with differents tab
	 *  - Description: information from README.md
	 *  - Installation: information from INSTALL.md
	 *  - Changelog: information from CHANGELOG.md on github
	 *
	 * @param Object $res contains information for plugins with custom update server.
	 * @param String $action 'plugin_information'.
	 * @param Object $args stdClass Object ( [slug] => woocommerce [is_ssl] => [fields] => Array ( [banners] => 1 [reviews] => 1 [downloaded] => [active_installs] => 1 ) [per_page] => 24 [locale] => en_US ).
	 */
	public function wpforms_epfl_payonline_plugin_info( $res, $action, $args ) {

		// do nothing if this is not about getting plugin information.
		if ( 'plugin_information' !== $action ) {
			return false;
		}

		// do nothing if it is not our plugin.
		if ( $this->slug !== $args->slug ) {
			return $res;
		}

		// trying to get from cache first, to disable cache comment 18,28,29,30,32.
		$remote = get_transient( 'upgrade_wpforms_epfl_payonline' );
		if ( false === $remote ) {

			// info.json is the file with the actual plugin information on your server.
			$remote = wp_remote_get(
				'https://api.github.com/repos/epfl-si/wpforms-epfl-payonline/releases/latest',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && 200 === $remote['response']['code'] && ! empty( $remote['body'] ) ) {
				set_transient( 'upgrade_wpforms_epfl_payonline', $remote, $this->cache_seconds ); // 43200 = 12 hours cache.
			}
		}

		if ( $remote ) {
			$remote              = json_decode( $remote['body'] );
			$latest_version      = ltrim( $remote->tag_name, 'v' );
			$res                 = new stdClass();
			$res->name           = $this->plugin_name;
			$res->slug           = $this->slug;
			$res->version        = $latest_version;
			$res->tested         = $this->wp_tested_version;
			$res->requires       = $this->wp_min_version;
			$res->author         = '<a href="https://search.epfl.ch/browseunit.do?unit=13030">EPFL ISAS-FSD</a>'; // I decided to write it directly in the plugin.
			$res->author_profile = 'https://profiles.wordpress.org/ponsfrilus/'; // WordPress.org profile.
			$res->download_link  = $remote->zipball_url;
			$res->trunk          = $remote->html_url;
			$res->last_updated   = $remote->published_at;
			$res->sections       = array(
				'description'  => $this->getReadMe(), // description tab.
				'installation' => $this->getInstall(), // installation tab.
				'changelog'    => $this->getChangelog(), // changelog tab.
				// you can add your custom sections (tabs) here.
			);

			// in case you want the screenshots tab, use the following HTML format for its content:
			// <ol><li><a href="IMG_URL" target="_blank" rel="noopener noreferrer"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>.
			if ( ! empty( $remote->sections->screenshots ) ) {
				$res->sections['screenshots'] = $remote->sections->screenshots;
			}
			$res->banners = array(
				'low'  => plugin_dir_url( __FILE__ ) . 'assets/banners/banner-772x250.png',
				'high' => plugin_dir_url( __FILE__ ) . 'assets/banners/banner-1544x500.png',
			);
			return $res;
		}
		return false;
	}

	/**
	 * Retrieve and parse markdown of the README.md file.
	 *
	 * See http://localhost:8089/wp-admin/plugin-install.php?tab=plugin-information&plugin=epfl_payonline&section=changelog&TB_iframe=true&width=600&height=800
	 */
	private function getReadMe() {
		$readme_path    = plugin_dir_path( __FILE__ ) . '/README.md';
		$readme_content = file_get_contents( $readme_path, true );
		require_once plugin_dir_path( __FILE__ ) . '/lib/Parsedown.php';
		$parsedown      = new Parsedown();
		$readme_content = $parsedown->text( $readme_content );
		return 'See README.md on <a href="https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/README.md">GitHub</a>.<br><div class="epfl_payonline_readme">' . $readme_content . '</div>';
	}

	/**
	 * Retrieve and parse markdown of the INSTALL.md file.
	 */
	private function getInstall() {
		$install_path    = plugin_dir_path( __FILE__ ) . '/INSTALL.md';
		$install_content = file_get_contents( $install_path, true );
		require_once plugin_dir_path( __FILE__ ) . '/lib/Parsedown.php';
		$parsedown       = new Parsedown();
		$install_content = $parsedown->text( $install_content );
		return 'See INSTALL.md on <a href="https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/INSTALL.md">GitHub</a>.<br><div class="epfl_payonline_install">' . $install_content . '</div>';
	}

	/**
	 * Retrieve CHANGELOG.md on github and parse markdown.
	 */
	private function getChangelog() {
		$changelog_content = file_get_contents( 'https://raw.githubusercontent.com/epfl-si/wpforms-epfl-payonline/master/CHANGELOG.md', true );
		require_once plugin_dir_path( __FILE__ ) . '/lib/Parsedown.php';
		$parsedown         = new parsedown();
		$changelog_content = $parsedown->text( $changelog_content );
		return 'See CHANGELOG.md on <a href="https://github.com/epfl-si/wpforms-epfl-payonline/blob/master/CHANGELOG.md">GitHub</a>.<br><div class="epfl_payonline_changelog">' . $changelog_content . '</div>';
	}

}

new WPForms_EPFL_Payonline();
