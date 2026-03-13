<?php
/**
 * Plugin Name: WPForms EPFL Payonline (saferpay)
 * Plugin URI:  https://github.com/epfl-si/wpforms-epfl-payonline
 * Description: EPFL Payonline integration with WPForms.
 * Author:      EPFL ISAS-FSD
 * Author URI:  https://go.epfl.ch/idev-fsd
 * Contributor: Nicolas Borboën <nicolas.borboen@epfl.ch>
 * Version:     2.11.0
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
define( 'WPFORMS_EPFL_PAYONLINE_VERSION', '2.11.0' );
// Plugin name.
define( 'WPFORMS_EPFL_PAYONLINE_NAME', 'WPForms EPFL Payonline (saferpay)' );
// Latest WP version tested with this plugin.
define( 'WP_LATEST_VERSION_WPFORMS_EPFL_PAYONLINE', '6.9' );
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

	load_textdomain( 'wpforms-epfl-payonline', plugin_dir_path( __FILE__ ) . 'languages/wpforms-epfl-payonline-fr_FR.mo' );

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

/**
 * This function customizes the default label, description, and consent text of the
 * GDPR checkbox field to align with EPFL data protection compliance.
 *
 * @link https://wpforms.com/developers/wpforms_field_new_default/
 */
function epfl_gdpr_checkbox_text( $field ) {

    if ( $field['type'] !== 'gdpr-checkbox' ) {
        return $field;
    }

    // default values
    $text = esc_html( 'By submitting this form, I consent to the processing of my personal data in compliance with the Federal Act on Data Protection (FADP) and, when applicable, with any other relevant legislation.' );
    $field['label'] = esc_html( 'FADP Agreement' );
    $field['description'] = esc_html( 'EPFL is required to respect the principles of data protection (https://go.epfl.ch/privacy-policy).' );
    $field['required'] = 1;
    if ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) ) {
        $field['choices'] = [
            [
                'label' => $text,
            ]
        ];
    }

    return $field;
}
add_filter( 'wpforms_field_new_default', 'epfl_gdpr_checkbox_text' );


/**
 * This function translates all field elements (labels, descriptions, sub-labels, choices, and countries) into French.
 * All the translations are in the wpforms-epfl-payonline-fr_FR.po file
 */
function epfl_translate_form_field( $properties, $field, $form_data ) {
    if ( function_exists( 'pll_current_language' ) && pll_current_language() !== 'fr' ) {
        return $properties;
    }

    // Translate field title
    if ( ! empty( $properties['label']['value'] ) ) {
        $properties['label']['value'] = esc_html__( trim( $properties['label']['value'] ), 'wpforms-epfl-payonline' );
    }

    if ( ! empty( $properties['description']['value'] ) ) {
        $properties['description']['value'] = esc_html__( trim( $properties['description']['value'] ), 'wpforms-epfl-payonline' );
    }

    if ( ! empty( $properties['inputs'] ) ) {
        foreach ( $properties['inputs'] as $key => $input ) {

       		// Translate checkbox options into French
            if ( ! empty( $input['label']['text'] ) ) {
                $properties['inputs'][$key]['label']['text'] = esc_html__( trim( $input['label']['text'] ), 'wpforms-epfl-payonline' );
            }

            if ( ! empty( $input['sublabel']['value'] ) ) {
                $properties['inputs'][$key]['sublabel']['value'] = esc_html__( trim( $input['sublabel']['value'] ), 'wpforms-epfl-payonline' );
            }

            // Translate dropdown country list
            if ( $key === 'country' && ! empty( $input['options'] ) ) {
                foreach ( $input['options'] as $iso => $name ) {
                    $translated = esc_html__( $name, 'wpforms-epfl-payonline' );
                    $properties['inputs']['country']['options'][$iso] = $translated;
                }
            }
        }
    }
    return $properties;
}
add_filter( 'wpforms_field_properties', 'epfl_translate_form_field', 20, 3 );

/**
 * This function translates the submit button text into French.
 */
function epfl_translate_submit_button( $form_data ) {
    if ( ! empty( $form_data['settings']['submit_text'] ) && pll_current_language() === 'fr' ) {
        $form_data['settings']['submit_text'] = esc_html__( trim( $form_data['settings']['submit_text']), 'wpforms-epfl-payonline' );
    }
    return $form_data;
}
add_filter( 'wpforms_frontend_form_data', 'epfl_translate_submit_button', 20, 1);

/**
 * This function translates form validation and error messages into French.
 */
function epfl_translate_error_messages ( $strings ) {

    if ( function_exists( 'pll_current_language' ) && pll_current_language() === 'fr' ) {
        $keys = [
            'val_required', 'val_email', 'val_email_suggestion', 'val_email_restricted',
            'val_number', 'val_number_positive', 'val_confirm', 'val_inputmask_incomplete',
            'val_checklimit', 'val_limit_characters', 'val_limit_words', 'val_url',
            'val_phone', 'val_fileextension', 'val_filesize', 'maxfilenumber',
            'val_time12h', 'val_time24h', 'val_time_limit', 'val_requiredpayment',
            'val_creditcard', 'val_post_max_size', 'val_password_strength',
            'val_unique', 'val_recaptcha_fail_msg'
        ];

        foreach ( $keys as $key ) {
            if ( isset( $strings[$key] ) ) {
                $strings[$key] = esc_html__( $strings[$key], 'wpforms-epfl-payonline' );
            }
        }
    }
    return $strings;
}
add_filter( 'wpforms_frontend_strings', 'epfl_translate_error_messages', 20, 1);
