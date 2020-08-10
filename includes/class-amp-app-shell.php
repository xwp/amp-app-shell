<?php
/**
 * Class AMP_App_Shell
 *
 * @package AmpAppShell
 */

use AmpProject\Dom\Document;

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
		add_action( 'parse_query', [ __CLASS__, 'init_app_shell' ], 9 );

		if ( ! is_admin() ) {
			add_action( 'template_redirect', [ __CLASS__, 'start_output_buffering' ] );
		}
	}

	/**
	 * Init app shell.
	 */
	public static function init_app_shell() {
		if ( ! class_exists( 'AMP_Theme_Support' ) ) {
			return;
		}

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
	 *
	 * @param  string $location The path or URL to redirect to.
	 * @return string Purged path or URL to redirect to.
	 */
	public static function purge_app_shell_query_var( $location = '' ) {
		if ( ! isset( $_GET[ self::COMPONENT_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $location;
		}

		self::$app_shell_component = wp_unslash( $_GET[ self::COMPONENT_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_REQUEST[ self::COMPONENT_QUERY_VAR ], $_GET[ self::COMPONENT_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$build_query = static function ( $query ) {
			$pairs = [];

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

		return $location;
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


	/**
	 * Start output buffering.
	 *
	 * @see AMP_App_Shell::prepare_response()
	 */
	public static function start_output_buffering() {
		ob_start( [ __CLASS__, 'prepare_response' ] );
	}

	/**
	 * Finish output buffering and process response.
	 *
	 * @see AMP_App_Shell::start_output_buffering()
	 *
	 * @param string $response Buffered HTML document response. By default it expects a complete document.
	 * @return string Processed Response.
	 */
	public static function prepare_response( $response ) {
		$app_shell_component = self::get_requested_app_shell_component();

		if ( ! class_exists( 'AmpProject\Dom\Document' ) || ! $app_shell_component ) {
			return $response;
		}

		$dom             = Document::fromHtml( $response );
		$content_element = $dom->getElementById( self::CONTENT_ELEMENT_ID );

		if ( ! $content_element ) {
			status_header( 500 );
			return esc_html__( 'Unable to locate CONTENT_ELEMENT_ID.', 'amp-app-shell' );
		}

		// Remove the content wrappers if requesting the inner app shell.
		if ( 'inner' === $app_shell_component ) {
			self::prepare_inner_app_shell_document( $content_element );
			return $dom->saveHTML();
		}

		return $response;
	}

	/**
	 * Prepare inner app shell.
	 *
	 * @param DOMElement $content_element Content element.
	 */
	protected static function prepare_inner_app_shell_document( DOMElement $content_element ) {
		$dom = Document::fromNode( $content_element );

		// Preserve the admin bar.
		$admin_bar = $dom->getElementById( 'wpadminbar' );
		if ( $admin_bar ) {
			$admin_bar->parentNode->removeChild( $admin_bar );
		}

		// Extract all stylesheet elements before the body gets isolated.
		$style_elements = [];
		$lower_case     = 'translate( %s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz" )'; // In XPath 2.0 this is lower-case().
		$predicates     = [
			sprintf( '( self::style and ( not( @type ) or %s = "text/css" ) )', sprintf( $lower_case, '@type' ) ),
			sprintf( '( self::link and @href and %s = "stylesheet" )', sprintf( $lower_case, '@rel' ) ),
		];
		foreach ( $dom->xpath->query( './/*[ ' . implode( ' or ', $predicates ) . ' ]', $dom->body ) as $element ) {
			$style_elements[] = $element;
		}
		foreach ( $style_elements as $style_element ) {
			$style_element->parentNode->removeChild( $style_element );
		}

		// Preserve all svg defs which aren't inside the content element.
		$svgs_with_def = [];
		foreach ( $dom->xpath->query( '//svg[.//defs]' ) as $svg ) {
			$svgs_with_def[] = $svg;
		}

		// Isolate the content element from the rest of the elements in the body.
		$remove_siblings = function( DOMElement $node ) {
			while ( $node->previousSibling ) {
				$node->parentNode->removeChild( $node->previousSibling );
			}
			while ( $node->nextSibling ) {
				$node->parentNode->removeChild( $node->nextSibling );
			}
		};
		$node            = $content_element;
		do {
			$remove_siblings( $node );
			$node = $node->parentNode;
		} while ( $node && $node !== $dom->body );

		// Restore admin bar element.
		if ( $admin_bar ) {
			$dom->body->appendChild( $admin_bar );
		}

		// Restore style elements.
		foreach ( $style_elements as $style_element ) {
			$dom->body->appendChild( $style_element );
		}

		// Restore SVGs with defs.
		foreach ( $svgs_with_def as $svg ) {
			/*
			 * Check if the node was removed from the document.
			 * This is needed because Node.compareDocumentPosition() is not available in PHP.
			 */
			$is_connected = false;
			$node         = $svg;
			while ( $node->parentNode ) {
				if ( $node === $svg->ownerDocument ) {
					$is_connected = true;
					break;
				}
				$node = $node->parentNode;
			}

			// Re-add the SVG element to the body with only its defs elements.
			if ( ! $is_connected ) {
				$defs = [];
				foreach ( $svg->getElementsByTagName( 'defs' ) as $def ) {
					$defs[] = $def;
				}

				// Remove all children.
				while ( $svg->firstChild ) {
					$svg->removeChild( $svg->firstChild );
				}

				// Re-add all defs.
				foreach ( $defs as $def ) {
					$svg->appendChild( $def );
				}

				// Add to body.
				$dom->body->appendChild( $svg );
			}
		}
	}
}
