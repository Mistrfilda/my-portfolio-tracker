<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Api\SlimAppFactory;
use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use function assert;

class PingTest extends TestCase
{

	public function testPing(): void
	{
		$configurator = Bootstrap::boot(true);
		$container = $configurator->createContainer();

		$slimAppFactory = $container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$app = $slimAppFactory->create();

		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/ping');
		$response = $app->handle($request);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('{"status":"ok"}', (string) $response->getBody());
	}

	public function testPingInvalidPath(): void
	{
		$configurator = Bootstrap::boot(true);
		$container = $configurator->createContainer();

		$slimAppFactory = $container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$app = $slimAppFactory->create();

		// Path not in openapi.yaml
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/non-existent');

		$this->expectException(HttpNotFoundException::class);
		$app->handle($request);
	}

}
