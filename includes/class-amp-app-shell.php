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

		// Remove app shell query var.
		add_filter( 'wp_redirect', [ __CLASS__, 'add_purged_query_var' ], 10, 1 );
		self::purge_app_shell_query_var();

		if ( ! is_admin() ) {
			/*
			 * Start output buffering after AMP plugin has already started its own
			 * buffering. Any changes done to the document in this app shell buffering
			 * callback are later processed by the AMP plugin.
			 */
			add_action( 'template_redirect', [ __CLASS__, 'start_output_buffering' ] );

			/*
			 * Start late output buffering at very low priority so that it is run
			 * before AMP plugin buffering starts. Thanks to that, the app shell
			 * buffering callback function is executed after AMP plugin does its job.
			 */
			$priority = defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX; // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
			add_action( 'wp', [ __CLASS__, 'start_late_output_buffering' ], $priority );
		}
	}

	/**
	 * Init app shell.
	 */
	public static function init_app_shell() {
		if ( ! is_amp_app_shell_supported() ) {
			return;
		}

		$requested_app_shell_component = self::get_requested_app_shell_component();

		// When inner app shell is requested, it is always an AMP request. Do not allow AMP when getting outer app shell for now (but this should be allowed in the future).
		if ( 'outer' === $requested_app_shell_component ) {
			add_action(
				'template_redirect',
				function() {
					if ( ! amp_is_request() ) {
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

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts for outer app shell.
	 *
	 * This includes precached app shell and normal site navigation prior to service worker installation.
	 */
	public static function enqueue_scripts() {
		$requested_app_shell_component = self::get_requested_app_shell_component();
		if ( amp_is_request() || 'inner' === $requested_app_shell_component ) {
			return;
		}

		wp_enqueue_script( 'amp-shadow' );
		wp_enqueue_script( 'amp-wp-app-shell' );

		$exports = [
			'contentElementId'  => self::CONTENT_ELEMENT_ID,
			'homeUrl'           => home_url( '/' ),
			'adminUrl'          => admin_url( '/' ),
			'ampSlug'           => amp_get_slug(),
			'componentQueryVar' => self::COMPONENT_QUERY_VAR,
			'isOuterAppShell'   => true,
		];

		wp_add_inline_script( 'amp-wp-app-shell', sprintf( 'var ampAppShell = %s;', wp_json_encode( $exports ) ), 'before' );
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

		// Force AMP in case of app shell inner component.
		if ( 'inner' === self::get_requested_app_shell_component() && function_exists( 'amp_get_slug' ) ) {
			$_GET[ amp_get_slug() ]  = 1;
			$_SERVER['QUERY_STRING'] = add_query_arg( amp_get_slug(), 1, $_SERVER['QUERY_STRING'] );
			$_SERVER['REQUEST_URI']  = add_query_arg( amp_get_slug(), 1, $_SERVER['REQUEST_URI'] );
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
		$requested_app_shell_component = self::get_requested_app_shell_component();

		if ( ! empty( $requested_app_shell_component ) ) {
			$url = add_query_arg( self::COMPONENT_QUERY_VAR, $requested_app_shell_component, $url );
		}

		return $url;
	}

	/**
	 * Get the requested app shell component (either inner or outer).
	 *
	 * @return string|null App shell component.
	 */
	public static function get_requested_app_shell_component() {
		if ( empty( self::$app_shell_component ) || ! is_amp_app_shell_supported() ) {
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
		ob_start(
			function( $response ) {
				return self::prepare_response( $response );
			}
		);
	}

	/**
	 * Start late output buffering.
	 *
	 * @see AMP_App_Shell::prepare_response()
	 */
	public static function start_late_output_buffering() {
		ob_start(
			function( $response ) {
				return self::prepare_response( $response, true );
			}
		);
	}

	/**
	 * Finish output buffering and process response.
	 *
	 * @see AMP_App_Shell::start_output_buffering()
	 * @see AMP_App_Shell::start_late_output_buffering()
	 *
	 * @param string $response Buffered HTML document response. By default it expects a complete document.
	 * @param bool   $is_late  Flag indicating that buffer contains late, already processed response.
	 * @return string Processed Response.
	 */
	public static function prepare_response( $response, $is_late = false ) {
		$app_shell_component = self::get_requested_app_shell_component();

		// Remove the content wrappers if requesting the inner app shell.
		if ( 'inner' !== $app_shell_component || ! class_exists( 'AmpProject\Dom\Document' ) ) {
			return $response;
		}

		$dom             = Document::fromHtml( $response );
		$content_element = $dom->getElementById( self::CONTENT_ELEMENT_ID );

		if ( ! $content_element ) {
			status_header( 500 );
			return esc_html__( 'Unable to locate CONTENT_ELEMENT_ID.', 'amp-app-shell' );
		}

		if ( $is_late ) {
			self::sanitize_styles_for_shadow_dom( $dom );
		} else {
			self::prepare_inner_app_shell_document( $content_element );
		}

		return $dom->saveHTML();
	}

	/**
	 * Prepare inner app shell by removing content wrappers.
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

		$node = $content_element;
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

	/**
	 * Sanitize styles specifically for using inside an app shell.
	 *
	 * @param Document $dom DOM tree.
	 */
	protected static function sanitize_styles_for_shadow_dom( Document $dom ) {
		$lower_case = 'translate( %s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz" )'; // In XPath 2.0 this is lower-case().
		$query      = sprintf( '//*[ ( self::style and not( @amp-boilerplate ) and ( not( @type ) or %s = "text/css" ) ) ]', sprintf( $lower_case, '@type' ) );

		foreach ( $dom->xpath->query( $query ) as $element ) {
			/*
			 * The :root pseudo selector does not work inside shadow DOM. Additionally,
			 * the shadow DOM is not including the root html element (or the head element),
			 * however there is a body element. The AMP plugin uses :root in the transformation
			 * of !important rules to give selectors high specificity. Replacing :root with
			 * body will not work all of the time.
			 * @todo The use of :root pseudo selectors in stylesheets needs to be revisited in Shadow DOM.
			 */
			$element->nodeValue = preg_replace( '/:root\b/', 'body', $element->nodeValue );
		}
	}
}
