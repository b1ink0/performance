<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		$elements = array();
		for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS; $i++ ) {
			$elements[] = array(
				'xpath' => sprintf( '/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::IMG]', $i ),
				'isLCP' => false,
			);
		}

		$tag_visitor_registry = new OD_Tag_Visitor_Registry();
		$tag_visitor_registry->register( 'img', static function (): void {} );
		$tag_visitor_registry->register( 'video', static function (): void {} );

		$test_case->populate_url_metrics( $elements, od_compute_current_etag( $tag_visitor_registry ), false );
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				' .
				join(
					"\n",
					call_user_func(
						static function () {
							$tags = array();
							for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS + 1; $i++ ) {
								$tags[] = sprintf( '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">' );
							}
							return $tags;
						}
					)
				) .
				'
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				' .
				join(
					"\n",
					call_user_func(
						static function () {
							$tags = array();
							for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS + 1; $i++ ) {
								$tags[] = sprintf( '<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">', $i );
							}
							return $tags;
						}
					)
				) .
				'
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
