<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 5. 2024
 * Time: 7:19
 */
namespace BugCatcher\Reporter\Tests;

use BugCatcher\Reporter\CurlReporter;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 5. 2024
 * Time: 7:35
 */
class CurlReporterTest extends TestCase {

	public function testCurlReporter() {
		$curlReporter = new CurlReporter(
			"https://127.0.0.1:8000",
			'dev',
			true,
		);
		try {
			throw new Exception("Test exception");
		} catch (Exception $e) {
			$curlReporter->reportException($e);
		}
		$this->assertTrue(true);
	}

}