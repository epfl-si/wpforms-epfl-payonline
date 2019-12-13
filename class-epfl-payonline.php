<?php

/**
 * EPFL Payonline integration.
 *
 * @package WPFormsEPFLPayonline
 */
class WPForms_EPFL_Payonline extends WPForms_Payment {

	/**
	 * Initialize.
	 *
	 */
	public function init() {

		$this->version      = WPFORMS_EPFL_PAYONLINE_VERSION;
		$this->plugin_name  = WPFORMS_EPFL_PAYONLINE_NAME;
		$this->name         = 'EPFL Payonline';
		$this->slug         = 'epfl_payonline';
		$this->priority     = 10;
		$this->icon         = plugins_url( 'assets/images/EPFL-Payonline-trans.png', __FILE__ );

		add_action( 'wpforms_process_complete', array( $this, 'process_entry_to_epfl_payonline' ), 20, 4 );
		add_action( 'init', array( $this, 'process_return_from_epfl_payonline' ) );
		
		// (see wpforms/pro/includes/payments/functions.php)
		add_filter( 'wpforms_currencies', array( $this, 'filter_currencies' ), 10, 1 );
		add_filter( 'wpforms_has_payment_gateway', array( $this, 'filter_has_payment_gateway' ), 10, 1 );

		// Define filters for payment details (see wpforms/pro/includes/admin/entries/class-entries-single.php)
		add_filter( 'wpforms_entry_details_payment_gateway', array( $this, 'filter_entry_details_payment_gateway' ), 10, 3 );
		add_filter( 'wpforms_entry_details_payment_transaction', array( $this, 'filter_entry_details_payment_transaction' ), 10, 3 );
		
		// Add additional link to the plugin row
		add_filter( 'plugin_row_meta', array( $this, 'add_links_to_plugin_row') , 10, 4 );
	}

