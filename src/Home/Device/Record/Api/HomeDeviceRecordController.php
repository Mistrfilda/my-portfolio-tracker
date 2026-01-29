<?php

declare(strict_types = 1);

namespace App\Home\Device\Record\Api;

use App\Home\Device\Record\HomeDeviceRecordFacade;
use App\Home\Device\Record\HomeDeviceRecordUnit;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeDeviceRecordController
{

	public function __construct(
		private HomeDeviceRecordFacade $homeDeviceRecordFacade,
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
		assert($value === null || is_numeric($value));

		$unitString = $body['unit'] ?? null;
		assert($unitString === null || is_string($unitString));

		$unit = $unitString !== null ? HomeDeviceRecordUnit::from($unitString) : null;
		$floatValue = $value !== null ? (float) $value : null;

		$record = $this->homeDeviceRecordFacade->createByInternalId(
			$internalId,
			null,
			$floatValue,
			$unit,
		);

		$response->getBody()->write(Json::encode([
			'id' => $record->getId()->toString(),
			'deviceInternalId' => $record->getHomeDevice()->getInternalId(),
			'value' => $record->getFloatValue(),
			'unit' => $record->getUnit()?->value,
			'createdAt' => $record->getCreatedAt()->format('c'),
		]));

		return $response->withHeader('Content-Type', 'application/json');
	}

}
