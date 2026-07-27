<?php

declare(strict_types = 1);

namespace App\Test\Integration\Home\Device\Record\Api;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Doctrine\NoEntityFoundException;
use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceType;
use App\Home\Device\Record\HomeDeviceRecord;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\Home\Device\Record\HomeDeviceRecordUnit;
use App\Home\Home;
use App\Test\Integration\Api\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Exception\HttpBadRequestException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class HomeDeviceRecordApiTest extends ApiTestCase
{

	private EntityManagerInterface $entityManager;

	private AppAdmin $appAdmin;

	private HomeDevice $device;

	protected function setUp(): void
	{
		parent::setUp();

		$this->entityManager = $this->getService(EntityManagerInterface::class);
		$suffix = Uuid::uuid4()->toString();
		$now = new ImmutableDateTime('2026-01-01 10:00:00');
		$this->appAdmin = new AppAdmin(
			'Home API Admin',
			'home-api-' . $suffix,
			'home-api-' . $suffix . '@example.test',
			'password',
			$now,
			false,
			false,
		);
		$home = new Home('Home API ' . $suffix, $now);
		$this->device = new HomeDevice(
			$home,
			'device-' . $suffix,
			'Temperature sensor',
			HomeDeviceType::TEMPERATURE,
			$now,
		);
		$this->entityManager->persist($this->appAdmin);
		$this->entityManager->persist($home);
		$this->entityManager->persist($this->device);
		$this->entityManager->flush();

		$currentAppAdminGetter = $this->getService(CurrentAppAdminGetter::class);
		$currentAppAdminGetter->setApiAppAdmin($this->appAdmin);
	}

	#[DataProvider('provideValues')]
	public function testCreatePersistsSupportedValueTypes(
		float|bool|string|null $value,
		string|null $unit,
	): void
	{
		$body = ['internalId' => $this->device->getInternalId(), 'value' => $value];
		if ($unit !== null) {
			$body['unit'] = $unit;
		}

		$response = $this->app->handle($this->createJsonRequest($body));

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		$data = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($data);
		self::assertSame($this->device->getInternalId(), $data['deviceInternalId']);
		self::assertSame($value, $data['value']);
		self::assertSame($unit, $data['unit']);

		$recordRepository = $this->getService(HomeDeviceRecordRepository::class);
		$record = $recordRepository->getById(Uuid::fromString((string) $data['id']));
		self::assertSame($this->appAdmin->getId()->toString(), $record->getCreatedBy()?->getId()->toString());
		self::assertSame(is_float($value) ? $value : null, $record->getFloatValue());
		self::assertSame(is_bool($value) ? $value : null, $record->getBooleanValue());
		self::assertSame(is_string($value) ? $value : null, $record->getStringValue());
	}

	public function testCreateRejectsRequestWithoutInternalId(): void
	{
		$this->expectException(HttpBadRequestException::class);

		$this->app->handle($this->createJsonRequest(['value' => 20.0]));
	}

	public function testCreateRejectsUnknownDevice(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->app->handle($this->createJsonRequest([
			'internalId' => 'unknown-device-' . Uuid::uuid4()->toString(),
			'value' => 20.0,
		]));
	}

	public function testListReturnsLatestLimitedRecordsAndDeviceWithoutRecords(): void
	{
		$home = $this->device->getHome();
		$secondDevice = new HomeDevice(
			$home,
			'empty-device-' . Uuid::uuid4()->toString(),
			'Door sensor',
			HomeDeviceType::SENSOR,
			new ImmutableDateTime('2026-01-01'),
		);
		$olderRecord = new HomeDeviceRecord(
			$this->device,
			$this->appAdmin,
			null,
			20.0,
			null,
			HomeDeviceRecordUnit::CELSIUS,
			new ImmutableDateTime('2026-01-01 08:00:00'),
		);
		$newerRecord = new HomeDeviceRecord(
			$this->device,
			$this->appAdmin,
			null,
			21.5,
			null,
			HomeDeviceRecordUnit::CELSIUS,
			new ImmutableDateTime('2026-01-02 08:00:00'),
		);
		$this->entityManager->persist($secondDevice);
		$this->entityManager->persist($olderRecord);
		$this->entityManager->persist($newerRecord);
		$this->entityManager->flush();

		$request = (new ServerRequestFactory())->createServerRequest(
			'GET',
			'/api/v1/home/device/records?limit=1',
		)->withHeader('X-Api-Key', 'test-api-key');
		$response = $this->app->handle($request);

		self::assertSame(200, $response->getStatusCode());
		$data = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($data);
		$devicesByInternalId = [];
		foreach ($data as $device) {
			self::assertIsArray($device);
			$devicesByInternalId[$device['internalId']] = $device;
		}

		$deviceData = $devicesByInternalId[$this->device->getInternalId()];
		self::assertCount(1, $deviceData['records']);
		self::assertSame($newerRecord->getId()->toString(), $deviceData['latestRecord']['id']);
		self::assertSame($newerRecord->getId()->toString(), $deviceData['records'][0]['id']);
		self::assertSame(21.5, $deviceData['records'][0]['value']);

		$emptyDeviceData = $devicesByInternalId[$secondDevice->getInternalId()];
		self::assertNull($emptyDeviceData['latestRecord']);
		self::assertSame([], $emptyDeviceData['records']);
	}

	/**
	 * @return array<string, array{float|bool|string|null, string|null}>
	 */
	public static function provideValues(): array
	{
		return [
			'float with unit' => [21.5, HomeDeviceRecordUnit::CELSIUS->value],
			'boolean' => [true, null],
			'string' => ['open', null],
			'null' => [null, null],
		];
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function createJsonRequest(array $body): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest('POST', '/api/v1/home/device/record')
			->withHeader('X-Api-Key', 'test-api-key')
			->withHeader('Content-Type', 'application/json')
			->withBody(new StreamFactory()->createStream(Json::encode($body)));
	}

}
