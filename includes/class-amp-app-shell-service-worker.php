<?php
/**
 * AMP_App_Shell_Service_Worker.
 *
 * @package AMP_App_Shell
 */

/**
 * Class AMP_App_Shell_Service_Worker.
 */
class AMP_App_Shell_Service_Worker {

	/**
	 * Init.
	 */
	public static function init() {
		if ( ! class_exists( 'WP_Service_Workers' ) || ! class_exists( 'AMP_Theme_Support' ) ) {
			return;
		}

		$theme_support = AMP_Theme_Support::get_theme_support_args();
		if ( isset( $theme_support['service_worker'] ) && false === $theme_support['service_worker'] ) {
			return;
		}

		if ( ! isset( $theme_support['app_shell'] ) ) {
			return;
		}

		// App shell is mutually exclusive with navigation preload.
		add_filter( 'wp_service_worker_navigation_preload', '__return_false' );

		// Opt to route all navigation requests through the app shell.
		add_filter(
			'wp_service_worker_navigation_route',
			function ( $navigation_route ) {
				$navigation_route['url'] = add_query_arg( AMP_App_Shell::COMPONENT_QUERY_VAR, 'outer', home_url( '/' ) );
				return $navigation_route;
			}
		);

		/**
		 * Add the query var to designate that the inner app shell is being requested.
		 *
		 * @param array $precache_entry Precache entry.
		 * @return array Modified precache entry.
		 */
		$add_inner_app_shell_component = function( $precache_entry ) {
			$precache_entry['url'] = add_query_arg(
				AMP_App_Shell::COMPONENT_QUERY_VAR,
				'inner',
				$precache_entry['url']
			);
			return $precache_entry;
		};
		add_filter( 'wp_offline_error_precache_entry', $add_inner_app_shell_component, 100 );
		add_filter( 'wp_server_error_precache_entry', $add_inner_app_shell_component, 100 );

		// @todo There should be some query var that is used to disable navigation routing entirely so that there is no need to bypass for network.
		// Prevent app shell from being served when requesting AMP version directly.
		add_filter(
			'wp_service_worker_navigation_route_blacklist_patterns',
			function ( $blacklist_patterns ) {
				$blacklist_patterns[] = '\?(.+&)*' . preg_quote( amp_get_slug(), '/' ) . '(=|&|$)';
				return $blacklist_patterns;
			}
		);

		// Make sure the offline template is added to list of templates in AMP.
		add_filter(
			'amp_supportable_templates',
			function( $supportable_templates ) {
				if ( ! isset( $supportable_templates['is_offline'] ) ) {
					$supportable_templates['is_offline'] = [
						'label' => __( 'Offline', 'amp' ),
					];
				}
				return $supportable_templates;
			},
			1000
		);

		/*
		 * The default-enabled options reflect which features are not commented-out in the AMP-by-Example service worker.
		 * See <https://github.com/ampproject/amp-by-example/blob/e093edb401b1617859b5365e80b639d81b06f058/boilerplate-generator/templates/files/serviceworkerJs.js>.
		 */
		$enabled_options = [
			'live_list_offline_commenting' => false,
		];
		if ( isset( $theme_support['service_worker'] ) && is_array( $theme_support['service_worker'] ) ) {
			$enabled_options = array_merge(
				$enabled_options,
				$theme_support['service_worker']
			);
		}

		if ( $enabled_options['live_list_offline_commenting'] ) {
			add_action( 'wp_front_service_worker', [ __CLASS__, 'add_live_list_offline_commenting' ] );
		}
	}

	/**
	 * Add live list offline commenting service worker script.
	 *
	 * @param WP_Service_Worker_Scripts $service_workers WP Service Workers object.
	 */
	public static function add_live_list_offline_commenting( $service_workers ) {
		if ( ! ( $service_workers instanceof WP_Service_Worker_Scripts ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected argument to be WP_Service_Worker_Scripts.', 'amp' ), '1.0' );
			return;
		}

		$theme_support = AMP_Theme_Support::get_theme_support_args();
		if ( empty( $theme_support['comments_live_list'] ) ) {
			return;
		}

		$service_workers->register(
			'amp-offline-commenting',
			function() {
				$js = file_get_contents( AMP_APP_SHELL__DIR__ . '/assets/js/amp-service-worker-offline-commenting.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
				$js = preg_replace( '#/\*\s*global.+?\*/#', '', $js );
				$js = str_replace(
					'ERROR_MESSAGES',
					wp_json_encode( wp_service_worker_get_error_messages() ),
					$js
				);
				$js = str_replace(
					'SITE_URL',
					wp_json_encode( site_url() ),
					$js
				);
				return $js;
			}
		);
	}
}
