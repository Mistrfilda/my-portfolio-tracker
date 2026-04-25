<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock\Asset\Api;

use App\Test\Integration\Api\ApiTestCase;
use Nette\Utils\Json;
use Slim\Psr7\Factory\ServerRequestFactory;

class StockAssetListTest extends ApiTestCase
{

	public function testList(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stocks')
			->withHeader('X-Api-Key', 'test-api-key');
		$response = $this->app->handle($request);

		$this->assertSame(200, $response->getStatusCode());
		$body = (string) $response->getBody();
		$this->assertJson($body);

		$data = Json::decode($body, Json::FORCE_ARRAY);
		$this->assertIsArray($data);

		if ($data === []) {
			return;
		}

		$this->assertArrayHasKey('trend', $data[0]);
		$this->assertArrayHasKey('oneDayChange', $data[0]);
		$this->assertArrayHasKey('sevenDayChange', $data[0]);
		$this->assertArrayHasKey('thirtyDayChange', $data[0]);
	}

	public function testDetailReturnsNotFoundForUnknownStock(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest(
			'GET',
			'/api/v1/stocks/00000000-0000-0000-0000-000000000000',
		)->withHeader('X-Api-Key', 'test-api-key');
		$response = $this->app->handle($request);

		$this->assertSame(404, $response->getStatusCode());
	}

}
