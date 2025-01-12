<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
				'isLCP' => true,
			),
		)
	);
};
