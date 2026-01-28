<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock\Asset\Api;

use App\Test\Integration\Api\ApiTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class StockAssetListTest extends ApiTestCase
{

	public function testList(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stocks')
			->withHeader('X-Api-Key', 'test-api-key');
		$response = $this->app->handle($request);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertJson((string) $response->getBody());
	}

}
