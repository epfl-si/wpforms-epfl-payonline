/* global wpforms_builder, wpforms_epfl_payonline */

/**
 * WPForms EPFL Standard function.
 *
 * @since 1.5.0
 */
'use strict';

var WPFormsEPFLPayonline = window.WPFormsEPFLPayonline || ( function( document, window, $ ) {

	// Enable or disable the form
	if ( $( '#wpforms-panel-field-epfl_payonline-enable' ).is(':checked') ) {
		$( '.wpforms-epfl_payonline-payment-settings-container' ).show();
	} else {
		$( '.wpforms-epfl_payonline-payment-settings-container' ).hide();
	}
	$( '#wpforms-panel-field-epfl_payonline-enable' ).on( "click", function() {
		$( '.wpforms-epfl_payonline-payment-settings-container' ).toggle();
	});

	// Display fields in function of the payment mode
	if ( $( '#wpforms-epfl-payonline-payment-mode' ).val() === 'manual' ) {
		$( '.wpforms-epfl-payonline-payment-mode-manual-container' ).show();
	} else {
		$( '.wpforms-epfl-payonline-payment-mode-manual-container' ).hide();
	}
	$( '#wpforms-epfl-payonline-payment-mode' ).on( "change", function() {
		if ( $( '#wpforms-epfl-payonline-payment-mode' ).val() === 'manual' ) {
			$( '.wpforms-epfl-payonline-payment-mode-manual-container' ).show();
		} else {
			$( '.wpforms-epfl-payonline-payment-mode-manual-container' ).hide();
		}
	});

}( document, window, jQuery ) );

// Initialize.
WPFormsEPFLPayonline.init();
