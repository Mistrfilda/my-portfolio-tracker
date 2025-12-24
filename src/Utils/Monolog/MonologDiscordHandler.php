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

	public function __construct(
		private string $webhookUrl,
		Level $level = Level::Error,
	)
	{
		parent::__construct($level);
		$this->client = new Client();
	}

	protected function write(LogRecord $record): void
	{
		try {
			$this->client->post($this->webhookUrl, [
				'json' => [
					'embeds' => [
						[
							'title' => $this->getTitle($record->level),
							'description' => $this->formatMessage($record),
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

	private function formatMessage(LogRecord $record): string
	{
		$message = $record->message;

		if (strlen($message) > 4000) {
			$message = substr($message, 0, 4000) . '...';
		}

		return '```' . "\n" . $message . "\n" . '```';
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
		if (count($extra) > 0) {
			$fields = array_merge($fields, $this->formatExtraFields($extra));
		}

		/** @var array<string, mixed> $context */
		$context = $record->context;
		if (count($context) > 0) {
			$fields = array_merge($fields, $this->formatContextFields($context));
		}

		return $fields;
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<int, array{name: string, value: string, inline?: bool}>
	 */
	private function formatExtraFields(array $extra): array
	{
		$fields = [];

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

			if (isset($extra['server']) && is_string($extra['server'])) {
				$httpInfo[] = '**Server:** ' . $extra['server'];
			}

			if (count($httpInfo) > 0) {
				$fields[] = [
					'name' => '🌐 HTTP Request',
					'value' => implode("\n", $httpInfo),
				];
			}
		}

		if (isset($extra['file']) && isset($extra['line'])) {
			$file = is_string($extra['file']) ? $extra['file'] : '';
			$line = is_int($extra['line'])
				? (string) $extra['line']
				: (is_string($extra['line']) ? $extra['line'] : '');

			if ($file !== '' && $line !== '') {
				$fields[] = [
					'name' => '📍 Location',
					'value' => '```' . "\n" . $file . ':' . $line . "\n" . '```',
				];
			}
		}

		if (isset($extra['class']) || isset($extra['function'])) {
			$location = '';
			if (isset($extra['class']) && is_string($extra['class'])) {
				$location .= $extra['class'];
				if (isset($extra['callType']) && is_string($extra['callType'])) {
					$location .= $extra['callType'];
				}
			}

			if (isset($extra['function']) && is_string($extra['function'])) {
				$location .= $extra['function'] . '()';
			}

			if ($location !== '') {
				$fields[] = [
					'name' => '🎯 Call',
					'value' => '`' . $location . '`',
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

		return $fields;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<int, array{name: string, value: string, inline?: bool}>
	 */
	private function formatContextFields(array $context): array
	{
		$fields = [];

		if (isset($context['exception'])) {
			$exception = $context['exception'];
			if ($exception instanceof Throwable) {
				$exceptionInfo = sprintf(
					"**%s**\n```\n%s\n```\n**File:** %s:%d",
					$exception::class,
					$this->truncate($exception->getMessage(), 500),
					basename($exception->getFile()),
					$exception->getLine(),
				);

				$fields[] = [
					'name' => '🔥 Exception',
					'value' => $exceptionInfo,
				];
			} elseif (is_string($exception)) {
				$fields[] = [
					'name' => '🔥 Exception',
					'value' => '```' . "\n" . $this->truncate($exception, 1000) . "\n" . '```',
				];
			}
		}

		$otherContext = array_diff_key($context, ['exception' => true]);
		if (count($otherContext) > 0) {
			$contextString = json_encode($otherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if ($contextString !== false && strlen($contextString) < 1024) {
				$fields[] = [
					'name' => '📋 Context',
					'value' => '```json' . "\n" . $contextString . "\n" . '```',
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
