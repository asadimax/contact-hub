<?php
/**
 * Plugin Name:       Contact Hub
 * Author     :       Hossein asadi
 * Plugin URI :       https://asadionline.ir/
 * Description:       A fast, accessible floating contact widget: WhatsApp, Telegram, Eitaa, Bale, Rubika, Instagram, phone, email and more. No external requests, no tracking.
 * Version:           2.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       contact-hub
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACT_HUB_VERSION', '2.1.0' );
define( 'CONTACT_HUB_FILE', __FILE__ );
define( 'CONTACT_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTACT_HUB_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACT_HUB_DIR . 'includes/class-channels.php';
require_once CONTACT_HUB_DIR . 'includes/class-settings.php';
require_once CONTACT_HUB_DIR . 'includes/class-frontend.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( Contact_Hub_Settings::OPTION ) ) {
			add_option( Contact_Hub_Settings::OPTION, Contact_Hub_Settings::defaults() );
		}
	}
);

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'contact-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

Contact_Hub_Settings::init();
Contact_Hub_Frontend::init();
