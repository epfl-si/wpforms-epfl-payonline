<?php
/**
 * Plugin Name: WPForms EPFL Payonline (saferpay)
 * Plugin URI:  https://github.com/epfl-si/wpforms-epfl-payonline
 * Description: EPFL Payonline integration with WPForms.
 * Author:      EPFL ISAS-FSD
 * Author URI:  https://go.epfl.ch/idev-fsd
 * Contributor: Nicolas Borboën <nicolas.borboen@epfl.ch>
 * Version:     2.6.1
 * Text Domain: wpforms-epfl-payonline
 * Domain Path: languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
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

// Plugin version.
define( 'WPFORMS_EPFL_PAYONLINE_VERSION', '2.6.1' );
// Plugin name.
define( 'WPFORMS_EPFL_PAYONLINE_NAME', 'WPForms EPFL Payonline (saferpay)' );
// Latest WP version tested with this plugin.
define( 'WP_LATEST_VERSION_WPFORMS_EPFL_PAYONLINE', '6.3' );
// Minimal WP version required for this plugin.
define( 'WP_MIN_VERSION_WPFORMS_EPFL_PAYONLINE', '6.0.0' );

// Plugin Folder Path.
if ( ! defined( 'WPFORMS_EPFL_PAYONLINE_PLUGIN_DIR' ) ) {
	define( 'WPFORMS_EPFL_PAYONLINE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'WPFORMS_EPFL_PAYONLINE_PLUGIN_URL' ) ) {
	define( 'WPFORMS_EPFL_PAYONLINE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Load the payment class.
 */
function wpforms_epfl_payonline() {

	// WPForms Pro is required.
	if ( ! class_exists( 'WPForms_Pro' ) ) {
		return;
	}

	load_plugin_textdomain( 'wpforms-epfl-payonline', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	require_once plugin_dir_path( __FILE__ ) . 'class-wpforms-epfl-payonline.php';
	require_once plugin_dir_path( __FILE__ ) . 'class-wpforms-saferpay.php';
	require_once plugin_dir_path( __FILE__ ) . 'templates/class-epfl-conference-form-template.php';
	require_once plugin_dir_path( __FILE__ ) . 'templates/class-epfl-donation-form-template.php';
}
add_action( 'wpforms_loaded', 'wpforms_epfl_payonline' );

/* Load JS */
function load_epflpayonline_js() {
	wp_enqueue_script(
		'wpforms-epfl-payonline',
		WPFORMS_EPFL_PAYONLINE_PLUGIN_URL . 'assets/js/wpforms-epfl-payonline.js',
		array( 'wpforms-builder' ),
		WPFORMS_EPFL_PAYONLINE_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'load_epflpayonline_js' );


// WPForms requires WP_Filesystem() to be of ->method === "direct".
// For some reason (likely pertaining to our symlink scheme),
// WordPress' autodetection fails. This is equivalent to setting the
// `FS_METHOD` constant in `wp-confing.php`.
add_filter(
	'filesystem_method',
	function () {
		return 'direct';
	},
	10,
	3
);

/**
 * Limit maximum allowed to 4999 for a field using the class "set-maximum-to-4999".
 *
 * @link https://wpforms.com/fr/developers/how-to-set-minimum-amount-for-a-price-field/
 */

function set_maximum_to_4999( $field_id, $field_submit, $form_data ) {

	// This snippet will run for all forms
	$form_id = $form_data[ 'id' ];

	// And it will run for all fields with the CSS class of set-maximum-to-4999
	$fields  = $form_data[ 'fields' ];

	$maximum_amount = 4999;

	// Check if field has custom CSS class configured
	if ( !empty( $fields[ $field_id ][ 'css' ] ) ) {
		$classes = explode( ' ', $fields[ $field_id ][ 'css' ] );
		if ( in_array( 'set-maximum-to-4999', $classes ) ) {
			if ( $maximum_amount < (float) wpforms_sanitize_amount( $field_submit ) ) {
				$error_message = pll_current_language() == 'fr'
					? "Pour toute donation dès CHF 5'000.- nous vous invitons à contacter la Philanthropie (philanthropy@epfl.ch) qui vous accompagnera avec plaisir pour formaliser votre contribution."
					: "For any gift starting CHF 5,000, we kindly ask you to contact the Philanthropy team (philanthropy@epfl.ch) who will be happy to assist you in formalizing your donation.";
				wpforms()->process->errors[ $form_id ][ $field_id ] = $error_message;
				return;
			}
		}
	}
}
add_action( 'wpforms_process_validate_payment-single', 'set_maximum_to_4999', 10, 3 );

/**
* Change the error text message that appears.
*
* @link https://wpforms.com/developers/how-to-change-the-error-text-for-failed-submissions/
*/
function translate_message_failed_submission($translated_text, $text, $domain) {
	// Bail early if it's not a WPForms string.
	if ($domain !== 'wpforms-lite') {
		return $translated_text;
	}

	// Compare against the original string (in English).
	if (pll_current_language() == 'fr' & $text === 'Form has not been submitted, please see the errors below.') {
		$translated_text = __('Le formulaire n\'a pas été envoyé, veuillez consulter les erreurs ci-dessous.', 'wpforms');
	}

	return $translated_text;
}
add_filter('gettext', 'translate_message_failed_submission', 10, 3);
