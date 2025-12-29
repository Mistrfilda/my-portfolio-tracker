<?php

declare(strict_types = 1);

namespace App\Test\Unit\Utils;

use App\Utils\Console\ConsoleCurrentOutputHelper;
use App\Utils\Datetime\DatetimeConst;
use App\Utils\Monolog\MonologDiscordHandler;
use App\Utils\Monolog\MonologHelper;
use App\Utils\PublicDirPathGetter;
use App\Utils\Sortable\IncompatibleSortableEntitiesException;
use App\Utils\Sortable\ISortableEntity;
use App\Utils\Sortable\SortException;
use App\Utils\Sortable\SortQueryParameters;
use App\Utils\Sortable\SortService;
use App\Utils\TypeValidator;
use DateTimeImmutable;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use Nette\Http\IRequest;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class UtilsTest extends TestCase
{

	// ===== ConsoleCurrentOutputHelper =====

	public function testConsoleCurrentOutputHelperSetAndGetOutput(): void
	{
		$helper = new ConsoleCurrentOutputHelper();
		$this->assertNull($helper->getOutput());

		$outputMock = Mockery::mock(OutputInterface::class);
		$helper->setOutput($outputMock);

		$this->assertSame($outputMock, $helper->getOutput());
	}

	// ===== DatetimeConst =====

	public function testDatetimeConstHasExpectedConstants(): void
	{
		$this->assertEquals('d. m. Y H:i:s', DatetimeConst::SYSTEM_DATETIME_FORMAT);
		$this->assertEquals('d. m. Y H:i', DatetimeConst::SYSTEM_DATETIME_FORMAT_WITHOUT_SECONDS);
		$this->assertEquals('d. m. Y', DatetimeConst::SYSTEM_DATE_FORMAT);
		$this->assertEquals('---', DatetimeConst::DEFAULT_NULL_DATETIME_PLACEHOLDER);
		$this->assertIsArray(DatetimeConst::CZECH_MONTHS);
		$this->assertCount(12, DatetimeConst::CZECH_MONTHS);
		$this->assertEquals('Leden', DatetimeConst::CZECH_MONTHS[1]);
		$this->assertEquals('Prosinec', DatetimeConst::CZECH_MONTHS[12]);
	}

	// ===== MonologHelper =====

	public function testMonologHelperFormatMessageFromException(): void
	{
		$exception = new RuntimeException('Test error message', 123);
		$formatted = MonologHelper::formatMessageFromException($exception);

		$this->assertStringContainsString('RuntimeException', $formatted);
		$this->assertStringContainsString('Test error message', $formatted);
		$this->assertStringContainsString('#123', $formatted);
		$this->assertStringContainsString(__FILE__, $formatted);
	}

	public function testMonologHelperFormatMessageFromExceptionWithoutCode(): void
	{
		$exception = new RuntimeException('Error without code');
		$formatted = MonologHelper::formatMessageFromException($exception);

		$this->assertStringNotContainsString('#0', $formatted);
	}

	// ===== PublicDirPathGetter =====

	public function testPublicDirPathGetterReturnsCorrectPaths(): void
	{
		$wwwDir = '/var/www/public';

		$urlMock = Mockery::mock(UrlScript::class);
		$urlMock->shouldReceive('getBasePath')
			->andReturn('/app/');
		$urlMock->shouldReceive('getBaseUrl')
			->andReturn('https://example.com/app/');

		$requestMock = Mockery::mock(IRequest::class);
		$requestMock->shouldReceive('getUrl')
			->andReturn($urlMock);

		$getter = new PublicDirPathGetter($wwwDir, $requestMock);

		$this->assertEquals('/app', $getter->getBasePath());
		$this->assertEquals('https://example.com/app', $getter->getBaseUrl());
		$this->assertEquals($wwwDir, $getter->getWwwDir());
	}

	// ===== SortQueryParameters =====

	public function testSortQueryParametersGetParameter(): void
	{
		$uuid = Uuid::uuid4();
		$params = new SortQueryParameters([
			'id' => 123,
			'name' => 'test',
			'uuid' => $uuid,
		]);

		$this->assertEquals(123, $params->getParameter('id'));
		$this->assertEquals('test', $params->getParameter('name'));
		$this->assertSame($uuid, $params->getParameter('uuid'));
	}

	public function testSortQueryParametersHasParameter(): void
	{
		$params = new SortQueryParameters(['key' => 'value']);

		$this->assertTrue($params->hasParameter('key'));
		$this->assertFalse($params->hasParameter('nonexistent'));
	}

	public function testSortQueryParametersThrowsExceptionForMissingParameter(): void
	{
		$params = new SortQueryParameters([]);

		$this->expectException(SortException::class);
		$this->expectExceptionMessage('Missing parameter missing_key');

		$params->getParameter('missing_key');
	}

	// ===== SortService =====

	public function testSortServiceSetNewPositionWithNext(): void
	{
		$entityMock = Mockery::mock(ISortableEntity::class);
		$nextMock = Mockery::mock(ISortableEntity::class);

		$entityMock->shouldReceive('getSortingGroup')->andReturn('group1');
		$entityMock->shouldReceive('getSortPosition')->andReturn(10);
		$nextMock->shouldReceive('getSortingGroup')->andReturn('group1');
		$nextMock->shouldReceive('getSortPosition')->andReturn(5);
		$entityMock->shouldReceive('sort')->once()->with(5);

		$service = new SortService();
		$service->setNewPosition($entityMock, null, $nextMock);

		$this->assertTrue(true);
	}

	public function testSortServiceSetNewPositionWithPrevious(): void
	{
		$entityMock = Mockery::mock(ISortableEntity::class);
		$previousMock = Mockery::mock(ISortableEntity::class);

		$entityMock->shouldReceive('getSortingGroup')->andReturn('group1');
		$entityMock->shouldReceive('getSortPosition')->andReturn(5);
		$previousMock->shouldReceive('getSortingGroup')->andReturn('group1');
		$previousMock->shouldReceive('getSortPosition')->andReturn(10);
		$entityMock->shouldReceive('sort')->once()->with(10);

		$service = new SortService();
		$service->setNewPosition($entityMock, $previousMock, null);

		$this->assertTrue(true);
	}

	public function testSortServiceThrowsExceptionWhenBothNull(): void
	{
		$entityMock = Mockery::mock(ISortableEntity::class);
		$service = new SortService();

		$this->expectException(IncompatibleSortableEntitiesException::class);
		$this->expectExceptionMessage('At least one of `previous` and `next` must be set');

		$service->setNewPosition($entityMock, null, null);
	}

	public function testSortServiceThrowsExceptionForIncompatibleGroups(): void
	{
		$entityMock = Mockery::mock(ISortableEntity::class);
		$nextMock = Mockery::mock(ISortableEntity::class);

		$entityMock->shouldReceive('getSortingGroup')->andReturn('group1');
		$nextMock->shouldReceive('getSortingGroup')->andReturn('group2');

		$service = new SortService();

		$this->expectException(IncompatibleSortableEntitiesException::class);
		$this->expectExceptionMessage('Next does not belong to the same sorting group');

		$service->setNewPosition($entityMock, null, $nextMock);
	}

	// ===== TypeValidator =====

	public function testTypeValidatorValidateString(): void
	{
		$this->assertEquals('test', TypeValidator::validateString('test'));
	}

	public function testTypeValidatorValidateStringThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateString(123);
	}

	public function testTypeValidatorValidateNullableString(): void
	{
		$this->assertEquals('test', TypeValidator::validateNullableString('test'));
		$this->assertNull(TypeValidator::validateNullableString(null));
	}

	public function testTypeValidatorValidateInt(): void
	{
		$this->assertEquals(123, TypeValidator::validateInt(123));
		$this->assertEquals(456, TypeValidator::validateInt('456'));
	}

	public function testTypeValidatorValidateIntThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateInt('not a number');
	}

	public function testTypeValidatorValidateNullableInt(): void
	{
		$this->assertEquals(123, TypeValidator::validateNullableInt(123));
		$this->assertNull(TypeValidator::validateNullableInt(null));
	}

	public function testTypeValidatorValidateFloat(): void
	{
		$this->assertEquals(12.34, TypeValidator::validateFloat(12.34));
		$this->assertEquals(56.0, TypeValidator::validateFloat('56'));
	}

	public function testTypeValidatorValidateFloatThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateFloat('not a float');
	}

	public function testTypeValidatorValidateNullableFloat(): void
	{
		$this->assertEquals(12.34, TypeValidator::validateNullableFloat(12.34));
		$this->assertNull(TypeValidator::validateNullableFloat(null));
	}

	public function testTypeValidatorValidateBool(): void
	{
		$this->assertTrue(TypeValidator::validateBool(true));
		$this->assertTrue(TypeValidator::validateBool('true'));
		$this->assertTrue(TypeValidator::validateBool(1));
		$this->assertTrue(TypeValidator::validateBool('1'));

		$this->assertFalse(TypeValidator::validateBool(false));
		$this->assertFalse(TypeValidator::validateBool('false'));
		$this->assertFalse(TypeValidator::validateBool(0));
		$this->assertFalse(TypeValidator::validateBool('0'));
	}

	public function testTypeValidatorValidateBoolThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateBool('not a bool');
	}

	public function testTypeValidatorValidateNullableBool(): void
	{
		$this->assertTrue(TypeValidator::validateNullableBool(true));
		$this->assertNull(TypeValidator::validateNullableBool(null));
	}

	public function testTypeValidatorValidateImmutableDatetime(): void
	{
		$datetime = new ImmutableDateTime('2025-12-29 10:00:00');
		$result = TypeValidator::validateImmutableDatetime($datetime);

		$this->assertInstanceOf(ImmutableDateTime::class, $result);
		$this->assertSame($datetime, $result);
	}

	public function testTypeValidatorValidateNullableImmutableDatetime(): void
	{
		$datetime = new ImmutableDateTime('2025-12-29 10:00:00');
		$this->assertInstanceOf(ImmutableDateTime::class, TypeValidator::validateNullableImmutableDatetime($datetime));
		$this->assertNull(TypeValidator::validateNullableImmutableDatetime(null));
	}

	public function testTypeValidatorValidateArray(): void
	{
		$array = [1, 2, 3];
		$this->assertEquals($array, TypeValidator::validateArray($array));
	}

	public function testTypeValidatorValidateArrayThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateArray('not an array');
	}

	public function testTypeValidatorValidateIterable(): void
	{
		$array = [1, 2, 3];
		$this->assertEquals($array, TypeValidator::validateIterable($array));
	}

	public function testTypeValidatorValidateIterableThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateIterable('not iterable');
	}

	public function testTypeValidatorValidateInstanceOf(): void
	{
		$datetime = new ImmutableDateTime('now');
		$result = TypeValidator::validateInstanceOf($datetime, ImmutableDateTime::class);

		$this->assertInstanceOf(ImmutableDateTime::class, $result);
		$this->assertSame($datetime, $result);
	}

	public function testTypeValidatorValidateInstanceOfThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		TypeValidator::validateInstanceOf('string', ImmutableDateTime::class);
	}

	public function testMonologDiscordHandlerWritesLogToDiscord(): void
	{
		$webhookUrl = 'https://discord.com/api/webhooks/test';
		$handler = new MonologDiscordHandler($webhookUrl, Level::Error);

		// Create a mock Client using reflection to replace the internal client
		$clientMock = Mockery::mock(Client::class);

		$reflection = new ReflectionClass($handler);
		$clientProperty = $reflection->getProperty('client');
		$clientProperty->setValue($handler, $clientMock);

		$datetime = new DateTimeImmutable('2025-12-29 10:00:00');
		$exception = new RuntimeException('Test exception message');

		$record = new LogRecord(
			datetime: $datetime,
			channel: 'test-channel',
			level: Level::Error,
			message: 'Test error message',
			context: ['exception' => $exception],
			extra: [
				'url' => 'https://example.com/test',
				'http_method' => 'GET',
				'ip' => '127.0.0.1',
				'file' => '/var/www/test.php',
				'line' => 42,
			],
		);

		// Expect that post method will be called with proper structure
		$clientMock
			->shouldReceive('post')
			->once()
			->with($webhookUrl, Mockery::on(function ($options) {
				// Validate the structure of the Discord webhook payload
				$this->assertArrayHasKey('json', $options);
				$this->assertArrayHasKey('embeds', $options['json']);
				$this->assertIsArray($options['json']['embeds']);
				$this->assertCount(1, $options['json']['embeds']);

				$embed = $options['json']['embeds'][0];

				// Check title
				$this->assertArrayHasKey('title', $embed);
				$this->assertEquals('❌ Error', $embed['title']);

				// Check description contains the message
				$this->assertArrayHasKey('description', $embed);
				$this->assertStringContainsString('Test error message', $embed['description']);

				// Check color (Error should be red - 0xF44336)
				$this->assertArrayHasKey('color', $embed);
				$this->assertEquals(0xF44336, $embed['color']);

				// Check timestamp
				$this->assertArrayHasKey('timestamp', $embed);

				// Check fields
				$this->assertArrayHasKey('fields', $embed);
				$this->assertIsArray($embed['fields']);

				return true;
			}));

		// Trigger the write by handling the record
		$reflection = new ReflectionClass($handler);
		$writeMethod = $reflection->getMethod('write');
		$writeMethod->invoke($handler, $record);

		$this->assertTrue(true);
	}

}
