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
 * This saferpay add-on need some "wp_options" values to be set in order that
 * the "payment mode" Test and Production work.
 * It can be done with :
 * $ wp option add wpforms-epfl-payonline-saferpay-apiusername-test API_123456_12345678
 * $ wp option add wpforms-epfl-payonline-saferpay-apipassword-test AverySecretPassword
 * $ wp option add wpforms-epfl-payonline-saferpay-customerid-test 123456
 * $ wp option add wpforms-epfl-payonline-saferpay-terminalid-test 12345678
 *
 * The "Production" is similar with `-prod` at the end.
 *
 **/

class SaferpayPayment { // extends WPForms_Payment 

	const SpecVersion = '1.32';

	public $payment_settings;
	public $payonline_mode;
	public $payment_data;
	public $wpforms_data;
	public $payonline_saferpay_apiusername;
	public $payonline_saferpay_apipassword;
	public $payonline_saferpay_customerid;
	public $payonline_saferpay_terminalid;

	public function __construct($payment_settings, $payment_data, $wpforms_data) {
		$this->payment_settings = $payment_settings;
		$this->payment_data = $payment_data;
		$this->wpforms_data = $wpforms_data;
		$this->payonline_mode = $payment_settings['payonline_mode'];
		$this->payment_reconciliation_code = $payment_settings['payment_reconciliation_code'];
		$this->payment_description = $payment_settings['payment_description'];

		error_log("XXX PAYONLINE MODE IS " . $this->payonline_mode);
		error_log(var_export($payment_settings, true));
		error_log("\n\n xxxxxxxxxxxxxxxxxxxxxxxxxx");

		if ( $this->payonline_mode === 'manual' ) {
			$this->payonline_saferpay_apiusername = $payment_settings['saferpay_api_username'];
			$this->payonline_saferpay_apipassword = $payment_settings['saferpay_api_password'];
			$this->payonline_saferpay_customerid = $payment_settings['saferpay_customer_id'];
			$this->payonline_saferpay_terminalid = $payment_settings['saferpay_terminal_id'];
		} else if ( $this->payonline_mode === 'test' ) {
			$this->payonline_saferpay_apiusername = get_option( 'wpforms-epfl-payonline-saferpay-apiusername-test' );
			$this->payonline_saferpay_apipassword = get_option( 'wpforms-epfl-payonline-saferpay-apipassword-test' );
			$this->payonline_saferpay_customerid = get_option( 'wpforms-epfl-payonline-saferpay-customerid-test' );
			$this->payonline_saferpay_terminalid = get_option( 'wpforms-epfl-payonline-saferpay-terminalid-test' );
		} else if ( $this->payonline_mode === 'production' ) {
			$this->payonline_saferpay_apiusername = get_option( 'wpforms-epfl-payonline-saferpay-apiusername-prod' );
			$this->payonline_saferpay_apipassword = get_option( 'wpforms-epfl-payonline-saferpay-apipassword-prod' );
			$this->payonline_saferpay_customerid = get_option( 'wpforms-epfl-payonline-saferpay-customerid-prod' );
			$this->payonline_saferpay_terminalid = get_option( 'wpforms-epfl-payonline-saferpay-terminalid-prod' );
		}

	}

