<?php

declare(strict_types = 1);

namespace App\Home\Device\Record\Api;

use App\Home\Device\HomeDeviceRepository;
use App\Home\Device\Record\HomeDeviceRecordFacade;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\Home\Device\Record\HomeDeviceRecordUnit;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeDeviceRecordController
{

	public function __construct(
		private HomeDeviceRecordFacade $homeDeviceRecordFacade,
		private HomeDeviceRepository $homeDeviceRepository,
		private HomeDeviceRecordRepository $homeDeviceRecordRepository,
	)
	{
	}

	public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$body = $request->getParsedBody();
		assert(is_array($body));

		$internalId = $body['internalId'];
		assert(is_string($internalId));

		$value = $body['value'] ?? null;
		assert($value === null || is_numeric($value) || is_bool($value) || is_string($value));

		$unitString = $body['unit'] ?? null;
		assert($unitString === null || is_string($unitString));

		$unit = $unitString !== null ? HomeDeviceRecordUnit::from($unitString) : null;

		$floatValue = null;
		$booleanValue = null;
		$stringValue = null;

		if (is_numeric($value)) {
			$floatValue = (float) $value;
		} elseif (is_bool($value)) {
			$booleanValue = $value;
		} elseif (is_string($value)) {
			$stringValue = $value;
		}

		$record = $this->homeDeviceRecordFacade->createByInternalId(
			$internalId,
			$stringValue,
			$floatValue,
			$booleanValue,
			$unit,
		);

		$response->getBody()->write(Json::encode([
			'id' => $record->getId()->toString(),
			'deviceInternalId' => $record->getHomeDevice()->getInternalId(),
			'value' => $record->getBooleanValue() ?? $record->getFloatValue() ?? $record->getStringValue(),
			'unit' => $record->getUnit()?->value,
			'createdAt' => $record->getCreatedAt()->format('c'),
		]));

		return $response->withHeader('Content-Type', 'application/json');
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$queryParams = $request->getQueryParams();
		$limitParam = $queryParams['limit'] ?? null;
		$limit = is_numeric($limitParam) ? (int) $limitParam : 10;

		$devices = $this->homeDeviceRepository->findAll();
		$result = [];

		foreach ($devices as $device) {
			$records = $this->homeDeviceRecordRepository->findLatestForDevice($device, $limit);

			$latestRecord = $records[0] ?? null;

			$result[] = [
				'id' => $device->getId()->toString(),
				'internalId' => $device->getInternalId(),
				'name' => $device->getName(),
				'type' => $device->getType()->value,
				'latestRecord' => $latestRecord !== null ? [
					'id' => $latestRecord->getId()->toString(),
					'value' => $latestRecord->getBooleanValue() ?? $latestRecord->getFloatValue() ?? $latestRecord->getStringValue(),
					'unit' => $latestRecord->getUnit()?->value,
					'createdAt' => $latestRecord->getCreatedAt()->format('c'),
				] : null,
				'records' => array_map(
					static fn ($record) => [
						'id' => $record->getId()->toString(),
						'value' => $record->getBooleanValue() ?? $record->getFloatValue() ?? $record->getStringValue(),
						'unit' => $record->getUnit()?->value,
						'createdAt' => $record->getCreatedAt()->format('c'),
					],
					$records,
				),
			];
		}

		$response->getBody()->write(Json::encode($result));

		return $response->withHeader('Content-Type', 'application/json');
	}

}
