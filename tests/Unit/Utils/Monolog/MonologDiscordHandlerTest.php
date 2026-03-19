<?php

declare(strict_types = 1);

namespace App\Test\Unit\Utils\Monolog;

use App\Utils\Monolog\MonologDiscordHandler;
use DateTimeImmutable;
use GuzzleHttp\Client;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class MonologDiscordHandlerTest extends TestCase
{

	private MonologDiscordHandler $handler;

	private Client&Mockery\MockInterface $clientMock;

	protected function setUp(): void
	{
		$this->handler = new MonologDiscordHandler('https://discord.com/api/webhooks/test', Level::Error);
		$this->clientMock = Mockery::mock(Client::class);

		$reflection = new ReflectionClass($this->handler);
		$clientProperty = $reflection->getProperty('client');
		$clientProperty->setValue($this->handler, $this->clientMock);
	}

	protected function tearDown(): void
	{
		Mockery::resetContainer();
	}

	public function testCliCommandFieldIsPresent(): void
	{
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2025-12-29 10:00:00'),
			channel: 'test-channel',
			level: Level::Error,
			message: 'CLI error occurred',
			context: ['exception' => new RuntimeException('CLI test exception')],
			extra: [
				'memory_peak_usage' => '10MB',
			],
		);

		$this->clientMock
			->shouldReceive('post')
			->once()
			->with('https://discord.com/api/webhooks/test', Mockery::on(function (mixed $options): bool {
				$this->assertIsArray($options);

				$embed = $options['json']['embeds'][0];
				$fields = $embed['fields'];

				$fieldNames = array_map(static fn (array $field): string => $field['name'], $fields);

				// Since we're running in CLI (phpunit), CLI Command field should be present
				$this->assertContains('🖥️ CLI Command', $fieldNames);

				return true;
			}));

		$reflection = new ReflectionClass($this->handler);
		$writeMethod = $reflection->getMethod('write');
		$writeMethod->invoke($this->handler, $record);
	}

	public function testHttpRequestFieldWhenUrlPresent(): void
	{
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2025-12-29 10:00:00'),
			channel: 'test-channel',
			level: Level::Error,
			message: 'HTTP error occurred',
			context: ['exception' => new RuntimeException('HTTP test exception')],
			extra: [
				'url' => 'https://example.com/test',
				'http_method' => 'POST',
				'ip' => '192.168.1.1',
			],
		);

		$this->clientMock
			->shouldReceive('post')
			->once()
			->with('https://discord.com/api/webhooks/test', Mockery::on(function (mixed $options): bool {
				$this->assertIsArray($options);

				$embed = $options['json']['embeds'][0];
				$fields = $embed['fields'];

				$fieldNames = array_map(static fn (array $field): string => $field['name'], $fields);

				// HTTP Request field should be present instead of CLI
				$this->assertContains('🌐 HTTP Request', $fieldNames);
				$this->assertNotContains('🖥️ CLI Command', $fieldNames);

				$httpField = null;
				foreach ($fields as $field) {
					if ($field['name'] === '🌐 HTTP Request') {
						$httpField = $field;
					}
				}

				$this->assertNotNull($httpField);
				$this->assertStringContainsString('https://example.com/test', $httpField['value']);
				$this->assertStringContainsString('POST', $httpField['value']);
				$this->assertStringContainsString('192.168.1.1', $httpField['value']);

				return true;
			}));

		$reflection = new ReflectionClass($this->handler);
		$writeMethod = $reflection->getMethod('write');
		$writeMethod->invoke($this->handler, $record);
	}

	public function testIntrospectionInfoInNonExceptionLog(): void
	{
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2025-12-29 10:00:00'),
			channel: 'test-channel',
			level: Level::Error,
			message: 'Something went wrong',
			context: [],
			extra: [
				'file' => '/var/www/src/Stock/Price/StockAssetPriceFacade.php',
				'line' => 42,
				'class' => 'App\\Stock\\Price\\StockAssetPriceFacade',
				'function' => 'downloadPrices',
			],
		);

		$this->clientMock
			->shouldReceive('post')
			->once()
			->with('https://discord.com/api/webhooks/test', Mockery::on(function (mixed $options): bool {
				$this->assertIsArray($options);

				$embed = $options['json']['embeds'][0];
				$description = $embed['description'];

				// Should contain the log message
				$this->assertStringContainsString('Something went wrong', $description);

				// Should contain introspection info
				$this->assertStringContainsString('Logged at:', $description);
				$this->assertStringContainsString('StockAssetPriceFacade', $description);
				$this->assertStringContainsString('downloadPrices()', $description);
				$this->assertStringContainsString(':42', $description);

				return true;
			}));

		$reflection = new ReflectionClass($this->handler);
		$writeMethod = $reflection->getMethod('write');
		$writeMethod->invoke($this->handler, $record);
	}

	public function testNonExceptionLogWithoutIntrospectionData(): void
	{
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2025-12-29 10:00:00'),
			channel: 'test-channel',
			level: Level::Error,
			message: 'Plain error message',
			context: [],
			extra: [],
		);

		$this->clientMock
			->shouldReceive('post')
			->once()
			->with('https://discord.com/api/webhooks/test', Mockery::on(function (mixed $options): bool {
				$this->assertIsArray($options);

				$embed = $options['json']['embeds'][0];
				$description = $embed['description'];

				$this->assertStringContainsString('Plain error message', $description);
				$this->assertStringNotContainsString('Logged at:', $description);

				return true;
			}));

		$reflection = new ReflectionClass($this->handler);
		$writeMethod = $reflection->getMethod('write');
		$writeMethod->invoke($this->handler, $record);
	}

}
