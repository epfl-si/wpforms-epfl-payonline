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

if ( class_exists( 'WPForms_Template', false ) ) :
	/**
	 * EPFL Conference Form Template
	 * Template for WPForms.
	 */
	class EPFL_Conference_Form_Template extends WPForms_Template {

		/**
		 * Primary class constructor.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Template name.
			$this->name = 'EPFL Conference Form Template';

			// Template slug.
			$this->slug = 'conference_form_new_template';

			// Template description.
			$this->description = 'Template for EPFL conferences with payments (via EPFL Payonline).';

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
						'id'            => '1',
						'type'          => 'email',
						'label'         => 'Email',
						'required'      => '1',
						'size'          => 'large',
						'default_value' => false,
					),
					2 => array(
						'id'       => '2',
						'type'     => 'phone',
						'label'    => 'Phone',
						'format'   => 'international',
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
					8 => array(
						'id'                   => '8',
						'type'                 => 'checkbox',
						'label'                => 'Diets',
						'choices'              => array(
							1 => array(
								'label'      => 'Gluten-Free',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							2 => array(
								'label'      => 'Lactose-Free',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							4 => array(
								'label'      => 'Vegetarian',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							5 => array(
								'label'      => 'Veganism',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
						),
						'choices_images_style' => 'modern',
						'choices_icons_color'  => '#0399ed',
						'choices_icons_size'   => 'large',
						'choices_icons_style'  => 'default',
						'description'          => 'Please indicate your food preferences and we will do our best to offer adapted meals at the conference.',
						'input_columns'        => '2',
					),
					4 => array(
						'id'                      => '4',
						'type'                    => 'payment-multiple',
						'label'                   => 'Conference fees',
						'choices'                 => array(
							1 => array(
								'label'      => 'Students',
								'value'      => '100.00',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							2 => array(
								'label'      => 'Speakers',
								'value'      => '200.00',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							3 => array(
								'label'      => 'Other attendees',
								'value'      => '250.00',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
						),
						'show_price_after_labels' => '1',
						'choices_images_style'    => 'modern',
						'choices_icons_color'     => '#0399ed',
						'choices_icons_size'      => 'large',
						'choices_icons_style'     => 'default',
						'description'             => 'Students will be asked to show their card at the conference check-in.',
						'required'                => '1',
					),
					7 => array(
						'id'                      => '7',
						'type'                    => 'payment-checkbox',
						'label'                   => 'Evenning events',
						'choices'                 => array(
							1 => array(
								'label'      => 'Conference dinner (Wednesday evenning)',
								'value'      => '100.00',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
							2 => array(
								'label'      => 'Conference event (Thursday evenning)',
								'value'      => '100.00',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
						),
						'show_price_after_labels' => '1',
						'choices_images_style'    => 'modern',
						'choices_icons_color'     => '#0399ed',
						'choices_icons_size'      => 'large',
						'choices_icons_style'     => 'default',
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
						'size'        => 'small',
						'limit_count' => '1',
						'limit_mode'  => 'characters',
					),
					9 => array(
						'id'          => '9',
						'type'        => 'gdpr-checkbox',
						'required'    => '1',
						'label'       => 'GDPR Agreement',
						'choices'     => array(
							1 => array(
								'label'      => 'By checking this box, I consent to the use of my personal data by EPFL for the purpose of processing my application for this conference (https://go.epfl.ch/lpd).',
								'icon'       => 'face-smile',
								'icon_style' => 'regular',
							),
						),
						'description' => 'EPFL is required to respect the principles of data protection (https://go.epfl.ch/privacy-policy).',
					),
				),
				'field_id' => 10,
				'settings' => array(
					'form_title'             => 'EPFL Conference Form Template',
					'submit_text'            => 'Submit',
					'submit_text_processing' => 'Sending...',
					'notification_enable'    => '1',
					'notifications'          => array(
						1 => array(
							'notification_name' => 'Default Notification',
							'email'             => 'your.email@epfl.ch',
							'carboncopy'        => 'another.email@epfl.ch',
							'subject'           => 'Someone just submitted your form...',
							'sender_name'       => 'Demo conference',
							'sender_address'    => 'demo.conference@epfl.ch',
							'replyto'           => 'demo.conference@epfl.ch',
							'message'           => '{all_fields}',
							'file_upload_attachment_fields' => array(),
							'entry_csv_attachment_entry_information' => array(),
							'entry_csv_attachment_file_name' => 'entry-details',
						),
					),
					'confirmations'          => array(
						1 => array(
							'name'                        => 'Default Confirmation',
							'type'                        => 'message',
							'message'                     => 'Thanks for contacting us! We will be in touch with you shortly.',
							'message_scroll'              => '1',
							'page'                        => '10',
							'message_entry_preview_style' => 'basic',
						),
					),
					'honeypot'               => '1',
					'anti_spam'              => array(
						'country_filter' => array(
							'action'        => 'allow',
							'country_codes' => array(),
							'message'       => 'Sorry, this form does not accept submissions from your country.',
						),
						'keyword_filter' => array(
							'message' => 'Sorry, your message can\'t be submitted because it contains prohibited words.',
						),
					),
					'form_tags'              => array(),
				),
				'payments' => array(
					'epfl_payonline' => array(
						'enable'  => '1',
						'id_inst' => '1234567890',
						'email'   => 'conference.admin@groupes.epfl.ch',
					),
				),
				'meta'     => array(
					'template' => 'conference_form_new_template',
				),
			);
		}
	}

	new EPFL_Conference_Form_Template();
endif;
