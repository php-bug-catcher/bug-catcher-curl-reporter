<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 5. 2024
 * Time: 7:19
 */
namespace BugCatcher\Reporter;

use Exception;
use Kregel\ExceptionProbe\Stacktrace;
use Throwable;

class CurlReporter {


	public function __construct(
		private readonly string $url,
		private readonly string $project,
		private readonly bool   $stackTrace = false,
	) {}

	public function reportException(Throwable $exception): void {
		$stackTrace = null;
		if ($this->stackTrace) {
			$stackTrace = $this->collectFrames($exception->getTraceAsString());
		}
		$path = '/api/record_logs';
		$data = [
			"message" => $message,
			"level"       => $record->level->value,
			"projectCode" => $this->project,
			"requestUri"  => $this->uriCatcher->getUri(),
		];
		if ($stackTrace) {
			$path = '/api/record_log_traces';
			$data['stackTrace'] = $stackTrace;
		}
		[$status, $response] = $this->request("POST", $path, json_encode($data), [
			'Content-Type' => 'application/json',
			'accept'       => 'application/json',
		]);
		if ($status !== 201) {
			throw new Exception("Error during sending log record to BugCatcher.\n" . $response);
		}
	}

	public function collectFrames(string $stackTrace): string {
		$stacktrace = (new Stacktrace())->parse($stackTrace);

		return serialize($stacktrace);
	}

	public function getUri() {
		$url = ($_SERVER['REQUEST_URI']??'');
		if (!$url && isset($argv) && is_array($argv)) {
			$url = join(" ", $argv);
		}

		return $url;
	}

	private function request(string $method, string $url, array|string $data = [], array $headers = []): array {
		$headers = array_map(function ($key, $value) {
			return $key . ': ' . $value;
		}, array_keys($headers), $headers);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url . $url);
		if ($method === "POST") {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		curl_close($ch);

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return [$status, $response];

	}
}