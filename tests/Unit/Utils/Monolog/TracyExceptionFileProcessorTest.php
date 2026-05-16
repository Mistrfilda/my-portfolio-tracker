<?php

declare(strict_types = 1);

namespace App\Test\Unit\Utils\Monolog;

use App\Utils\Monolog\TracyExceptionFileProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TracyExceptionFileProcessorTest extends TestCase
{

	public function testExceptionMessageContainsTracyHtmlLink(): void
	{
		$processor = new TracyExceptionFileProcessor(__DIR__);
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2026-05-02 17:59:15'),
			channel: 'default',
			level: Level::Error,
			message: 'RuntimeException: Test exception in /app/Test.php:10',
			context: ['exception' => new RuntimeException('Test exception')],
		);

		$processed = $processor($record);

		self::assertMatchesRegularExpression(
			'~RuntimeException: Test exception in /app/Test\.php:10  @@  error--\d{4}-\d{2}-\d{2}--\d{2}-\d{2}--[a-f0-9]{10}\.html$~',
			$processed->message,
		);
	}

	public function testMessageWithoutExceptionStaysUnchanged(): void
	{
		$processor = new TracyExceptionFileProcessor(__DIR__);
		$record = new LogRecord(
			datetime: new DateTimeImmutable('2026-05-02 17:59:15'),
			channel: 'default',
			level: Level::Info,
			message: 'Plain message',
			context: [],
		);

		$processed = $processor($record);

		self::assertSame('Plain message', $processed->message);
	}

}