	private function postJSONData($url, $data) {
		$basic_auth = base64_encode($this->payonline_saferpay_apiusername . ":" . $this->payonline_saferpay_apipassword );
		// error_log("POST AUTH");
		// error_log(var_export($basic_auth, true));
		// error_log("POST DATA");
		// error_log(var_export($data, true));
		$options = array(
			'http' => array(
				'ignore_errors' => true, // Note: without this line all 400ish status will be ignored! https://stackoverflow.com/a/11479968/960623
				'header'  => "Content-Type: application/json\r\n"
									 . "Authorization: Basic $basic_auth\r\n",
				'method'  => 'POST',
				'content' => json_encode($data)
			)
		);
		error_log("POST OPTIONS");
		error_log(var_export(json_encode($data), true));
		$context  = stream_context_create($options);
		$json_result = file_get_contents($url, false, $context);


		if ($json_result !== false) {
			$result = json_decode($json_result);
			//error_log(var_export($result, true));
			if (property_exists( $result, 'ErrorName' ) ) {
				// Something has failed
				error_log('POSTING DATA TO SAFERPAY FAILED!');
				foreach ($result->ErrorDetail as $msg) {
					error_log('  ' . $result->ErrorName . ' (' . $result->ErrorMessage . "): " . $msg);
				}
			} else {
				error_log("POST RESULTS");
				error_log(var_export(json_decode($json_result), true));
				return $result;
			}
		} else {
			// Something went wrong!
			error_log("Error posting data!");
		}
		return false;
	}

	/**
	 * PaymentPageInitialize — This method can be used to start a transaction with 
	 * the Payment Page which may involve either DCC and / or 3d-secure
	 *
	 * https://saferpay.github.io/jsonapi/#Payment_v1_PaymentPage_Initialize
	 */
	public function paymentPageInitialize() {

		// TODO: validate URL ?
		
		$my_entry_id = str_pad($this->wpforms_data['entry_id'], 6, "0", STR_PAD_LEFT);
		$data = array(
			// mandatory
			"RequestHeader" => array(
				// mandatory
				"SpecVersion" => self::SpecVersion,
				// mandatory
				"CustomerId" => $this->payonline_saferpay_customerid,
				// mandatory
				"RequestId" => $this->wpforms_data['entry_id'] . ':' . date("Y-m-d_H:i:s"), // TODO test a better payment identifier (should be unique)
				// mandatory
				"RetryIndicator" => 0
			),
			// mandatory
			"TerminalId" => $this->payonline_saferpay_terminalid,
			// mandatory
			"Payment" => array (
				// mandatory
				"Amount" => array (
					// mandatory Amount in minor unit (CHF 1.00 ⇒ Value=100). Only Integer values will be accepted!
					"Value" => $this->payment_data['amount']*100,
					// mandatory ISO 4217 3-letter currency code
					"CurrencyCode" => strtoupper($this->payment_data['currency']), // TODO: use the wproms settings
				),
				// recommanded Unambiguous order identifier defined by the merchant / shop. This identifier might be used as reference later on. Max 80
				"OrderId" => $this->payment_reconciliation_code, // "NoSpaceOrSpecial" . bin2hex(openssl_random_pseudo_bytes(32)), // "wpf-" . $this->wpforms_data['entry_id'],
				"PayerNote" => "EPFL WPFORMS 50 " . bin2hex(openssl_random_pseudo_bytes(17)), // Max 50 // TODO: change me
				// mandatory A human readable description provided by the merchant that will be displayed in Payment Page.
				"Description" => $this->payment_description
			),
			"ReturnUrl" => array(
				"Url" => get_site_url() . "?EPFLPayonline&entry_id=" . $my_entry_id
			),
			"RedirectNotifyUrls" => array(
				"Success" => get_site_url() . "?EPFLPayonline&status=Success&entry_id=" . $my_entry_id,
				"Fail" => get_site_url() . "?EPFLPayonline&status=Fail&entry_id=" . $my_entry_id
			),
			// Information about the caller (merchant host)
			"ClientInfo" => array(
				"ShopInfo" => "epfl_wpforms v" . WPFORMS_EPFL_PAYONLINE_VERSION ?: "epfl_wpforms", // Name and version of the shop software
				"OsInfo" => "epfl_wp_base" //Information on the operating system
			),
			"Payer" => array(
				"LanguageCode" => "en",
				"BillingAddress" => array(
					"FirstName" => $this->payment_data['FirstName'], // The payer's first name
					"LastName" => $this->payment_data['LastName'], // The payer's last name
					// // "Company" => '', // The payer's company
					// // "LegalForm" => '', //The payer's legal form (AG, GmbH, Misc.)
					// // "Gender" => '', // The payer's gender
					"Street" => $this->payment_data['Street'] ?? 'no address1', // The payer's street
					"Street2" => $this->payment_data['Street2'] ?? 'no address2', // The payer's street, second line. Only use this, if you need two lines. It may not be supported by all acquirers.
					"Zip" => $this->payment_data['Zip'] ?? '0000', // The payer's zip code
					"City" => $this->payment_data['City'] ?? 'no city', // The payer's city
					//"CountrySubdivisionCode" => $this->payment_data['CountrySubdivisionCode'] ?? 'no state', // The payer's country subdivision code (Canton)
					"CountryCode" => $this->payment_data['CountryCode'] ?? 'XX', // The payer's country code (ISO 3166-1 alpha-2 country code)
					"Email" => $this->payment_data['Email'] ?? 'no email', // The payer's email address
					// "DateOfBirth" => '', // The payer's date of birth in ISO 8601 extended date notation (YYYY-MM-DD)
					// "Phone" => '', // The payer's phone number
					// // "VatNumber" => '', // The company's vat number
					// // "Id" => '', // Payer identifier defined by the merchant / shop. Use a unique id for your customer (a UUID is highly recommended). For GDPR reasons, we don't recommend using an id which contains personal data (eg. no name).
					// // "DeliveryAddress" => array()
				),
			),
			// "Notification" => array(
			// 	"MerchantEmails" => array('nicolas.borboen+merchantEmail@epfl.ch'),
			// 	"PayerEmail" => $this->payment_data['Email']
			// )
		);

		// The test URL is https://test.saferpay.com/api/Payment/v1/PaymentPage/Initialize
		$url = $this->payment_settings['saferpay_api_url'] . '/api/Payment/v1/PaymentPage/Initialize';
		$payment_init_result = self::postJSONData($url, $data);

		if ($payment_init_result->Token &&
				$payment_init_result->Expiration &&
				$payment_init_result->RedirectUrl) {
			return $payment_init_result;
		} else {
			return false;
		}
	}

