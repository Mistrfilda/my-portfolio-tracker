<?php

declare(strict_types = 1);

namespace App\Api;

use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Exception\HttpUnauthorizedException;

class ApiKeyMiddleware implements MiddlewareInterface
{

	/**
	 * @param array<array{apiKey: string, appAdminId: string}> $apiKeys
	 */
	public function __construct(
		private array $apiKeys,
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private AppAdminRepository $appAdminRepository,
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

		$appAdmin = $this->appAdminRepository->findById(Uuid::fromString($appAdminId));
		$this->currentAppAdminGetter->setApiAppAdmin($appAdmin);

		return $handler->handle($request);
	}

	private function findAppAdminIdByApiKey(string $apiKey): string|null
	{
		foreach ($this->apiKeys as $keyConfig) {
			if ($apiKey !== '' && hash_equals($keyConfig['apiKey'], $apiKey)) {
				return $keyConfig['appAdminId'];
			}
		}

		return null;
	}

}
