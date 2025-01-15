<?php
/**
 * Tests for Optimization Detective REST API site health check.
 *
 * @package optimization-detective
 */

class Test_OD_REST_API_Site_Health_Check extends WP_UnitTestCase {

	/**
	 * Holds mocked response headers for different test scenarios.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected $mocked_responses = array();

	/**
	 * Setup each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any filters or mocks.
		remove_all_filters( 'pre_http_request' );

		// Add the filter to mock HTTP requests.
		add_filter( 'pre_http_request', array( $this, 'mock_http_requests' ), 10, 3 );
	}

	/**
	 * Test that the site health check is `good` when the REST API is available.
	 */
	public function test_rest_api_available(): void {
		$this->mocked_responses = array(
			get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ) => $this->build_mock_response(
				400,
				'Bad Request',
				array(
					'data' => array(
						'params' => array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' ),
					),
				)
			),
		);

		$result           = od_optimization_detective_rest_api_test();
		$od_rest_api_info = get_option( 'od_rest_api_info', array() );

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 400, isset( $od_rest_api_info['status_code'] ) ? $od_rest_api_info['status_code'] : '' );
		$this->assertTrue( isset( $od_rest_api_info['available'] ) ? $od_rest_api_info['available'] : false );
	}

	/**
	 * Test behavior when REST API returns an unauthorized error.
	 */
	public function test_rest_api_unauthorized(): void {
		$this->mocked_responses = array(
			get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ) => $this->build_mock_response(
				401,
				'Unauthorized'
			),
		);

		$result           = od_optimization_detective_rest_api_test();
		$od_rest_api_info = get_option( 'od_rest_api_info', array() );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 401, isset( $od_rest_api_info['status_code'] ) ? $od_rest_api_info['status_code'] : '' );
		$this->assertFalse( isset( $od_rest_api_info['available'] ) ? $od_rest_api_info['available'] : true );
	}

	/**
	 * Test behavior when REST API returns an forbidden error.
	 */
	public function test_rest_api_forbidden(): void {
		$this->mocked_responses = array(
			get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ) => $this->build_mock_response(
				403,
				'Forbidden'
			),
		);

		$result           = od_optimization_detective_rest_api_test();
		$od_rest_api_info = get_option( 'od_rest_api_info', array() );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 403, isset( $od_rest_api_info['status_code'] ) ? $od_rest_api_info['status_code'] : '' );
		$this->assertFalse( isset( $od_rest_api_info['available'] ) ? $od_rest_api_info['available'] : true );
	}

	/**
	 * Mock HTTP requests for assets to simulate different responses.
	 *
	 * @param bool                 $response A preemptive return value of an HTTP request. Default false.
	 * @param array<string, mixed> $args     Request arguments.
	 * @param string               $url      The request URL.
	 * @return array<string, mixed> Mocked response.
	 */
	public function mock_http_requests( bool $response, array $args, string $url ): array {
		if ( isset( $this->mocked_responses[ $url ] ) ) {
			return $this->mocked_responses[ $url ];
		}

		// If no specific mock set, default to a generic success response.
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	/**
	 * Build a mock response.
	 *
	 * @param int                  $status_code HTTP status code.
	 * @param string               $message     HTTP status message.
	 * @param array<string, mixed> $body        Response body.
	 * @return array<string, mixed> Mocked response.
	 */
	protected function build_mock_response( int $status_code, string $message, array $body = array() ): array {
		return array(
			'response' => array(
				'code'    => $status_code,
				'message' => $message,
			),
			'body'     => wp_json_encode( $body ),
		);
	}
}