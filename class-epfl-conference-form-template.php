<?php
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

			// Template name
			$this->name = 'Conference Form (EPFL Payonline)';

			// Template slug
			$this->slug = 'conference_form_epfl_payonline';

			// Template description
			$this->description = 'Collect Payments via EPFL Payonline for conferences payments with this ready-made form template. You can add and remove fields as needed.';

			// Template field and settings
			$this->data = array(
				'field_id' => '9',
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
					2 => array(
						'id'       => '2',
						'type'     => 'phone',
						'label'    => 'Phone',
						'format'   => 'us',
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
						'id'                   => '4',
						'type'                 => 'payment-multiple',
						'label'                => 'Conference fees',
						'choices'              => array(
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
						'description'          => 'Students will be asked to show their card at the conference check-in.',
						'required'             => '1',
						'choices_images_style' => 'modern',
					),
					7 => array(
						'id'                   => '7',
						'type'                 => 'payment-checkbox',
						'label'                => 'Evenning events',
						'choices'              => array(
							1 => array(
								'label' => 'Conference dinner (Wednesday evenning)',
								'value' => '100.00',
							),
							2 => array(
								'label' => 'Conference event (Thursday evenning)',
								'value' => '100.00',
							),
						),
						'choices_images_style' => 'modern',
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
						'size'        => 'large',
						'limit_count' => '1',
						'limit_mode'  => 'characters',
					),
				),
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
							'name'           => 'Default Confirmation',
							'type'           => 'message',
							'message'        => 'Thanks for contacting us! We will be in touch with you shortly.',
							'message_scroll' => '1',
							'page'           => '88',
						),
					),
				),
				'payments' => array(
					'epfl_payonline' => array(
						'id_inst'  => '',
						'email'    => '',
						'mode'     => 'production',
						'shipping' => '0',
						'note'     => '1',
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
