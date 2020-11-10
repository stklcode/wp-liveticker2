<?php
/**
 * Liveticker
 *
 * @package     Liveticker
 * @author      Stefan Kalscheuer <stefan@stklcode.de>
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Liveticker (by stklcode)
 * Description: A simple Liveticker for WordPress.
 * Version:     1.2.0-alpha
 * Author:      Stefan Kalscheuer
 * Author URI:  https://www.stklcode.de
 * Text Domain: stklcode-liveticker
 * License:     GPLv2 or later
 *
 * Liveticker (by stklcode) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Liveticker (by stklcode) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Liveticker (by stklcode). If not, see http://www.gnu.org/licenses/gpl-2.0.html.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants.
define( 'SCLIVETICKER_FILE', __FILE__ );
define( 'SCLIVETICKER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCLIVETICKER_BASE', plugin_dir_url( __FILE__ ) );
define( 'SCLIVETICKER_BASENAME', plugin_basename( __FILE__ ) );

// System Hooks.
add_action( 'init', array( 'SCLiveticker\\SCLiveticker', 'register_types' ) );
add_action( 'plugins_loaded', array( 'SCLiveticker\\SCLiveticker', 'init' ) );
register_activation_hook( SCLIVETICKER_FILE, array( 'SCLiveticker\\System', 'activate' ) );
register_uninstall_hook( SCLIVETICKER_FILE, array( 'SCLiveticker\\System', 'uninstall' ) );

// Allow shortcodes in widgets.
add_filter( 'widget_text', 'do_shortcode' );

// Add shortcode.
add_shortcode( 'liveticker', array( 'SCLiveticker\\SCLiveticker', 'shortcode_ticker_show' ) );

// Add Widget.
add_action( 'widgets_init', array( 'SCLiveticker\\Widget', 'register' ) );

// Add Gutenberg block.
add_action( 'enqueue_block_editor_assets', array( 'SCLiveticker\\Admin', 'register_block' ) );

// Autoload.
spl_autoload_register( 'scliveticker_autoload' );

/**
 * Autoloader for Liveticker classes.
 *
 * @param string $class  Name of the class to load.
 *
 * @return void
 */
function scliveticker_autoload( $class ) {
	$plugin_classes = array(
		'SCLiveticker\\SCLiveticker',
		'SCLiveticker\\Admin',
		'SCLiveticker\\Api',
		'SCLiveticker\\System',
		'SCLiveticker\\Widget',
	);
	if ( in_array( $class, $plugin_classes, true ) ) {
		require_once sprintf(
			'%s/includes/class-%s.php',
			SCLIVETICKER_DIR,
			strtolower( str_replace( '_', '-', substr( $class, 13 ) ) )
		);
	}
}
