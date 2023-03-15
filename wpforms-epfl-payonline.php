<?php
/**
 * Plugin Name: WPForms EPFL Payonline
 * Plugin URI:  https://github.com/epfl-si/wpforms-epfl-payonline
 * Description: EPFL Payonline integration with WPForms.
 * Author:      EPFL ISAS-FSD
 * Author URI:  https://go.epfl.ch/idev-fsd
 * Contributor: Nicolas Borboën <nicolas.borboen@epfl.ch>
 * Version:     1.7.3
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
define( 'WPFORMS_EPFL_PAYONLINE_VERSION', '1.7.3' );
// Plugin name.
define( 'WPFORMS_EPFL_PAYONLINE_NAME', 'WPForms EPFL Payonline' );
// Latest WP version tested with this plugin.
define( 'WP_LATEST_VERSION_WPFORMS_EPFL_PAYONLINE', '6.1.1' );
// Minimal WP version required for this plugin.
define( 'WP_MIN_VERSION_WPFORMS_EPFL_PAYONLINE', '5.0' );

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
	require_once plugin_dir_path( __FILE__ ) . 'class-epfl-conference-form-template.php';

}

add_action( 'wpforms_loaded', 'wpforms_epfl_payonline' );

// WPForms requires WP_Filesystem() to be of ->method === "direct".
// For some reason (likely pertaining to our symlink scheme),
// WordPress' autodetection fails. This is equivalent to setting the
// `FS_METHOD` constant in `wp-confing.php`.
add_filter(
    'filesystem_method',
    function() {
        return 'direct';
    },
    10,
    3
);
