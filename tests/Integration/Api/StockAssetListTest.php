<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Api\SlimAppFactory;
use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use function assert;

class StockAssetListTest extends TestCase
{

	public function testList(): void
	{
		$configurator = Bootstrap::boot(true);
		$container = $configurator->createContainer();

		$slimAppFactory = $container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$app = $slimAppFactory->create();

		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/stocks');
		$response = $app->handle($request);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertJson((string) $response->getBody());
	}

}
