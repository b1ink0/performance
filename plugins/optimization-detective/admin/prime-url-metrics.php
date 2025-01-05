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

if ( isset( $_GET['od_prime'] ) ) {
	add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );
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

	$min_ar = max( 1, od_get_minimum_viewport_aspect_ratio() );
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
				$ar = $max_ar - ( ($max_ar - $min_ar) * ( ( $width - $min_width ) / ( $max_width - $min_width ) ) );
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
	$posts = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		)
	);

	$urls = array();
	foreach ( $posts as $post ) {
		$urls[] = get_permalink( $post );
	}

	return array_values( array_filter( $urls ) );
}
