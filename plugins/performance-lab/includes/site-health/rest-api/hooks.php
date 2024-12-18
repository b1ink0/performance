<?php
/**
 * Hook callbacks used for the Optimization Detective REST API health check.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the Optimization Detective REST API check to site health tests.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_optimization_detective_add_rest_api_test( array $tests ): array {
	if ( ! defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
		return $tests;
	}

	$tests['direct']['optimization_detective_rest_api'] = array(
		'label' => __( 'Optimization Detective REST API Endpoint Availability', 'performance-lab' ),
		'test'  => 'perflab_optimization_detective_rest_api_test',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'perflab_optimization_detective_add_rest_api_test' );
