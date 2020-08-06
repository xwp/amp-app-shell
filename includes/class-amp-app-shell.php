<?php
/**
 * Class AMP_App_Shell
 *
 * @package AmpAppShell
 */

/**
 * Class AMP_App_Shell
 */
class AMP_App_Shell {

	/**
	 * Query var for requesting the inner or outer app shell.
	 *
	 * @var string
	 */
	const COMPONENT_QUERY_VAR = 'amp_app_shell_component';

	/**
	 * ID for element that contains the content for app shell.
	 *
	 * @var string
	 */
	const CONTENT_ELEMENT_ID = 'amp-app-shell-content';

	/**
	 * App shell component type (inner or outer).
	 *
	 * @var string
	 */
	public static $app_shell_component = '';

	/**
	 * Init app shell.
	 */
	public static function init() {
		$theme_support = AMP_Theme_Support::get_theme_support_args();
		if ( ! isset( $theme_support['app_shell'] ) ) {
			return;
		}

		$requested_app_shell_component = self::get_requested_app_shell_component();

		// When inner app shell is requested, it is always an AMP request. Do not allow AMP when getting outer app shell for now (but this should be allowed in the future).
		if ( 'outer' === $requested_app_shell_component ) {
			add_action(
				'template_redirect',
				function() {
					if ( ! is_amp_endpoint() ) {
						return;
					}
					wp_die(
						esc_html__( 'Outer app shell can only be requested of the non-AMP version (thus requires Transitional mode).', 'amp-app-shell' ),
						esc_html__( 'AMP Outer App Shell Problem', 'amp-app-shell' ),
						[ 'response' => 400 ]
					);
				}
			);
		}

		// @todo This query param should be standardized and then this can be handled in the same place as WP_Service_Worker_Navigation_Routing_Component::filter_title_for_streaming_header().
		if ( 'outer' === $requested_app_shell_component ) {
			add_filter(
				'pre_get_document_title',
				function() {
					return __( 'Loading...', 'amp-app-shell' );
				}
			);
		}

		// Enqueue scripts for (outer) app shell, including precached app shell and normal site navigation prior to service worker installation.
		if ( 'inner' !== $requested_app_shell_component ) {
			add_action(
				'wp_enqueue_scripts',
				function() use ( $requested_app_shell_component ) {
					if ( is_amp_endpoint() ) {
						return;
					}
					wp_enqueue_script( 'amp-shadow' );
					wp_enqueue_script( 'amp-wp-app-shell' );

					$exports = [
						'contentElementId'  => self::CONTENT_ELEMENT_ID,
						'homeUrl'           => home_url( '/' ),
						'adminUrl'          => admin_url( '/' ),
						'componentQueryVar' => self::COMPONENT_QUERY_VAR,
						'isOuterAppShell'   => 'outer' === $requested_app_shell_component,
					];
					wp_add_inline_script( 'amp-wp-app-shell', sprintf( 'var ampAppShell = %s;', wp_json_encode( $exports ) ), 'before' );
				}
			);
		}
	}

	/**
	 * Remove app shell query var that comes in requests.
	 */
	public static function purge_app_shell_query_var() {
		if ( ! isset( $_GET[ self::COMPONENT_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		self::$app_shell_component = wp_unslash( $_GET[ self::COMPONENT_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_REQUEST[ self::COMPONENT_QUERY_VAR ], $_GET[ self::COMPONENT_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$build_query = static function ( $query ) {
			$pairs   = [];
			foreach ( explode( '&', $query ) as $pair ) {
				if ( false === strpos( $pair, self::COMPONENT_QUERY_VAR ) ) {
					$pairs[] = $pair;
				}
			}

			return implode( '&', $pairs );
		};

		// Scrub QUERY_STRING.
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$_SERVER['QUERY_STRING'] = $build_query( $_SERVER['QUERY_STRING'] );
		}

		// Scrub REQUEST_URI.
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			list( $path, $query ) = explode( '?', $_SERVER['REQUEST_URI'], 2 );

			$pairs                  = $build_query( $query );
			$_SERVER['REQUEST_URI'] = $path;
			if ( ! empty( $pairs ) ) {
				$_SERVER['REQUEST_URI'] .= "?{$pairs}";
			}
		}
	}

	/**
	 * Add purged query var to the supplied URL.
	 *
	 * @param string $url URL.
	 * @return string URL with purged query var.
	 */
	public static function add_purged_query_var( $url ) {
		if ( ! empty( self::$app_shell_component ) ) {
			$url = add_query_arg( self::COMPONENT_QUERY_VAR, self::$app_shell_component, $url );
		}
		return $url;
	}

	/**
	 * Get the requested app shell component (either inner or outer).
	 *
	 * @return string|null App shell component.
	 */
	public static function get_requested_app_shell_component() {
		if ( empty( self::$app_shell_component ) ) {
			return null;
		}

		$theme_support_args = AMP_Theme_Support::get_theme_support_args();
		if ( ! isset( $theme_support_args['app_shell'] ) ) {
			return null;
		}

		$component = self::$app_shell_component;
		if ( in_array( $component, [ 'inner', 'outer' ], true ) ) {
			return $component;
		}
		return null;
	}
}
