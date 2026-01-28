<?php

declare(strict_types = 1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

class ApiKeyMiddleware implements MiddlewareInterface
{

	/**
	 * @param array<string> $apiKeys
	 */
	public function __construct(private array $apiKeys)
	{
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$apiKey = $request->getHeaderLine('X-Api-Key');

		if (!in_array($apiKey, $this->apiKeys, true)) {
			throw new HttpUnauthorizedException($request, 'Invalid API key provided');
		}

		return $handler->handle($request);
	}

}
