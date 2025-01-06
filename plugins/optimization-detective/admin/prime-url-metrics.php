<?php
/**
 * Optimization Detective: OD_Storage_Lock class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class containing logic for locking storage for new URL Metrics.
 *
 * @since n.e.x.t
 */
function od_add_admin_menu(): void {
	add_submenu_page(
		'tools.php',
		__( 'Prime URL Metrics', 'optimization-detective' ),
		__( 'Prime URL Metrics', 'optimization-detective' ),
		'manage_options',
		'od-prime-url-metrics',
		'od_render_admin_page'
	);
}
add_action( 'admin_menu', 'od_add_admin_menu' );

/**
 * Initialize the Prime URL Metrics admin page.
 *
 * @since n.e.x.t
 */
function od_render_admin_page(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Prime URL Metrics', 'optimization-detective' ); ?></h1>
		<p><?php esc_html_e( 'Use this tool to load all URLs in iframes, bypassing storage locks, to quickly gather URL metrics.', 'optimization-detective' ); ?></p>
		<div id="od-prime-app"></div>
	</div>
	<?php
}

/**
 * Enqueue admin assets for the Prime URL Metrics admin page.
 *
 * @since n.e.x.t
 *
 * @param string $hook_suffix The current admin page.
 */
function od_enqueue_admin_assets( string $hook_suffix ): void {
	if ( 'tools_page_od-prime-url-metrics' !== $hook_suffix ) {
		return;
	}

	$urls = od_generate_urls_list();

	wp_enqueue_script(
		'od-prime-url-metrics-js',
		plugin_dir_url( __FILE__ ) . 'od-prime-url-metrics.js',
		array(),
		'1.0',
		true
	);

	$bp_widths = od_get_breakpoint_max_widths();
	sort( $bp_widths );
	$bp_widths[] = end( $bp_widths ) + 100;

	// We need to ensure min is 1 else the height will become too small.
	$min_ar = max( 1, od_get_minimum_viewport_aspect_ratio() );
	// We also need to ensure max is 2 else the height will become too large.
	$max_ar = min( 2, od_get_maximum_viewport_aspect_ratio() );

	// Get minimum and maximum widths for interpolation.
	$min_width = $bp_widths[0];
	$max_width = end( $bp_widths );

	// Calculate Aspect Ratios using Inverse Proportionality.
	$breakpoints = array_map(
		static function ( $width ) use ( $min_width, $max_width, $min_ar, $max_ar ) {
			// Prevent division by zero.
			if ( 0 === $width ) {
				$ar = $min_ar;
			} else {
				// Calculate aspect ratio.
				$ar = $max_ar - ( ( $max_ar - $min_ar ) * ( ( $width - $min_width ) / ( $max_width - $min_width ) ) );
			}

			// Ensure aspect ratio does not go below min_ar.
			if ( $ar < $min_ar ) {
				$ar = $min_ar;
			}

			// Calculate height based on aspect ratio (height = ar * width).
			$height = (int) round( $ar * $width );
			return array(
				'width'  => $width,
				'height' => $height,
				'ar'     => round( $ar, 2 ),
			);
		},
		$bp_widths
	);

	wp_localize_script(
		'od-prime-url-metrics-js',
		'odPrimeData',
		array(
			'urls'        => $urls,
			'breakpoints' => $breakpoints,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'od_enqueue_admin_assets' );

/**
 * Generate a list of URLs to prime.
 *
 * @since n.e.x.t
 *
 * @return string[] List of URLs.
 */
function od_generate_urls_list(): array {
	$server   = wp_sitemaps_get_server();
	$registry = $server->registry;

	$all_urls = array();

	// Loop over all registered providers (e.g., posts, users, taxonomies).
	$providers = $registry->get_providers();
	foreach ( $providers as $provider_key => $provider ) {
		// Each provider may have one or more object subtypes (e.g., 'post', 'page', 'attachment').
		$subtypes = $provider->get_object_subtypes();
		foreach ( $subtypes as $key => $subtype ) {
			$max_num_pages = $provider->get_max_num_pages( $key );

			// The provider may have multiple pages of URLs as one page may not be enough to get all URLs.
			for ( $page = 1; $page <= $max_num_pages; $page++ ) {
				$url_list = $provider->get_url_list( $page, $key );
				if ( ! empty( $url_list ) && is_array( $url_list ) ) {
					$all_urls = array_merge( $all_urls, array_filter( array_column( $url_list, 'loc' ) ) );
				}
			}
		}
	}
	return $all_urls;
}
