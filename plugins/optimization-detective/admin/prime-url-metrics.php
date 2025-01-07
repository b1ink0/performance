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

	$urls_with_pagination_data = od_generate_urls_list();

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
			'data'        => $urls_with_pagination_data,
			'breakpoints' => $breakpoints,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'od_enqueue_admin_assets' );

/**
 * Generate a batched list of URLs to prime, with stateful iteration.
 *
 * @since n.e.x.t
 *
 * @param int $provider_index     Index of the current sitemap provider in the array of providers.
 * @param int $subtype_index      Index of the current subtype for the current provider.
 * @param int $page_number        Current page number for the current subtype.
 * @param int $offset_within_page How many URLs have been consumed from the current page’s URL list.
 * @param int $limit             How many URLs to return in this batch.
 *
 * @return array {
 *     @type string[] $urls                The list of URLs in this batch.
 *     @type int      $next_provider_index The next provider index to be used in the subsequent call.
 *     @type int      $next_subtype_index  The next subtype index to be used in the subsequent call.
 *     @type int      $next_page_number    The next page number to be used in the subsequent call.
 *     @type int      $next_offset_within_page The next offset within the page to be used.
 *     @type bool     $has_more            Whether more URLs still exist to be fetched.
 * }
 */
function od_generate_urls_list(
	int $provider_index = 0,
	int $subtype_index = 0,
	int $page_number = 1,
	int $offset_within_page = 0,
	int $limit = 500
): array {
	// Get the server & its registry of sitemap providers.
	$server   = wp_sitemaps_get_server();
	$registry = $server->registry;

	// All registered providers.
	$providers = array_values( $registry->get_providers() ); // Ensure zero-based index.

	$all_urls        = array();
	$collected_count = 0;

	// Flag to indicate if we should stop collecting further URLs (i.e., we reached $limit).
	$done = false;

	// Start iterating from the current provider_index forward.
	$providers_count = count( $providers );
	for ( $p = $provider_index; $p < $providers_count && ! $done; $p++ ) {
		$provider = $providers[ $p ];

		// WordPress providers often return an array of strings from get_object_subtypes().
		$subtypes = array_values( $provider->get_object_subtypes() ); // zero-based index.

		// Start from the current subtype_index if resuming.
		$subtypes_count = count( $subtypes );
		for ( $s = ( $p === $provider_index ) ? $subtype_index : 0; $s < $subtypes_count && ! $done; $s++ ) {
			// This is likely just a string, e.g. 'post', 'page', 'user', etc.
			$subtype = $subtypes[ $s ];

			// Retrieve the max number of pages for this subtype.
			$max_num_pages = $provider->get_max_num_pages( $subtype->name );

			// Start from the current page_number if resuming.
			for ( $page = ( ( $p === $provider_index ) && ( $s === $subtype_index ) ) ? $page_number : 1; $page <= $max_num_pages && ! $done; ++$page ) {
				// Make sure get_url_list() is called with the correct arguments: $page, $subtype (string).
				$url_list = $provider->get_url_list( $page, $subtype->name );
				if ( ! is_array( $url_list ) ) {
					continue;
				}

				$url_chunk = array_filter( array_column( $url_list, 'loc' ) );

				// We might have partially consumed this page, so skip $offset_within_page items first.
				$current_page_urls = array_slice( $url_chunk, $offset_within_page );

				// Count how many we consumed in this page.
				$consumed_in_this_page = 0;

				// Now collect from current_page_urls until we reach $limit.
				foreach ( $current_page_urls as $url ) {
					$all_urls[] = $url;
					++$collected_count;
					++$consumed_in_this_page;

					if ( $collected_count >= $limit ) {
						// We have our full batch; stop collecting further.
						$done = true;
						break;
					}
				}

				if ( ! $done ) {
					// We consumed this entire page, so if we continue, next time we start at offset 0 of the next page.
					$page_number        = $page + 1;
					$offset_within_page = 0;
				} else {
					// We reached the limit in the middle of this page.
					// Figure out how many we used from this page to update the offset properly.
					$extra_consumed = $collected_count - $limit; // If exactly $limit, this might be 0 or negative.
					if ( $extra_consumed < 0 ) {
						$extra_consumed = 0;
					}

					$offset_within_page = $offset_within_page + ( $consumed_in_this_page - $extra_consumed );

					// We haven't fully finished this page, so keep the same $page_number.
					$page_number = $page;
				}
			} // end for pages

			if ( ! $done ) {
				// If we've finished all pages in this subtype, move to next subtype from the start (page 1, offset 0).
				$page_number        = 1;
				$offset_within_page = 0;
			}
		} // end for subtypes

		if ( ! $done ) {
			// If we finished all subtypes in this provider, move to next provider and start at subtype=0, page=1.
			$subtype_index      = 0;
			$page_number        = 1;
			$offset_within_page = 0;
		}
	} // end for providers

	// If we have looped through all providers without hitting $limit, we are done. No more URLs.
	// If $done == true, that means we ended early, so there are more left.
	$has_more = $done;

	// If we finished all providers/subtypes/pages, then we have no more left.
	// That means we might be at the last provider, last subtype, last page, etc.
	if ( ! $done ) {
		$provider_index = count( $providers ); // No more providers to iterate over next time.
	}

	return array(
		'urls'                    => $all_urls,
		'has_more'                => $has_more,
		'next_provider_index'     => $provider_index,  // Where we left off in the outer loop.
		'next_subtype_index'      => $subtype_index,   // Where we left off in the subtype loop.
		'next_page_number'        => $page_number,
		'next_offset_within_page' => $offset_within_page,
		'batch_size'              => $limit,
		'od_next_nonce'           => wp_create_nonce( 'od-prime-url-metrics-nonce' ),
	);
}


/**
 * Handle AJAX request to generate URLs list.
 *
 * @since n.e.x.t
 */
function od_ajax_generate_urls_list(): void {
	check_ajax_referer( 'od-prime-url-metrics-nonce', 'nonce' );
	// Get and sanitize input parameters.
	$provider_index     = isset( $_POST['provider_index'] ) ? absint( $_POST['provider_index'] ) : 0;
	$subtype_index      = isset( $_POST['subtype_index'] ) ? absint( $_POST['subtype_index'] ) : 0;
	$page_number        = isset( $_POST['page_number'] ) ? absint( $_POST['page_number'] ) : 1;
	$offset_within_page = isset( $_POST['offset_within_page'] ) ? absint( $_POST['offset_within_page'] ) : 0;
	$limit              = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 500;

	// Call the function to generate URLs.
	$result = od_generate_urls_list( $provider_index, $subtype_index, $page_number, $offset_within_page, $limit );

	// Send the result back to the client.
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_od_generate_urls_list', 'od_ajax_generate_urls_list' );