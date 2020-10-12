<?php
/**
 * AMP App Shell Helper Functions
 *
 * @package AmpAppShell
 */

/**
 * Handle activation of plugin.
 */
function amp_app_shell_activate() {
	if ( ! did_action( 'amp_app_shell_init' ) ) {
		amp_app_shell_init();
	}
}

/**
 * Bootstrap plugin.
 */
function amp_app_shell_bootstrap_plugin() {
	/*
	 * Register AMP App Shell scripts regardless of whether AMP is enabled or it
	 * is the AMP endpoint for the sake of being able to use AMP components on
	 * non-AMP documents. Do the registration as late as possible so that required
	 * polyfills and dependencies can be added to the AMP WP plugin scripts.
	 */
	add_action( 'wp_default_scripts', 'amp_app_shell_register_default_scripts', PHP_INT_MAX );

	add_action( 'amp_init', 'amp_app_shell_init' );

	// Ensure async is present on script tags.
	add_filter( 'script_loader_tag', 'amp_app_shell_filter_script_loader_tag', PHP_INT_MAX, 2 );
}

/**
 * Init AMP App Shell.
 */
function amp_app_shell_init() {
	/**
	 * Triggers on init when AMP App Shell plugin is active.
	 */
	do_action( 'amp_app_shell_init' );

	AMP_App_Shell_Service_Worker::init();
	AMP_App_Shell::init();
}

/**
 * Check if AMP App Shell is supported by a theme.
 *
 * @return bool True if app shell is supported by a theme.
 */
function is_amp_app_shell_supported() {
	return function_exists( 'current_theme_supports' ) && current_theme_supports( 'amp_app_shell' );
}

/**
 * Get the requested app shell component name.
 *
 * @return string|null Inner or outer shell, or null otherwise.
 */
function get_amp_app_shell_requested_component() {
	return AMP_App_Shell::get_requested_app_shell_component();
}

/**
 * Register default scripts for AMP App Shell components.
 *
 * @param WP_Scripts $wp_scripts Scripts.
 */
function amp_app_shell_register_default_scripts( $wp_scripts ) {
	// Add Web Components polyfill if Shadow DOM is not natively available.
	$wp_scripts->add_inline_script(
		'amp-shadow',
		sprintf(
			'if ( ! Element.prototype.attachShadow ) { const script = document.createElement( "script" ); script.src = %s; script.async = true; document.head.appendChild( script ); }',
			wp_json_encode( 'https://cdnjs.cloudflare.com/ajax/libs/webcomponentsjs/2.4.4/webcomponents-bundle.js' )
		),
		'after'
	);

	// App shell library.
	$handle         = 'amp-wp-app-shell';
	$url            = plugins_url( 'assets/js/' . $handle . '.js', AMP_APP_SHELL__FILE__ );
	$asset_file     = AMP_APP_SHELL__DIR__ . '/assets/js/' . $handle . '.asset.php';
	$asset          = require $asset_file;
	$dependencies   = $asset['dependencies'];
	$dependencies[] = 'amp-shadow';
	$version        = $asset['version'];

	$wp_scripts->add( $handle, $url, $dependencies, $version );

	$wp_scripts->add_data(
		$handle,
		'amp_script_attributes',
		[
			'async' => true,
		]
	);
}

/**
 * Add AMP script attributes to enqueued scripts.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 *
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string Script loader tag.
 */
function amp_app_shell_filter_script_loader_tag( $tag, $handle ) {
	$src        = wp_scripts()->registered[ $handle ]->src;
	$attributes = wp_scripts()->get_data( $handle, 'amp_script_attributes' );
	if ( empty( $attributes ) ) {
		return $tag;
	}

	// Add each attribute (if it hasn't already been added).
	foreach ( $attributes as $key => $value ) {
		if ( ! preg_match( ":\s$key(=|>|\s):", $tag ) ) {
			if ( true === $value ) {
				$attribute_string = sprintf( ' %s', esc_attr( $key ) );
			} else {
				$attribute_string = sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
			$tag = preg_replace(
				':(?=></script>):',
				$attribute_string,
				$tag,
				1
			);
		}
	}

	return $tag;
}

/**
 * Mark the beginning of the content that will be displayed inside the app shell.
 *
 * Depends on adding app_shell to the amp theme support args.
 *
 * @todo Should this take an argument for the content placeholder?
 */
function amp_start_app_shell_content() {
	if ( ! is_amp_app_shell_supported() ) {
		return;
	}

	printf( '<div id="%s">', esc_attr( AMP_App_Shell::CONTENT_ELEMENT_ID ) );

	// Start output buffering if requesting outer shell, since all content will be omitted from the response.
	if ( 'outer' === AMP_App_Shell::get_requested_app_shell_component() ) {
		$content_placeholder = '<p>' . esc_html__( 'Loading&hellip;', 'amp-app-shell' ) . '</p>';

		/**
		 * Filters the content which is shown in the app shell for the content before it is loaded.
		 *
		 * This is used to display a loading message or a content skeleton.
		 *
		 * @since 1.1
		 * @todo Consider using template part for this instead, or an action with a default.
		 *
		 * @param string $content_placeholder Content placeholder.
		 */
		echo apply_filters( 'amp_app_shell_content_placeholder', $content_placeholder ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped

		ob_start();
	}
}

/**
 * Mark the end of the content that will be displayed inside the app shell.
 *
 * Depends on adding app_shell to the amp theme support args.
 */
function amp_end_app_shell_content() {
	if ( ! is_amp_app_shell_supported() ) {
		return;
	}

	// Clean output buffer if requesting outer shell, since all content will be omitted from the response.
	if ( 'outer' === AMP_App_Shell::get_requested_app_shell_component() ) {
		ob_end_clean();
	}

	printf( '</div><!-- #%s -->', esc_attr( AMP_App_Shell::CONTENT_ELEMENT_ID ) );
}