	/**
	 * Process and submit entry to provider.
	 *
	 *
	 * @param array $fields
	 * @param array $entry
	 * @param array $form_data
	 * @param int   $entry_id
	 */
	public function process_entry_to_epfl_payonline( $fields, $entry, $form_data, $entry_id ) {

		// TODO: start actually using this variable or remove it.
		$error = false;

		// Check an entry was created and passed.
		if ( empty( $entry_id ) ) {
			return;
		}

		// Check if payment method exists.
		if ( empty( $form_data['payments'][ $this->slug ] ) ) {
			return;
		}

		// Check required payment settings.
		$payment_settings = $form_data['payments'][ $this->slug ];
		if (
			empty( $payment_settings['email'] ) ||
			empty( $payment_settings['enable'] ) ||
			$payment_settings['enable'] != '1'
		) {
			return;
		}

		// Check for conditional logic.
		if (
			! empty( $form_data['payments']['epfl_payonline']['conditional_logic'] ) &&
			! empty( $form_data['payments']['epfl_payonline']['conditional_type'] ) &&
			! empty( $form_data['payments']['epfl_payonline']['conditionals'] ) &&
			function_exists( 'wpforms_conditional_logic' )
		) {

			// All conditional logic checks passed, continue with processing.
			$process = wpforms_conditional_logic()->conditionals_process( $fields, $form_data, $form_data['payments']['epfl_payonline']['conditionals'] );

			if ( 'stop' === $form_data['payments']['epfl_payonline']['conditional_type'] ) {
				$process = ! $process;
			}

			// If preventing the notification, log it, and then bail.
			if ( ! $process ) {
				wpforms_log(
					esc_html__( 'EPFL Payonline Payment stopped by conditional logic', 'wpforms-epfl-payonline' ),
					$fields,
					array(
						'parent'  => $entry_id,
						'type'    => array( 'payment', 'conditional_logic' ),
						'form_id' => $form_data['id'],
					)
				);

				return;
			}
		}

		// Check that, despite how the form is configured, the form and
		// entry actually contain payment fields, otherwise no need to proceed.
		$form_has_payments  = wpforms_has_payment( 'form', $form_data );
		$entry_has_paymemts = wpforms_has_payment( 'entry', $fields );
		if ( ! $form_has_payments || ! $entry_has_paymemts ) {
			$error = esc_html__( 'EPFL Payonline Payment stopped, missing payment fields', 'wpforms-epfl-payonline' );
		}

		// Check total charge amount.
		$amount = wpforms_get_total_payment( $fields );
		if ( empty( $amount ) || $amount == wpforms_sanitize_amount( 0 ) ) {
			$error = esc_html__( 'EPFL Payonline Payment stopped, invalid/empty amount', 'wpforms-epfl-payonline' );
		}

		if ( $error ) {
			return;
		}

		// Update entry to include payment details.
		$entry_data = array(
			'status' => 'pending',
			'type'   => 'payment',
			'meta'   => wp_json_encode(
				array(
					'payment_type'      => $this->slug,
					'payment_recipient' => trim( sanitize_email( $payment_settings['email'] ) ),
					'payment_total'     => $amount,
					'payment_currency'  => strtolower( wpforms_setting( 'currency', 'USD' ) ),
					'payment_mode'      => esc_html( $payment_settings['mode'] ),
				)
			),
		);
		wpforms()->entry->update( $entry_id, $entry_data );

		// Build the return URL with hash.
		$query_args  = 'form_id=' . $form_data['id'] . '&entry_id=' . $entry_id . '&hash=' . wp_hash( $form_data['id'] . ',' . $entry_id );
		$return_url = home_url('/index.php');
		if ( ! empty( $form_data['settings']['ajax_submit'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$return_url = $_SERVER['HTTP_REFERER'];
		}

		$return_url = esc_url_raw(
			add_query_arg(
				array(
					'wpforms_return' => base64_encode( $query_args ),
				),
				apply_filters( 'wpforms_payonline_return_url', $return_url, $form_data )
			)
		);

		// Setup various vars.
		$items       = wpforms_get_payment_items( $fields );
		$redirect    = 'production' === $payment_settings['mode'] ? 'https://payonline.epfl.ch/cgi-bin/commande/?' : 'https://payonline.epfl.ch/cgi-bin/commande/?';
		$id_inst     = 'production' === $payment_settings['mode'] ? $payment_settings['id_inst'] : '1234567890';
		$cancel_url  = ! empty( $payment_settings['cancel_url'] ) ? esc_url_raw( $payment_settings['cancel_url'] ) : home_url();
		$transaction = 'donation' === $payment_settings['transaction'] ? '_donations' : '_cart';

		// Setup EPFL Payonline arguments.
		$payonline_args = array(
			'id_inst'       => $id_inst,
			'Currency'      => strtoupper( wpforms_setting( 'currency', 'USD' ) ),
			'LastName'      => $entry['fields'][0]['last'],
			'FirstName'     => $entry['fields'][0]['first'],
			'Addr'          => $entry['fields'][3]['address1'],
			'ZipCode'       => $entry['fields'][3]['postal'],
			'City'          => $entry['fields'][3]['city'],
			'Country'       => $entry['fields'][3]['country'],
			'Email'         => $entry["fields"][1],
			'id_transact'   => absint( $entry_id ),
			// 'URL'           => "http://localhost:8080/index.php?commande=OK",
			// 'url'           => "http://localhost:8080/index.php?commande=OK",
			'Total'         => 0, // defined below...
			'wpforms_return' => base64_encode( $query_args ), // Test for return var
			// WPForms default...
			'bn'            => 'WPForms_SP',
			'business'      => trim( $payment_settings['email'] ),
			'cancel_return' => $cancel_url,
			'cbt'           => get_bloginfo( 'name' ),
			'charset'       => get_bloginfo( 'charset' ),
			'cmd'           => $transaction,
			'currency_code' => strtoupper( wpforms_setting( 'currency', 'USD' ) ),
			'custom'        => absint( $form_data['id'] ),
			'invoice'       => absint( $entry_id ),	
			'no_note'       => absint( $payment_settings['note'] ),
			'no_shipping'   => absint( $payment_settings['shipping'] ),
			'notify_url'    => add_query_arg( 'wpforms-listener', '', home_url( 'index.php' ) ), // add_query_arg( 'wpforms-listener', 'IPN', home_url( 'index.php' ) ),
			'return'        => $return_url,
			'rm'            => '2',
			'tax'           => 0,
			'upload'        => '1',
		);

		error_log( print_r( $payonline_args, true ) );

		// Add cart items.
		if ( '_cart' === $transaction ) {

			// Product/service.
			$i = 1;
			foreach ( $items as $item ) {

				$item_amount = wpforms_sanitize_amount( $item['amount'] );

				if (
					! empty( $item['value_choice'] ) &&
					in_array( $item['type'], array( 'payment-multiple', 'payment-select' ), true )
				) {
					$item_name = $item['name'] . ' - ' . $item['value_choice'];
				} else {
					$item_name = $item['name'];
				}
				$payonline_args[ 'item_name_' . $i ] = stripslashes_deep( html_entity_decode( $item_name, ENT_COMPAT, 'UTF-8' ) );
				// Don't yet support quantities.
				//$payonline_args['quantity_' . $i ]  = $item['quantity'];
				$payonline_args[ 'amount_' . $i ] = $item_amount;
				$payonline_args['Total']    += $item_amount;
				$i ++;
			}
		} else {

			// Combine a donation name from all payment fields names.
			$item_names = array();

			foreach ( $items as $item ) {

				if (
					! empty( $item['value_choice'] ) &&
					in_array( $item['type'], array( 'payment-multiple', 'payment-select' ), true )
				) {
					$item_name = $item['name'] . ' - ' . $item['value_choice'];
				} else {
					$item_name = $item['name'];
				}

				$item_names[] = stripslashes_deep( html_entity_decode( $item_name, ENT_COMPAT, 'UTF-8' ) );
			}

			$payonline_args['item_name'] = implode( '; ', $item_names );
			$payonline_args['amount']    = $amount;
			$payonline_args['Total']    = $amount;
		}

		$payonline_args['Total'] = number_format($payonline_args['Total'], 2, '.', '');
		// Last change to filter args.
		$payonline_args = apply_filters( 'wpforms_payonline_redirect_args', $payonline_args, $fields, $form_data, $entry_id );

		// Build query.
		$redirect .= http_build_query( $payonline_args );
		$redirect  = str_replace( '&amp;', '&', $redirect );

		// Redirect to EPFL Payonline.
		wp_redirect( $redirect );
		exit;
	}

	/**
	 * Verify SSHA hash.
	 *
	 * See https://www.openldap.org/faq/data/cache/347.html
	 * and https://github.com/anhofmann/php-ssha/blob/master/ssha.php
	 *
	 * @param string $token
	 * @param string $inputHash ($id_transact . ':' . $id_inst)
	 * @return boolean
	 */
	private function ssha_password_verify ($token, $inputHash) {
		list($salt, $hash) = explode(':', $token);
		$ohash = base64_decode(substr($hash, 6));
		$osalt = substr($ohash, 20);
		$ohash = substr($ohash, 0, 20);
		$nhash = pack("H*", sha1($inputHash . $osalt));
		if ($ohash === $nhash) {
			return True;
		} else {
			return False;
		}
	}

	/**
	 * Handle the POSTed data from payonline.
	 *
	 * See https://www.openldap.org/faq/data/cache/347.html
	 * and https://github.com/anhofmann/php-ssha/blob/master/ssha.php
	 *
	 * @return void
	 */
	public function process_return_from_epfl_payonline () {
		// Anything like wp_url/index.php?EPFLPayonline
		if ( ! isset( $_GET['EPFLPayonline'] ) || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
			error_log( "Hit index.php?EPFLPayonline; RETURN" );
			return;
		}
		error_log( var_export($_POST, true) );

		$data = array();
		// Loop through each POST.
		foreach ( $_POST as $key => $value ) {
			// TODO Sanitize values
			$data[$key] = $value;
		}
		
		// Check if $post_data_array has been populated.
		if ( ! is_array( $data ) || empty( $data ) || empty( $data['invoice'] ) || empty( $data['token'] ) || empty( $data['id_transact'] ) || empty( $data['id_inst'] ) ) {
			error_log( "Error: Missing \$data in process_return_from_epfl_payonline()" );
			return;
		}

		// Get payment (entry).
		if ( !$this->ssha_password_verify($data['token'], $data['id_transact']. ':' . $data['id_inst']) ) {
			error_log( "Error: Hash can not be verified" );
			return;
		}

		$error          = '';
		$payment_id     = absint( $data['invoice'] );
		$payment        = wpforms()->entry->get( absint( $payment_id ) );
		$payment_status = $data['result']; // result		if ( 'completed' === $payment_status || 'production' !== $payment_meta['payment_mode'] ) {
		$form_data      = wpforms()->form->get( $payment->form_id, array(
			'content_only' => true,
		) );

		// If payment or form doesn't exist, bail.
		if ( empty( $payment ) || empty( $form_data ) ) {
			error_log( "Error: Missing \$payment or \$form_data in process_return_from_epfl_payonline()" );
			return;
		}
		
		$payment_meta = json_decode( $payment->meta, true );

			// Verify payment recipient emails match.
			if ( empty( $form_data['payments']['epfl_payonline']['email'] ) || strtolower( $data['business'] ) !== strtolower( trim( $form_data['payments']['epfl_payonline']['email'] ) ) ) {
				$error = esc_html__( 'Payment failed: recipient emails do not match', 'wpforms-epfl-payonline' );
			}
			if ( 0 == $payment_status ) {
				$error = esc_html__( 'Payment failed: payonline returned 0', 'wpforms-epfl-payonline' );
			}

			// If there was an error, log and update the payment status.
			if ( ! empty( $error ) || 0 == $payment_status ) {
				$payment_meta['payment_note'] = $error;
				wpforms_log(
					esc_html__( 'EPFL Payonline Return Error', 'wpforms-epfl-payonline' ),
					sprintf( '%s - Payonline data: %s', $error, '<pre>' . print_r( $data, true ) . '</pre>' ),
					array(
						'parent'  => $payment_id,
						'type'    => array( 'error', 'payment' ),
						'form_id' => $payment->form_id,
					)
				);
				wpforms()->entry->update( $payment_id, array(
					'status' => 'failed',
					'meta'   => wp_json_encode( $payment_meta ),
				) );

				return;
			}

			// Completed payment.
			if ( 1 == $payment_status ) {

				$payment_meta['payment_transaction'] = $data['PaymentID'];
				//$payment_meta['payment_note']        = 'Yay <a href="test">test</a>';
				$user_url = esc_url(
					add_query_arg(
						array(
							'user_id' => absint( $payment_meta['payment_transaction'] ),
						),
						admin_url( 'user-edit.php' )
					)
				);
				$payment_meta['payment_note'] = "<strong><a href=" . $user_url . ">" . absint( $payment_meta['payment_transaction'] ) . "</a></strong>";
				// $payment_meta['payment_note']        = 'Yay <a href="test">test</a>';
				wpforms()->entry->update( $payment_id, array(
					'status' => 'completed',
					'meta'   => wp_json_encode( $payment_meta ),
				) );

			}

			// Completed EPFL Payonline IPN call.
			do_action( 'wpforms_epfl_payonline_process_complete', wpforms_decode( $payment->fields ), $form_data, $payment_id, $data );

		error_log( var_export( $_POST, true ) );
		error_log( var_export( $_GET, true ) );
		error_log( var_export( $_SERVER, true ) );
		error_log( " - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - " );
		echo var_export( $_POST, true ) ;
		echo var_export( $_GET, true ) ;
		echo var_export( $_SERVER, true ) ;
		die();
		
	}

	
	/**
	 * Filter currencies to match Payonline needs.
	 *
	 * See https://wiki.epfl.ch/payonline-help
	 *   All payments can be made in Swiss francs (CHF), euro (EUR) and US dollars (USD).
	 *   For activities organized in Switzerland, it is generally recommended to use Swiss francs (CHF).
	 *
	 * @param array $currencies
	 * @return array $currencies
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
	 * Enable by default EPFL Payonline payment gateway.
	 *
	 */
	public function filter_has_payment_gateway( $form_data ) {
		if ( ! empty( $form_data['payments']['epfl_payonline']['enable'] ) ) {
			return true;
		}
	}

	/**
	 * Define the payment gateway name in a entry detail.
	 *
	 */
	public function filter_entry_details_payment_gateway( $entry_meta, $entry, $form_data ) {
		return esc_html__( 'EPFL Payonline', 'wpforms-epfl-payonline' );
	}

	/**
	 * Define the payment transaction name in a entry detail.
	 *
	 */
	public function filter_entry_details_payment_transaction( $entry_meta, $entry, $form_data ) {
		if ( ! empty( $entry['payment_transaction'] ) ) {
			return $entry['payment_transaction']; // Links don't work here
			// return sprintf( '<a href="https://payonline.epfl.ch/cgi-bin/payonline/dettrans?id_trans=%s" target="_blank" rel="noopener noreferrer">%s</a>', $entry['payment_transaction'], $entry['payment_transaction'] );
		} else {
			return '-';
		}
	}

	/**
	 * Display content inside the panel content area.
	 *
	 */
	public function builder_content() {

		echo
			'<p class="note">' .
			sprintf(
				wp_kses(
					/* translators: %s - Addons page URL in admin area. */
					__( 'This addon allows to use <a href="%s">EPFL Payonline</a> with the <a href="%s">WPForms plugin</a>. Please read <a href="%s">Payonline Help</a>.', 'wpforms-epfl-payonline' ),
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
			'</p><br /><hr><br />';
		wpforms_panel_field(
			'checkbox',
			$this->slug,
			'enable',
			$this->form_data,
			esc_html__( 'Enable EPFL Payonline payments', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => '0',
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'id_inst',
			$this->form_data,
			esc_html__( 'EPFL Payonline instance ID', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__('You must create a payment instance (entity that identifies your conference in the Payonline service) by using the "New Instance" link in the main menu on <a href="https://payonline.epfl.ch" target="_blank">payonline.epfl.ch</a>', 'wpforms-epfl-payonline' ),
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'email',
			$this->form_data,
			esc_html__( 'EPFL Payonline Email Address', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'Enter your EPFL Payonline address for the payment to be sent', 'wpforms-epfl-payonline' ),
			)
		);
		wpforms_panel_field(
			'select',
			$this->slug,
			'mode',
			$this->form_data,
			esc_html__( 'Mode', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => 'production',
				'options' => array(
					'production' => esc_html__( 'Production', 'wpforms-epfl-payonline' ),
					'test'       => esc_html__( 'Test / Sandbox', 'wpforms-epfl-payonline' ),
				),
				'tooltip' => esc_html__( 'Select Production to receive real payments or select Test to use the EPFL Payonline developer sandbox (id_inst=1234567890)', 'wpforms-epfl-payonline' ),
			)
		);
		wpforms_panel_field(
			'select',
			$this->slug,
			'transaction',
			$this->form_data,
			esc_html__( 'Payment Type', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => 'product',
				'options' => array(
					'product'  => esc_html__( 'Products and Services', 'wpforms-epfl-payonline' ),
					'donation' => esc_html__( 'Donation', 'wpforms-epfl-payonline' ),
				),
				'tooltip' => esc_html__( 'Select the type of payment you are receiving.', 'wpforms-epfl-payonline' ),
			)
		);
		wpforms_panel_field(
			'text',
			$this->slug,
			'cancel_url',
			$this->form_data,
			esc_html__( 'Cancel URL', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'tooltip' => esc_html__( 'Enter the URL to send users to if they do not complete the EPFL Payonline checkout', 'wpforms-epfl-payonline' ),
			)
		);
		wpforms_panel_field(
			'select',
			$this->slug,
			'shipping',
			$this->form_data,
			esc_html__( 'Shipping', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => '0',
				'options' => array(
					'1' => esc_html__( 'Don\'t ask for an address', 'wpforms-epfl-payonline' ),
					'0' => esc_html__( 'Ask for an address, but do not require', 'wpforms-epfl-payonline' ),
					'2' => esc_html__( 'Ask for an address and require it', 'wpforms-epfl-payonline' ),
				),
			)
		);
		wpforms_panel_field(
			'checkbox',
			$this->slug,
			'note',
			$this->form_data,
			esc_html__( 'Don\'t ask buyer to include a note with payment', 'wpforms-epfl-payonline' ),
			array(
				'parent'  => 'payments',
				'default' => '1',
			)
		);

		if ( function_exists( 'wpforms_conditional_logic' ) ) {
			wpforms_conditional_logic()->conditionals_block(
				array(
					'form'        => $this->form_data,
					'type'        => 'panel',
					'panel'       => 'epfl_payonline',
					'parent'      => 'payments',
					'actions'     => array(
						'go'   => esc_html__( 'Process', 'wpforms-epfl-payonline' ),
						'stop' => esc_html__( 'Don\'t process', 'wpforms-epfl-payonline' ),
					),
					'action_desc' => esc_html__( 'this charge if', 'wpforms-epfl-payonline' ),
					'reference'   => esc_html__( 'EPFL Payonline Standard payment', 'wpforms-epfl-payonline' ),
				)
			);
		} else {
			echo
				'<p class="note">' .
				sprintf(
					wp_kses(
						/* translators: %s - Addons page URL in admin area. */
						__( 'Install the <a href="%s">Conditional Logic addon</a> to enable conditional logic for EPFL Payonline payments.', 'wpforms-epfl-payonline' ),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					admin_url( 'admin.php?page=wpforms-addons' )
				) .
				'</p>';
		}
	}

	/**
	 * Add additional links for WPForms EPFL Payonline in the plugins list.
	 *
	 */
	function add_links_to_plugin_row( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( $this->plugin_name == $plugin_data['Name'] ) {
			$row_meta = array(
				'payonline'      => '<a href="' . esc_url( 'https://payonline.epfl.ch' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Payonline', 'wpforms-epfl-payonline' ) . '</a>',
				'help-payonline' => '<a href="' . esc_url( 'https://wiki.epfl.ch/payonline-help' ) . '" target="_blank" aria-label="' . esc_attr__( 'Plugin Additional Links', 'wpforms-epfl-payonline' ) . '">' . esc_html__( 'Help Payonline', 'wpforms-epfl-payonline' ) . '</a>'
			);
			return array_merge( $plugin_meta, $row_meta );
		}
		return (array) $plugin_meta;
	}

}

new WPForms_EPFL_Payonline();