	/**
	 * PaymentPage_Assert — Call this function to safely check the status of the 
	 * transaction from your server.
	 *
	 * https://saferpay.github.io/jsonapi/#Payment_v1_PaymentPage_Assert
	 */
	public function paymentPageAssert($token, $request_id) {
		// https://test.saferpay.com/api//Payment/v1/PaymentPage/Assert
		$url = $this->payment_settings['saferpay_api_url'] . '/api/Payment/v1/PaymentPage/Assert';
		$data = array( 
			"RequestHeader" => array(
				"SpecVersion" => self::SpecVersion,
					"CustomerId" => $this->payonline_saferpay_customerid,
				"RequestId" => $request_id,
				"RetryIndicator" => 0
			),
			"Token" => $token
		);
		$assert = self::postJSONData($url, $data);
		if ($assert) {
			return $assert;
		} else {
			return false;
		}
	}

	/**
	 * Not sure yet (https://docs.saferpay.com/home/integration-guide/licences-and-interfaces/capture-and-daily-closing)
	 */
	public function paymentCapture($transaction_id, $request_id) {
		// https://saferpay.github.io/jsonapi/index.html#Payment_v1_Transaction_Capture
		$url = $this->payment_settings['saferpay_api_url'] . '/api/Payment/v1/Transaction/Capture';
		$data = array(
			"RequestHeader" => array(
				"SpecVersion" => self::SpecVersion,
				"CustomerId" => $this->payonline_saferpay_customerid,
				"RequestId" => $request_id,
				"RetryIndicator" => 0
			),
			"TransactionReference" => array(
				"TransactionId" => $transaction_id
			)
		);
		$capture = self::postJSONData($url, $data);
		if ($capture) {
			return $capture;
		} else {
			return false;
		}
	}

}
