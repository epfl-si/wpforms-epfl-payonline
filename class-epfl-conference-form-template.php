<?php
/**
 * EPFL Template of a conference form using EPFL Payonline
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

if ( class_exists( 'WPForms_Template', false ) ) :
	/**
	 * Conference Form (EPFL Payonline)
	 * Template for WPForms.
	 */
	class EPFL_Conference_Form_Template extends WPForms_Template {


		/**
		 * Primary class constructor.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Allow to add this template in the top part of the template page.
			$this->core = true;

			// Template name.
			$this->name = 'Conference Form (EPFL Payonline)';

			// Template slug.
			$this->slug = 'conference_form_epfl_payonline';

			// Template description.
			$this->description = 'Collect Payments via EPFL Payonline for conferences payments with this ready-made form template. You can add and remove fields as needed.';

			// Template field and settings.
			$this->data = array(
				'fields'   => array(
					0 => array(
						'id'       => '0',
						'type'     => 'name',
						'label'    => 'Name',
						'format'   => 'first-last',
						'required' => '1',
						'size'     => 'large',
					),
					1 => array(
						'id'       => '1',
						'type'     => 'email',
						'label'    => 'Email',
						'required' => '1',
						'size'     => 'large',
					),
					3 => array(
						'id'              => '3',
						'type'            => 'address',
						'label'           => 'Address',
						'scheme'          => 'international',
						'required'        => '1',
						'size'            => 'large',
						'country_default' => 'CH',
					),
					2 => array(
						'id'       => '2',
						'type'     => 'phone',
						'label'    => 'Phone',
						'format'   => 'smart',
						'required' => '1',
						'size'     => 'large',
					),
					8 => array(
						'id'                   => '8',
						'type'                 => 'checkbox',
						'label'                => 'Diets',
						'choices'              => array(
							1 => array(
								'label' => 'Gluten-Free',
							),
							2 => array(
								'label' => 'Lactose-Free',
							),
							3 => array(
								'label' => 'Semi-vegetarian',
							),
							4 => array(
								'label' => 'Vegetarian',
							),
							5 => array(
								'label' => 'Veganism',
							),
						),
						'description'          => 'Please indicate your food preferences and we will do our best to offer adapted meals at the conference.',
						'choices_images_style' => 'modern',
						'input_columns'        => 'inline',
					),
					4 => array(
						'id'                      => '4',
						'type'                    => 'payment-multiple',
						'label'                   => 'Conference fees',
						'choices'                 => array(
							1 => array(
								'label' => 'Student',
								'value' => '100.00',
							),
							2 => array(
								'label' => 'Speakers',
								'value' => '200.00',
							),
							3 => array(
								'label' => 'Other attendees',
								'value' => '250.00',
							),
						),
						'show_price_after_labels' => '1',
						'description'             => 'Students will be asked to show their card at the conference check-in.',
						'required'                => '1',
						'choices_images_style'    => 'modern',
					),
					7 => array(
						'id'                      => '7',
						'type'                    => 'payment-checkbox',
						'label'                   => 'Evenning events',
						'choices'                 => array(
							1 => array(
								'label' => 'Conference dinner (Wednesday evenning)',
								'value' => '100.00',
							),
							2 => array(
								'label' => 'Conference event (Thursday evenning)',
								'value' => '100.00',
							),
						),
						'show_price_after_labels' => '1',
						'choices_images_style'    => 'modern',
					),
					5 => array(
						'id'    => '5',
						'type'  => 'payment-total',
						'label' => 'Total Amount',
					),
					6 => array(
						'id'          => '6',
						'type'        => 'textarea',
						'label'       => 'Comment or Message',
						'required'    => '1',
						'size'        => 'small',
						'limit_count' => '1',
						'limit_mode'  => 'characters',
					),
					9 => array(
						'id'       => '9',
						'type'     => 'gdpr-checkbox',
						'required' => '1',
						'label'    => 'GDPR Agreement',
						'choices'  => array(
							1 => array(
								'label' => 'I consent to having this website store my submitted information so they can respond to my inquiry. Head to <a href="https://www.epfl.ch/about/presidency/presidents-team/legal-affairs/epfl-privacy-policy/">EPFL Privacy Policy (https://www.epfl.ch/about/presidency/presidents-team/legal-affairs/epfl-privacy-policy/</a>) for details.',
							),
						),
					),
				),
				'field_id' => 10,
				'settings' => array(
					'form_title'             => 'Conference Form (EPFL Payonline)',
					'form_desc'              => 'Collect Payments via EPFL Payonline for conferences payments with this ready-made form template. You can add and remove fields as needed.',
					'submit_text'            => 'Submit',
					'submit_text_processing' => 'Sending...',
					'honeypot'               => '1',
					'notification_enable'    => '1',
					'notifications'          => array(
						1 => array(
							'notification_name' => 'Default Notification',
							'email'             => '{admin_email}',
							'subject'           => 'New Entry: Billing / Order Form with Payonline',
							'sender_name'       => 'EPFL IDEV FSD',
							'sender_address'    => '{admin_email}',
							'message'           => '{all_fields}',
						),
					),
					'confirmations'          => array(
						1 => array(
							'name'                        => 'Default Confirmation',
							'type'                        => 'message',
							'message'                     => '<p>Thanks for contacting us! We will be in touch with you shortly.</p>',
							'message_scroll'              => '1',
							'page'                        => '2',
							'message_entry_preview_style' => 'basic',
						),
					),
				),
				'payments' => array(
					'epfl_payonline' => array(
						'enable'  => '1',
						'id_inst' => '1234567890',
						'email'   => '',
					),
				),
				'meta'     => array(
					'template' => 'conference_form_epfl_payonline',
				),
			);
		}
	}
	new EPFL_Conference_Form_Template();
endif;
