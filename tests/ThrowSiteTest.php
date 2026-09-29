<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 */
namespace BugCatcher\Reporter\Tests;

use BugCatcher\Reporter\CurlReporter;
use Exception;
use Kregel\ExceptionProbe\Codeframe;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The throw site (getFile()/getLine()) is never part of getTraceAsString() — its #0 is already
 * the caller. These tests pin down that the reported stackTrace payload starts with the real
 * throw site, not with the second call in the chain.
 */
class ThrowSiteTest extends TestCase {

	private function throwDeep(): void {
		throw new RuntimeException("primary error");   // <- the throw site the report must show
	}

	private function catchDeep(): Throwable {
		try {
			$this->throwDeep();
		} catch (Throwable $e) {
			return $e;
		}
		throw new Exception("unreachable");
	}

	public function testReportedStackTraceStartsAtThrowSite(): void {
		$exception = $this->catchDeep();
		$reporter  = new CapturingCurlReporter("https://log.invalid", 'dev', true);

		$reporter->reportException($exception);

		$this->assertSame('/api/record_log_traces', $reporter->capturedPath);
		$payload = json_decode($reporter->capturedData, true);
		/** @var Codeframe[] $frames */
		$frames = unserialize($payload['stackTrace']);

		$this->assertNotEmpty($frames);
		$this->assertSame($exception->getFile(), $frames[0]->file, "first frame must be the throw site file");
		$this->assertSame($exception->getLine(), $frames[0]->line, "first frame must be the throw site line");
		$this->assertStringContainsString("primary error", $frames[0]->frame);
		$this->assertNotEmpty($frames[0]->code, "throw site frame must carry the code window");

		// the caller of throwDeep() (= getTraceAsString #0) must follow as the second frame
		$this->assertSame(__FILE__, $frames[1]->file);
		$this->assertStringContainsString("throwDeep", $frames[1]->frame);
	}

	public function testPreviousChainIsAppended(): void {
		try {
			try {
				$this->throwDeep();
			} catch (Throwable $inner) {
				throw new Exception("wrapper error", 0, $inner);
			}
		} catch (Throwable $outer) {
		}
		$reporter = new CapturingCurlReporter("https://log.invalid", 'dev', true);

		$reporter->reportException($outer);

		$payload = json_decode($reporter->capturedData, true);
		/** @var Codeframe[] $frames */
		$frames = unserialize($payload['stackTrace']);

		$this->assertSame($outer->getFile(), $frames[0]->file);
		$this->assertSame($outer->getLine(), $frames[0]->line);

		$causedBy = array_values(array_filter($frames, fn (Codeframe $f) => str_contains($f->frame, 'Caused by:')));
		$this->assertCount(1, $causedBy, "previous exception must appear as a Caused by frame");
		$this->assertSame($outer->getPrevious()->getFile(), $causedBy[0]->file);
		$this->assertSame($outer->getPrevious()->getLine(), $causedBy[0]->line);
		$this->assertStringContainsString("primary error", $causedBy[0]->frame);
	}
}

class CapturingCurlReporter extends CurlReporter {
	public ?string $capturedPath = null;
	public ?string $capturedData = null;

	protected function request(string $method, string $url, array|string $data = [], array $headers = []): array {
		$this->capturedPath = $url;
		$this->capturedData = is_array($data) ? json_encode($data) : $data;

		return [201, ''];
	}
}
