<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
				'isLCP'             => true,
				'intersectionRatio' => 1,
				// Intentionally omitting resizedBoundingClientRect here to test behavior when data isn't supplied.
			),
		)
	);
};
