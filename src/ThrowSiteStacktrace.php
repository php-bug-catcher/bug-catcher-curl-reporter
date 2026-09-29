<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 */
namespace BugCatcher\Reporter;

use Kregel\ExceptionProbe\Codeframe;
use Kregel\ExceptionProbe\Stacktrace;
use Throwable;

/**
 * Builds code frames straight from a Throwable instead of from a trace string.
 *
 * PHP's getTraceAsString() never contains the throw site: its #0 is already the caller of the
 * frame that threw. The only place the throw site lives is getFile()/getLine(), so it has to be
 * prepended by hand or it is lost. Extending Stacktrace gives access to the protected
 * getTheCodeFromTheFile()/isValidFile(), so the synthetic frame carries the exact same code
 * window as the parsed ones.
 */
class ThrowSiteStacktrace extends Stacktrace {

	/**
	 * @return Codeframe[] the throw site and trace of $throwable, followed by those of its previous chain
	 */
	public function parseThrowable(Throwable $throwable): array {
		$frames = [];
		$seen = [];

		for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
			if (in_array($current, $seen, true)) {
				break;
			}
			$seen[] = $current;
			$frames = array_merge($frames, (new static())->parseSingle($current, $current !== $throwable));
		}

		return $frames;
	}

	/**
	 * @return Codeframe[]
	 */
	protected function parseSingle(Throwable $throwable, bool $isPrevious): array {
		$frames = [$this->throwSiteFrame($throwable, $isPrevious)];

		$trace = $throwable->getTraceAsString();
		if ($trace !== '') {
			$frames = array_merge($frames, $this->parse($trace));
		}

		return $frames;
	}

	protected function throwSiteFrame(Throwable $throwable, bool $isPrevious): Codeframe {
		$label = ($isPrevious ? 'Caused by: ' : '') . $throwable::class . ': ' . $throwable->getMessage();
		$label = str_replace(["\r\n", "\r", "\n"], ' ', $label);

		$file = $throwable->getFile();
		$line = $throwable->getLine();

		if (!$this->isValidFile($file)) {
			return new Codeframe($file, 0, [], $label);
		}

		return new Codeframe($file, $line, $this->getTheCodeFromTheFile($file, $line), $label);
	}
}
