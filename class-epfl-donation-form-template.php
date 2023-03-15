<?php
/**
 * EPFL Template of a donation form using EPFL Payonline
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

if ( class_exists( 'WPForms_Template', false ) ) :
/**
* EPFL Donation Form Template
* Template for WPForms.
*/
class EPFL_Donation_Form_Template extends WPForms_Template {
	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Template name
		$this->name = 'EPFL Donation Form Template';
		// Template slug
		$this->slug = 'epfl_donation_form_example';
		// Template description
		$this->description = 'Template for EPFL donation (via EPFL Payonline).';
		// Template field and settings
		$this->data = array (
	'fields' => array (
		0 => array (
			'id' => '0',
			'type' => 'name',
			'label' => 'Name',
			'format' => 'first-last',
			'required' => '1',
			'size' => 'large',
		),
		5 => array (
			'id' => '5',
			'type' => 'address',
			'label' => 'Address',
			'scheme' => 'international',
			'required' => '1',
			'size' => 'large',
			'country_default' => 'CH',
		),
		1 => array (
			'id' => '1',
			'type' => 'email',
			'label' => 'Email',
			'required' => '1',
			'size' => 'large',
			'default_value' => false,
		),
		4 => array (
			'id' => '4',
			'type' => 'payment-single',
			'label' => 'Donation',
			'format' => 'user',
			'required' => '1',
			'size' => 'large',
		),
		2 => array (
			'id' => '2',
			'type' => 'textarea',
			'label' => 'Comment or Message',
			'size' => 'small',
			'limit_count' => '1',
			'limit_mode' => 'characters',
		),
		3 => array (
			'id' => '3',
			'type' => 'payment-total',
			'label' => 'Total',
		),
	),
	'field_id' => 6,
	'settings' => array (
		'form_title' => 'EPFL Donation Form Template',
		'submit_text' => 'Donate',
		'submit_text_processing' => 'Sending...',
		'dynamic_population' => '1',
		'ajax_submit' => '1',
		'notification_enable' => '1',
		'notifications' => array (
			1 => array (
				'notification_name' => 'Default Notification',
				'email' => 'your.email@epfl.ch',
				'carboncopy' => 'another.email@epfl.ch',
				'subject' => 'New Entry: Donation Form',
				'sender_name' => 'Donation Form',
				'sender_address' => 'demo.donation@epfl.ch',
				'replyto' => 'demo.donation@epfl.ch',
				'message' => '{all_fields}',
				'file_upload_attachment_fields' => array (
				),
				'entry_csv_attachment_entry_information' => array (
				),
				'entry_csv_attachment_file_name' => 'entry-details',
			),
		),
		'confirmations' => array (
			1 => array (
				'name' => 'Default Confirmation',
				'type' => 'message',
				'message' => '<p>Thanks for contacting us! We will be in touch with you shortly.</p>',
				'message_scroll' => '1',
				'page' => '10',
				'message_entry_preview_style' => 'basic',
			),
		),
		'antispam' => '1',
		'anti_spam' => array (
			'country_filter' => array (
				'action' => 'allow',
				'country_codes' => array (
				),
				'message' => 'Sorry, this form does not accept submissions from your country.',
			),
			'keyword_filter' => array (
				'message' => 'Sorry, your message can\'t be submitted because it contains prohibited words.',
			),
		),
		'form_tags' => array (
		),
	),
	'payments' => array (
		'epfl_payonline' => array (
			'enable' => '1',
			'id_inst' => '1234567890',
			'email' => 'donation.admin@groupes.epfl.ch',
		),
	),
	'meta' => array (
		'template' => 'epfl_donation_form_example',
	),
);
	}
}
new EPFL_Donation_Form_Template();
endif;
