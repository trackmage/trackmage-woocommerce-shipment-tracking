<?php
/**
 * TrackMage for WordPress
 *
 * Easily integrate TrackMage with WordPress.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 *
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       TrackMage for WordPress
 * Plugin URI:        https://github.com/trackmage/trackmage-wordpress-plugin
 * Description:       Easily integrate TrackMage with WordPress.
 * Version:           0.1.0
 * Author:            TrackMage
 * Author URI:        https://trackmage.com
 * Text Domain:       trackmage
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/trackmage/trackmage-wordpress-plugin
 * Requires PHP:      5.6
 * Requires WP:       4.7
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( PHP_VERSION, '5.6', '<' ) ) {
	add_action( 'plugins_loaded', 'trackmage_init_deactivation' );

	/**
	 * Initialise deactivation functions.
	 */
	function trackmage_init_deactivation() {
		if ( current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_init', 'trackmage_deactivate' );
			add_action( 'admin_notices', 'trackmage_deactivation_notice' );
		}
	}

	/**
	 * Deactivate the plugin.
	 */
	function trackmage_deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	/**
	 * Show deactivation admin notice.
	 */
	function trackmage_deactivation_notice() {
		$notice = sprintf(
			// Translators: 1: Required PHP version, 2: Current PHP version.
			__( '<strong>TrackMage for WordPress</strong> requires PHP %1$s to run. This site uses %2$s, so the plugin has been <strong>deactivated</strong>.', 'trackmage' ),
			'5.6',
			PHP_VERSION
		);
		?>
		<div class="updated"><p><?php echo wp_kses_post( $notice ); ?></p></div>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	return false;
}

/**
 * Load plugin initialisation file.
 */
require plugin_dir_path( __FILE__ ) . '/init.php';
