<?php

declare(strict_types = 1);

namespace App\Utils\Monolog;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

class MonologDiscordHandler extends AbstractProcessingHandler
{

	private Client $client;

	private string $projectRoot;

	public function __construct(
		private string $webhookUrl,
		Level $level = Level::Error,
	)
	{
		parent::__construct($level);
		$this->client = new Client();
		$this->projectRoot = dirname(__DIR__, 3) . '/';
	}

	protected function write(LogRecord $record): void
	{
		try {
			$this->client->post($this->webhookUrl, [
				'json' => [
					'embeds' => [
						[
							'title' => $this->getTitle($record->level),
							'description' => $this->formatDescription($record),
							'color' => $this->getColor($record->level),
							'timestamp' => $record->datetime->format('c'),
							'fields' => $this->getFields($record),
						],
					],
				],
			]);
		} catch (GuzzleException) {
			// do nothing
		}
	}

	private function getTitle(Level $level): string
	{
		return match ($level) {
			Level::Debug => '🐛 Debug',
			Level::Info => 'ℹ️ Info',
			Level::Notice => '📌 Notice',
			Level::Warning => '⚠️ Warning',
			Level::Error => '❌ Error',
			Level::Critical => '🔥 Critical',
			Level::Alert => '🚨 Alert',
			Level::Emergency => '💀 Emergency',
		};
	}

	private function getColor(Level $level): int
	{
		return match ($level) {
			Level::Debug => 0x607D8B,
			Level::Info => 0x2196F3,
			Level::Notice => 0x00BCD4,
			Level::Warning => 0xFF9800,
			Level::Error => 0xF44336,
			Level::Critical => 0x9C27B0,
			Level::Alert => 0xFF5722,
			Level::Emergency => 0x000000,
		};
	}

	private function formatDescription(LogRecord $record): string
	{
		/** @var array<string, mixed> $context */
		$context = $record->context;
		$exception = $context['exception'] ?? null;

		if ($exception instanceof Throwable) {
			return $this->formatExceptionDescription($record->message, $exception);
		}

		$message = $record->message;
		if (strlen($message) > 4000) {
			$message = substr($message, 0, 4000) . '...';
		}

		return '```' . "\n" . $message . "\n" . '```';
	}

	private function formatExceptionDescription(string $logMessage, Throwable $exception): string
	{
		$parts = [];

		// Exception class name as the main headline
		$parts[] = '**Exception:** `' . $exception::class . '`';

		// Exception message in a code block
		$exceptionMessage = $exception->getMessage();
		if ($exceptionMessage !== '') {
			$parts[] = '```' . "\n" . $this->truncate($exceptionMessage, 1500) . "\n" . '```';
		}

		// Where it was thrown
		$originFile = $this->shortenPath($exception->getFile());
		$parts[] = '📍 **Thrown at:** `' . $originFile . ':' . $exception->getLine() . '`';

		// Project-only stack trace
		$projectTrace = $this->getProjectStackTrace($exception);
		if ($projectTrace !== '') {
			$parts[] = '📂 **Stack trace (project):**';
			$parts[] = '```' . "\n" . $projectTrace . "\n" . '```';
		}

		// Previous exception
		$previous = $exception->getPrevious();
		if ($previous instanceof Throwable) {
			$parts[] = '⬅️ **Caused by:** `' . $previous::class . '`';
			$previousMessage = $previous->getMessage();
			if ($previousMessage !== '') {
				$parts[] = '```' . "\n" . $this->truncate($previousMessage, 500) . "\n" . '```';
			}
		}

		if ($logMessage !== '' && $logMessage !== $exceptionMessage && !str_contains($logMessage, $exceptionMessage)) {
			$parts[] = '💬 **Log message:** ' . $this->truncate($logMessage, 300);
		}

		$result = implode("\n", $parts);

		return strlen($result) > 4000 ? substr($result, 0, 4000) . '...' : $result;
	}

	private function getProjectStackTrace(Throwable $exception): string
	{
		$trace = $exception->getTrace();
		$lines = [];
		$count = 0;

		foreach ($trace as $frame) {
			if ($count >= 8) {
				break;
			}

			$file = $frame['file'] ?? null;
			if (!is_string($file)) {
				continue;
			}

			// Skip vendor files
			if (str_contains($file, '/vendor/')) {
				continue;
			}

			$shortFile = $this->shortenPath($file);
			$line = $frame['line'] ?? '?';
			$class = isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : '';
			$type = isset($frame['type']) && is_string($frame['type']) ? $frame['type'] : '';
			$function = isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : '';

			$call = $class !== '' ? $class . $type . $function . '()' : $function . '()';
			$lines[] = $shortFile . ':' . $line . ' → ' . $call;
			$count++;
		}

		return implode("\n", $lines);
	}

	private function shortenPath(string $path): string
	{
		if (str_starts_with($path, $this->projectRoot)) {
			return substr($path, strlen($this->projectRoot));
		}

		// For vendor or other paths, try to show from vendor/ onwards
		$vendorPos = strpos($path, '/vendor/');
		if ($vendorPos !== false) {
			return substr($path, $vendorPos + 1);
		}

		return basename($path);
	}

	/**
	 * @return array<int, array{name: string, value: string, inline?: bool}>
	 */
	private function getFields(LogRecord $record): array
	{
		$fields = [
			[
				'name' => 'Level',
				'value' => $record->level->getName(),
				'inline' => true,
			],
			[
				'name' => 'Channel',
				'value' => $record->channel,
				'inline' => true,
			],
		];

		/** @var array<string, mixed> $extra */
		$extra = $record->extra;

		if (isset($extra['url'])) {
			$httpInfo = [];
			if (is_string($extra['url'])) {
				$httpInfo[] = '**URL:** ' . $extra['url'];
			}

			if (isset($extra['http_method']) && is_string($extra['http_method'])) {
				$httpInfo[] = '**Method:** ' . $extra['http_method'];
			}

			if (isset($extra['ip']) && is_string($extra['ip'])) {
				$httpInfo[] = '**IP:** ' . $extra['ip'];
			}

			if (count($httpInfo) > 0) {
				$fields[] = [
					'name' => '🌐 HTTP Request',
					'value' => implode("\n", $httpInfo),
				];
			}
		}

		if (isset($extra['memory_peak_usage']) && is_string($extra['memory_peak_usage'])) {
			$fields[] = [
				'name' => '💾 Memory',
				'value' => $extra['memory_peak_usage'],
				'inline' => true,
			];
		}

		/** @var array<string, mixed> $context */
		$context = $record->context;
		$otherContext = array_diff_key($context, ['exception' => true]);
		if (count($otherContext) > 0) {
			$contextString = json_encode($otherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if ($contextString !== false && $contextString !== '{}') {
				$fields[] = [
					'name' => '📋 Context',
					'value' => '```json' . "\n" . $this->truncate($contextString, 900) . "\n" . '```',
				];
			}
		}

		return $fields;
	}

	private function truncate(string $text, int $length): string
	{
		if (strlen($text) <= $length) {
			return $text;
		}

		return substr($text, 0, $length) . '...';
	}

}
