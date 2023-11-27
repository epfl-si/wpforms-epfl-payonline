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

		add_action( 'admin_enqueue_scripts', array( $this, 'load_custom_wp_admin_style' ) );

		add_action( 'wpforms_process_complete', array( $this, 'process_payment_to_wordline_saferpay' ), 20, 4 );
		add_filter( 'wpforms_forms_submission_prepare_payment_data', [ $this, 'prepare_payment_data' ], 10, 3 );
		add_filter( 'wpforms_forms_submission_prepare_payment_meta', [ $this, 'prepare_payment_meta' ], 10, 3 );
		add_action( 'init', array( $this, 'check_return_from_saferpay' ) );
		add_action( 'wpforms_process_payment_saved', [ $this, 'process_payment_saved' ], 10, 3 );

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

		// Plugin details.
		add_filter( 'plugins_api', array( $this, 'wpforms_epfl_payonline_plugin_info' ), 20, 3 );

		// Add payonline.epfl.ch to safe redirect hosts.
		add_filter( 'allowed_redirect_hosts', array( $this, 'wpforms_add_payonline_to_allowed_redirect' ) );
		//add_action( 'wpforms_process', [ $this, 'process_payment_to_wordline_saferpay' ], 10, 3 );


		add_filter( 'wpforms_db_payments_value_validator_get_allowed_gateways', [ $this, 'allowed_gateways' ], 10, 1 );

		// ???
		// add_action( 'wpforms_form_settings_notifications_single_after', [ $this, 'notification_settings' ], 10, 2 );
		// Logic that helps decide if we should send completed payments notifications. add_filter( 'wpforms_entry_email_process', [ $this, 'process_email' ], 70, 5 );
    }


	/**
	 * Export var to error log
	 *
	 * @param unknown $var ...
	 * @param String  $title ...
	 */
	function allowed_gateways( $list_of_allowed_gateway ) {
		$list_of_allowed_gateway[$this->slug] = esc_html__( $this->name, 'wpforms-epfl-payonline' );
		//$this->debug( $list_of_allowed_gateway, 'list_of_allowed_gateway');
		return $list_of_allowed_gateway;
	}

	/**
	 * Export var to error log
	 *
	 * @param unknown $var ...
	 * @param String  $title ...
	 */
	private function log( $var, $title = null ) {
		$log = '-- WPFORMS EPFL Payonline/Saferpay ';
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
	 * Update payment data by ID.
	 *
	 * @since 1.7.0
	 *
	 * @param string|int $payment_id Payment ID.
	 * @param array      $data       Payment data.
	 *
	 * @return void
	 */
	private function update_payment( $payment_id, $data = [] ) {

		wpforms()->get( 'payment' )->update( $payment_id, $data, '', '', [ 'cap' => false ] );
	}


	/**
	 * Update payment data by ID.
	 *
	 * @since 1.7.0
	 *
	 * @param string|int $payment_id Payment ID.
	 * @param array      $data       Payment data.
	 *
	 * @return void
	 */
	private function update_payment_meta( $payment_id, $data = [] ) {

		wpforms()->get( 'payment_meta' )->update( $payment_id, $data, '', '', [ 'cap' => false ] );
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
		$hosts[] = 'saferpay.com';
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

		$error = null;

		// Debugging submission to EPFL Payonline.
		// $this->debug( $form_data['settings']['form_title'], 'form_dataform_dataform_data' );
		// $this->debug( $form_data['payments'], '2payonline: $form_data[payments]' );
		// $this->debug( $fields, '2payonline: $fields' );
		// $this->debug( $entry, '2payonline: $entry' );

		// Check if payment method exists.
		if ( empty( $form_data['payments'][ $this->slug ] ) ) {
			$this->log( $form_data['payments'], 'No payment method found' );
			return;
		}

		// Check required payment settings.
		$payment_settings = $form_data['payments'][ $this->slug ];
		if (
			empty( $payment_settings['enable'] ) ||
			empty( $payment_settings['payonline_mode'] ) ||
			( '1' !== $payment_settings['enable'] )
		) {
			$this->log( $payment_settings, '⚠ Some EPFL Wordline Saferpay settings are missing ⚠' );
			$this->debug( $payment_settings, "PAYMENT SETTINGS" );
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

		// Last change to filter args.
		$payonline_args = '';
		$payonline_args = apply_filters( 'wpforms_payonline_redirect_args', $payonline_args, $fields, $form_data, $entry_id );

		#
		# INITIALIZE WORDLINE PAYMENT
		#
		$payment_data = array(
			'amount'                 => $amount,
			'currency'               => strtolower( wpforms_setting( 'currency', 'CHF' )),
			'FirstName'              => $this->getArraysFromType( $fields, 'name' )[0]['first'],
			'LastName'               => $this->getArraysFromType( $fields, 'name' )[0]['last'],
			'Street'                 => $this->getArraysFromType( $fields, 'address' )[0]['address1'],
			'Street2'                => $this->getArraysFromType( $fields, 'address' )[0]['address2'],
			'Zip'                    => $this->getArraysFromType( $fields, 'address' )[0]['postal'],
			'City'                   => $this->getArraysFromType( $fields, 'address' )[0]['city'],
			'CountrySubdivisionCode' => $this->getArraysFromType( $fields, 'address' )[0]['state'],
			'CountryCode'            => $this->getArraysFromType( $fields, 'address' )[0]['country'],
			'Email'                  => $this->getFieldsFromType( $fields, 'email' )[0]
		);
		$wpforms_data = array(
			"items" => wpforms_get_payment_items( $fields ),
			"entry_id" => absint( $entry_id ),
			"blog_info" => get_bloginfo( 'name' ),
			"form_title" => $form_data['settings']['form_title']
		);

		$payment = new SaferpayPayment(/*settings*/$payment_settings, /*data, total and user info*/$payment_data, $wpforms_data);
		$payment_init_result = $payment->paymentPageInitialize();
		$this->payment_redirect_url = $payment_init_result->RedirectUrl;
		// $this->debug( $payment_init_result->RedirectUrl, 'preparing redirection to saferpay' );
		// $this->debug( $this->payment_redirect_url, 'preparing redirection to saferpay' );

		// Save to wp_forms_payment in pending $this->payment_save($entry);

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
	 * Add details to payment data.
	 *
	 * @since 1.7.0
	 *
	 * @param array $payment_data Payment data args.
	 * @param array $fields       Form fields.
	 * @param array $form_data    Form data.
	 *
	 * @return array
	 */
	public function prepare_payment_data( $payment_data, $fields, $form_data ) {

		// $this->debug( $payment_data, 'payment_data' );
		// $this->debug( $form_data, 'form_data' );

		// If payment has not been cleared for processing, return.
		/*if ( ! $this->allowed_to_process ) {
			return $payment_data;
		}*/

		$payment_data['status']  = 'pending';
		$payment_data['gateway'] = sanitize_key( $this->slug );
		$payment_settings        = $form_data['payments'][ sanitize_key( $this->slug ) ];
		$payment_data['mode']    = ($payment_settings['payonline_mode'] === 'production') ? 'live' : 'test';

		return $payment_data;
	}

	/**
	 * Add payment meta for a successful one-time or subscription payment.
	 *
	 * @since 1.7.0
	 *
	 * @param array $payment_meta Payment meta.
	 * @param array $fields       Sanitized submitted field data.
	 * @param array $form_data    Form data and settings.
	 *
	 * @return array
	 */
	public function prepare_payment_meta( $payment_meta, $fields, $form_data ) {

		// $this->debug( $payment_meta, 'payment_meta' );
		// $this->debug( $form_data, 'form_data' );

		// If payment has not been cleared for processing, return.
		/*if ( ! $this->allowed_to_process ) {
			return $payment_meta;
		}*/

		$payment_meta['redirect_url'] = $this->payment_redirect_url;
		// $payment_meta['method_type'] = 'EPFLPayonline';

		return $payment_meta;
	}

	/**
	 * Handle the POSTed data from payonline
	 *
	 * See https://www.openldap.org/faq/data/cache/347.html
	 * and https://github.com/anhofmann/php-ssha/blob/master/ssha.php
	 *
	 * @return void
	 */
	public function check_return_from_saferpay() {
		// Anything like wp_url/index.php?EPFLPayonline.
		if ( ! isset( $_GET['EPFLPayonline'] ) || ! isset( $_GET['entry_id'] ) ) {
			return;
		} else {
			$this->debug( "Get hit on /?EPFLPayonline for " . $_GET['entry_id'], 'check_return_from_saferpay' );
		}
		$entry_id = absint( $_GET['entry_id'] );

		// TODO: do something clever here.
		$entry = wpforms()->entry->get( $entry_id );
		$this->debug( $entry_id, "Entry ID" );

		$payment = wpforms()->get( 'payment' )->get_by( 'entry_id', $entry_id );

		$form_data = wpforms()->form->get(
			$entry->form_id,
			array(
				'content_only' => true,
			)
		);

		// $this->debug( $form_data, "FORM DATA" );
		$payment_settings = $form_data['payments'][ $this->slug ];

		// If payment or form doesn't exist, bail.
		if ( empty( $entry ) || empty( $form_data ) ) {
			$this->log( 'Error: Missing $payment or $form_data in check_return_from_saferpay()', 'check_return_from_saferpay' );
			return;
		}

		$entry_meta = json_decode( $entry->meta, true );
		$this->debug( $entry_meta, "entry_meta" );
		$this->debug( $entry_meta['wordline_specifics'], "entry_meta->wordline_specifics" );
		$this->debug( $entry_meta['wordline_specifics']['Token'], "entry_meta->wordline_specifics->token" );


		$saferpay_payment = new SaferpayPayment(/*settings*/$payment_settings, /*data, total and user info*/null, null);
		$assert = $saferpay_payment->paymentPageAssert($entry_meta['wordline_specifics']['Token'], $entry_meta['wordline_specifics']['ResponseHeader']['RequestId']);

		error_log("\n\nASSERT\n");
		error_log(var_export(json_decode( $assert, true ), true));
		error_log("\n\nEND ASSERT\n");

		if ($assert->Transaction->AcquirerName !== '') {
			$this->debug( "AcquirerName is " . $assert->Transaction->AcquirerName );
			wpforms()->get( 'payment_meta' )->add(
				[
					'payment_id' => $payment->id,
					'meta_key'   => 'method_type',
					'meta_value' => $assert->Transaction->AcquirerName,
				]
			);
		} else {
			$this->debug("AcquirerNameAcquirerNameAcquirerNameAcquirerNameAcquirerNameAcquirerName");
		}


		if ( $assert && $assert->Transaction->Status !== 'AUTHORIZED' && $assert->Transaction->Status !== 'CAPTURED' ) { // && $assert->Transaction->Type === 'PAYMENT'
			$this->debug( $assert, "ASSERT" );
			$this->debug( $assert->Transaction->Status, "ASSERT" ); // AUTHORIZED
			$this->debug( $assert->Transaction->Type, "ASSERT" ); // PAYMENT

			// THE PAYMENT HAS FAILED
			wpforms_log(
				esc_html__( 'EPFL Wordline Return Error', 'wpforms-epfl-payonline' ),
				sprintf( 'Error - Wordline data: %s', '<pre>' . var_export( $assert, true ) . '</pre>' ),
				[
					'parent'  => $entry_id,
					'type'    => [ 'error', 'payment' ],
					'form_id' => $payment->form_id,
				]
			);
			$this->add_payment_log( $payment->id, 'EPFL Wordline Return Error' );

			$array_assert = (array) $assert; // was an object array
			$entry_meta['wordline_assert'] = $array_assert;
			$entry_data = array(
				'status' => 'failed',
				// 'type'   => 'payment',
				'meta'   => wp_json_encode(
					$entry_meta
				)
			);
			//$this->update_payment( $payment->id, [ 'title' => "TITRE DE TEST l532 class-wpforms....php" ] );
			wpforms()->entry->update( $entry_id, $entry_data, '', '', array( 'cap' => false ) );

			$this->update_payment( $payment->id, [ 'status' => 'failed' ] );

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
			$this->update_payment( $payment->id, [ 'status' => 'completed' ] );
			return;
		}

		// CAPTURE PAYMENT — actual money
		$capture = $saferpay_payment->paymentCapture($assert->Transaction->Id, $entry_meta['wordline_specifics']['ResponseHeader']['RequestId']);
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
			$this->update_payment( $payment->id, [ 'status' => 'failed' ] );

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
			wpforms()->entry->update( $entry_id, $entry_data, '', '', array( 'cap' => false ) );

			$this->update_payment( $payment->id, [ 'status' => 'completed' ] );

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

		// Debug check_return_from_saferpay.
		// $this->debug( $_POST, 'check_return_from_saferpay: POST:' );
		// $this->debug( $_GET, 'check_return_from_saferpay: GET:' );
		// $this->debug( $_SERVER, 'check_return_from_saferpay: SERVER:' );

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
			// 'USD' => array(
			// 	'name'                => esc_html__( 'U.S. Dollar', 'wpforms' ),
			// 	'symbol'              => '&#36;',
			// 	'symbol_pos'          => 'left',
			// 	'thousands_separator' => ',',
			// 	'decimal_separator'   => '.',
			// 	'decimals'            => 2,
			// ),
			// 'EUR' => array(
			// 	'name'                => esc_html__( 'Euro', 'wpforms' ),
			// 	'symbol'              => '&euro;',
			// 	'symbol_pos'          => 'right',
			// 	'thousands_separator' => '.',
			// 	'decimal_separator'   => ',',
			// 	'decimals'            => 2,
			// ),
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
			'select',
			$this->slug,
			'payonline_mode',
			$this->form_data,
			esc_html__( 'Payonline Payment Mode', 'wpforms' ),
			[
				'default'     => 'Test',
				'options'     => [
					'test'  => esc_html__( 'Test', 'wpforms-epfl-payonline' ),
					'production'     => esc_html__( 'Production', 'wpforms-epfl-payonline' ),
					'manual' => esc_html__( 'Manual', 'wpforms-epfl-payonline' ),
				],
				'class'       => 'wpforms-epfl-payonline-payment-mode-wrap',
				'input_id'    => 'wpforms-epfl-payonline-payment-mode',
				'input_class' => 'wpforms-epfl-payonline-payment-mode',
				'parent'      => 'payments',
				'tooltip' => esc_html__( 'Payonline payment mode : <br><ul><li>• Choose "Test" for testing purposes. No payments will be processed, but you can perform a comprehensive test of the entire process.</li><li>• Choose the "Production" option to initiate payment processing. The reconciliation code must be supplied by EPFL to ensure that the funds are accurately received within the EPFL system.</li><li>• Opt for this choice if you require your dedicated Saferpay terminal/instance. This option is intended for advanced users only.</li></ul>', 'wpforms-epfl-payonline' ),
			]
		);

		echo '<div class="wpforms-epfl-payonline-payment-mode-manual-container">';
		wpforms_panel_field(
			'text',
			$this->slug,
			'saferpay_customer_id',
			$this->form_data,
			esc_html__( 'SaferPay Customer ID', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_customer_id', 'wpforms-epfl-payonline' ),
				'default' => '',
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
				'default' => '',
			)
		);
		wpforms_panel_field(
			'text', // TODO password ?
			$this->slug,
			'saferpay_api_password',
			$this->form_data,
			esc_html__( 'SaferPay API Password', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'saferpay_api_password', 'wpforms-epfl-payonline' ),
				'default' => '',
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
				'default' => '',
			)
		);
		echo '</div><br>';

		echo '<div class="wpforms-epfl-payonline-payment-sf-code-container">';
		wpforms_panel_field(
			'text',
			$this->slug,
			'payment_reconciliation_code',
			$this->form_data,
			esc_html__( 'Payment Reconciliation Code', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'Payment reconciliation code provided by the accountings (SF)', 'wpforms-epfl-payonline' ),
			)
		);
		echo '</div><br>';

		// We decided that the form's name should be comprehensive enough for the
		// end user. It is alors easier for "webmaster".
		// wpforms_panel_field(
		// 	'text',
		// 	$this->slug,
		// 	'payment_description',
		// 	$this->form_data,
		// 	esc_html__( 'A description of the payment for the end-user', 'wpforms-epfl-payonline' ),
		// 	array(
		// 		'parent'  => 'payments',
		// 		'tooltip' => esc_html__( 'The description is shown in the payment page', 'wpforms-epfl-payonline' ),
		// 		'default' => 'EPFL payment'
		// 	)
		// );
		wpforms_panel_field(
			'text',
			$this->slug,
			'email',
			$this->form_data,
			esc_html__( 'WPForms Admin Notification Email Address', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'Enter an email address for payments notification: it is recommanded to create a <a href="https://groups.epfl.ch">groups</a> to easily add more than one person.', 'wpforms-epfl-payonline' ),
			)
		);
		echo '</div>';

	}

	/**
	 * Add additional links for WPForms EPFL Payonline in the plugins list.
	 *
	 * @access  public
	 * @param   array       $links_array            An array of the plugin's metadata
	 * @param   string      $plugin_file_name       Path to the plugin file
	 * @param   array       $plugin_data            An array of plugin data
	 * @param   string      $status                 Status of the plugin
	 * @return  array       $links_array
	 */
	public function add_links_to_plugin_row( $links_array, $plugin_file_name, $plugin_data, $status ) {

		if ( $this->plugin_name === $plugin_data['Name'] ) {
			$row_meta = array(
				'payonline'      => '<a href="' . esc_url( 'https://payonline.epfl.ch' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Payonline', 'wpforms-epfl-payonline' ) . '</a>',
				'help-payonline' => '<a href="' . esc_url( 'https://wiki.epfl.ch/payonline-help' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Help Payonline', 'wpforms-epfl-payonline' ) . '</a>',
			);
			return array_merge( $links_array, $row_meta );
		}
		return (array) $links_array;
	}

}

new WPForms_EPFL_Payonline();
