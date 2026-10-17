<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;

class PingTest extends ApiTestCase
{

	public function testPing(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/ping')
			->withHeader('Authorization', 'Bearer ' . $this->getOAuthAccessToken());
		$response = $this->app->handle($request);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('{"status":"ok"}', (string) $response->getBody());
	}

	public function testPingInvalidPath(): void
	{
		// Path not in openapi.yaml
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/non-existent')
			->withHeader('X-Api-Key', 'test-api-key');

		$this->expectException(HttpNotFoundException::class);
		$this->app->handle($request);
	}

	public function testPingUnauthorized(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/ping')
			->withHeader('X-Api-Key', 'invalid-api-key');

		self::assertSame(401, $this->app->handle($request)->getStatusCode());
	}

}
