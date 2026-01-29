<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

class TestApiKeyMiddleware implements MiddlewareInterface
{

	/**
	 * @param array<array{apiKey: string, appAdminId: string}> $apiKeys
	 */
	public function __construct(
		private array $apiKeys,
		private CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$apiKey = $request->getHeaderLine('X-Api-Key');

		$appAdminId = $this->findAppAdminIdByApiKey($apiKey);
		if ($appAdminId === null) {
			throw new HttpUnauthorizedException($request, 'Invalid API key provided');
		}

		$testAppAdmin = new AppAdmin(
			'Test Admin',
			'test-admin',
			'test@test.com',
			'password',
			new ImmutableDateTime(),
			false,
			false,
		);
		$this->currentAppAdminGetter->setApiAppAdmin($testAppAdmin);

		return $handler->handle($request);
	}

	private function findAppAdminIdByApiKey(string $apiKey): string|null
	{
		foreach ($this->apiKeys as $keyConfig) {
			if ($keyConfig['apiKey'] === $apiKey) {
				return $keyConfig['appAdminId'];
			}
		}

		return null;
	}

}
