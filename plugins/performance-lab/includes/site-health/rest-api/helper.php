<?php
/**
 * Helper functions for the Optimization Detective REST API health check.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function perflab_optimization_detective_rest_api_test(): array {
	$result = array(
		'label'       => __( 'Your site has functional Optimization Detective REST API endpoint', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Optimization Detective can send and store URL metrics via REST API endpoint', 'performance-lab' )
		),
		'actions'     => '',
		'test'        => 'optimization_detective_rest_api',
	);

	$rest_url = get_rest_url( null, 'optimization-detective/v1/url-metrics:store' );
	$response = wp_remote_post(
		$rest_url,
		array(
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => false,
		)
	);

	if ( is_wp_error( $response ) ) {
		$result['status']      = 'recommended';
		$result['label']       = __( 'Your site encountered error accessing Optimization Detective REST API endpoint', 'performance-lab' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'The Optimization Detective endpoint could not be reached. This might mean the REST API is disabled or blocked.', 'performance-lab' )
		);
		update_option(
			'perflab_od_rest_api_info',
			array(
				'status'    => 'error',
				'available' => false,
			)
		);
		return $result;
	}

	$status_code     = wp_remote_retrieve_response_code( $response );
	$data            = json_decode( wp_remote_retrieve_body( $response ), true );
	$expected_params = array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' );
	if (
		400 === $status_code
		&& isset( $data['data']['params'] )
		&& is_array( $data['data']['params'] )
		&& count( $expected_params ) === count( array_intersect( $data['data']['params'], $expected_params ) )
	) {
		// The REST API endpoint is available.
		update_option(
			'perflab_od_rest_api_info',
			array(
				'status'    => 'ok',
				'available' => true,
			)
		);
		return $result;
	} elseif ( 401 === $status_code ) {
		$result['status']      = 'recommended';
		$result['label']       = __( 'Your site encountered unauthorized error for Optimization Detective REST API endpoint', 'performance-lab' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'The REST API endpoint requires authentication. Ensure proper credentials are provided.', 'performance-lab' )
		);
		update_option(
			'perflab_od_rest_api_info',
			array(
				'status'    => 'unauthorized',
				'available' => false,
			)
		);
	} elseif ( 403 === $status_code ) {
		$result['status']      = 'recommended';
		$result['label']       = __( 'Your site encountered forbidden error for Optimization Detective REST API endpoint', 'performance-lab' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'The REST API endpoint is blocked check server or security settings.', 'performance-lab' )
		);
		update_option(
			'perflab_od_rest_api_info',
			array(
				'status'    => 'forbidden',
				'available' => false,
			)
		);
	}

	return $result;
}

/**
 * Periodically runs the Optimization Detective REST API health check.
 *
 * @since n.e.x.t
 */
function perflab_schedule_rest_api_health_check(): void {
	if ( ! (bool) wp_next_scheduled( 'perflab_rest_api_health_check_event' ) ) {
		wp_schedule_event( time(), 'hourly', 'perflab_rest_api_health_check_event' );
	}
}
add_action( 'wp', 'perflab_schedule_rest_api_health_check' );

/**
 * Hook for the scheduled REST API health check.
 *
 * @since n.e.x.t
 */
function perflab_run_scheduled_rest_api_health_check(): void {
	perflab_optimization_detective_rest_api_test();
}
add_action( 'perflab_rest_api_health_check_event', 'perflab_run_scheduled_rest_api_health_check' );
