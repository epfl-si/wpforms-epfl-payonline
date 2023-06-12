/* global wpforms_builder, wpforms_epfl_payonline */

/**
 * WPForms Paypal Standard function.
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

}( document, window, jQuery ) );

// Initialize.
WPFormsEPFLPayonline.init();
