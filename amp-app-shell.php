<?php
/**
 * Plugin Name: AMP App Shell for WordPress
 * Description: Support for app shell navigation via AMP Shadow DOM.
 * Plugin URI: https://github.com/xwp/amp-app-shell/
 * Author: AMP Project Contributors, XWP
 * Author URI: https://github.com/xwp/amp-app-shell/graphs/contributors
 * Version: 0.1.5
 * Text Domain: amp-app-shell
 * Domain Path: /languages/
 * License: GPLv2 or later
 * Requires at least: 4.9
 * Requires PHP: 5.6
 *
 * @package AmpAppShell
 */

define( 'AMP_APP_SHELL__FILE__', __FILE__ );
define( 'AMP_APP_SHELL__DIR__', dirname( __FILE__ ) );
define( 'AMP_APP_SHELL__VERSION', '0.1.5' );

/**
 * Errors encountered while loading the plugin.
 *
 * This has to be a global for the sake of PHP 5.2.
 *
 * @var WP_Error $_amp_app_shell_load_errors
 */
global $_amp_app_shell_load_errors;

$_amp_app_shell_load_errors = new WP_Error();

if ( version_compare( phpversion(), '5.6', '<' ) ) {
	$_amp_app_shell_load_errors->add(
		'insufficient_php_version',
		sprintf(
			/* translators: %s: required PHP version */
			__( 'The AMP App Shell plugin requires PHP %s. Please contact your host to update your PHP version.', 'amp-app-shell' ),
			'5.6+'
		)
	);
}

if ( ! file_exists( AMP_APP_SHELL__DIR__ . '/assets/js/amp-wp-app-shell.js' ) ) {
	$_amp_app_shell_load_errors->add(
		'build_required',
		sprintf(
			/* translators: %s: composer install && npm install && npm run build */
			__( 'You appear to be running the AMP App Shell plugin from source. Please do %s to finish installation.', 'amp-app-shell' ), // phpcs:ignore WordPress.Security.EscapeOutput
			'<code>composer install &amp;&amp; npm install &amp;&amp; npm run build</code>'
		)
	);
}

/**
 * Checks if required plugins are active.
 *
 * @global WP_Error $_amp_app_shell_load_errors
 */
function _amp_app_shell_check_dependecies() {
	global $_amp_app_shell_load_errors;

	if ( ! is_plugin_active( 'amp/amp.php' ) ) {
		$_amp_app_shell_load_errors->add(
			'amp_plugin_required',
			sprintf(
				/* translators: %s: <a href="https://wordpress.org/plugins/amp/" target="_blank">AMP</a> */
				__( 'It seems that %s plugin is not installed. Please install and activate the plugin to finish installation.', 'amp-app-shell' ), // phpcs:ignore WordPress.Security.EscapeOutput
				'<a href="https://wordpress.org/plugins/amp/" target="_blank">AMP</a>'
			)
		);
	}

	if ( ! is_plugin_active( 'pwa/pwa.php' ) ) {
		$_amp_app_shell_load_errors->add(
			'pwa_plugin_required',
			sprintf(
				/* translators: %s: <a href="https://wordpress.org/plugins/pwa/" target="_blank">PWA</a> */
				__( 'It seems that %s plugin is not installed. Please install and activate the plugin to finish installation.', 'amp-app-shell' ), // phpcs:ignore WordPress.Security.EscapeOutput
				'<a href="https://wordpress.org/plugins/pwa/" target="_blank">PWA</a>'
			)
		);
	}
}

add_action( 'admin_init', '_amp_app_shell_check_dependecies' );

/**
 * Checks if there are any load errors and displays admin notice if yes.
 *
 * @global WP_Error $_amp_app_shell_load_errors
 */
function _amp_app_shell_maybe_show_error_notices() {
	global $_amp_app_shell_load_errors;

	if ( ! empty( $_amp_app_shell_load_errors->errors ) ) {
		add_action( 'admin_notices', '_amp_app_shell_show_load_errors_admin_notice' );
	}
}

add_action( 'admin_init', '_amp_app_shell_maybe_show_error_notices' );

/**
 * Displays an admin notice about why the plugin is unable to load.
 *
 * @global WP_Error $_amp_app_shell_load_errors
 */
function _amp_app_shell_show_load_errors_admin_notice() {
	global $_amp_app_shell_load_errors;
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'AMP App Shell plugin unable to initialize.', 'amp-app-shell' ); ?></strong>
			<ul>
			<?php foreach ( array_keys( $_amp_app_shell_load_errors->errors ) as $error_code ) : ?>
				<?php foreach ( $_amp_app_shell_load_errors->get_error_messages( $error_code ) as $message ) : ?>
					<li>
						<?php echo wp_kses_post( $message ); ?>
					</li>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</ul>
		</p>
	</div>
	<?php
}

/**
 * Print admin notice if plugin installed with incorrect slug (which impacts WordPress's auto-update system).
 */
function _amp_app_shell_incorrect_plugin_slug_admin_notice() {
	$actual_slug = basename( AMP_APP_SHELL__DIR__ );
	?>
	<div class="notice notice-warning">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s is the current directory name, and %2$s is the required directory name */
					__( 'You appear to have installed the AMP App Shell plugin incorrectly. It is currently installed in the <code>%1$s</code> directory, but it needs to be placed in a directory named <code>%2$s</code>. Please rename the directory.', 'amp-app-shell' ),
					$actual_slug,
					'amp-app-shell'
				)
			);
			?>
		</p>
	</div>
	<?php
}

if ( 'amp-app-shell' !== basename( AMP_APP_SHELL__DIR__ ) ) {
	add_action( 'admin_notices', '_amp_app_shell_incorrect_plugin_slug_admin_notice' );
}

require_once AMP_APP_SHELL__DIR__ . '/includes/amp-app-shell-helper-functions.php';
require_once AMP_APP_SHELL__DIR__ . '/includes/class-amp-app-shell-service-worker.php';
require_once AMP_APP_SHELL__DIR__ . '/includes/class-amp-app-shell.php';

register_activation_hook( __FILE__, 'amp_app_shell_activate' );

amp_app_shell_bootstrap_plugin();
