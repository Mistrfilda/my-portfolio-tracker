<?php

declare(strict_types = 1);

namespace App\Mcp\Http;

use App\Mcp\McpServerFactory;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Throwable;

class McpController
{

	/**
	 * @param list<string> $corsAllowedOrigins
	 */
	public function __construct(
		private McpServerFactory $serverFactory,
		private LoggerInterface $logger,
		private array $corsAllowedOrigins = [],
	)
	{
	}

	public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$responseFactory = new ResponseFactory();
		$streamFactory = new StreamFactory();

		$transport = new StreamableHttpTransport(
			request: $request,
			responseFactory: $responseFactory,
			streamFactory: $streamFactory,
			logger: $this->logger,
			middleware: [
				new DnsRebindingProtectionMiddleware(
					allowedHosts: ['*'],
					allowedOrigins: $this->corsAllowedOrigins,
					responseFactory: $responseFactory,
					streamFactory: $streamFactory,
				),
			],
		);

		try {
			return $this->serverFactory->create()->run($transport);
		} catch (Throwable $e) {
			$this->logger->error('Unhandled error in MCP HTTP endpoint.', [
				'exception' => $e,
			]);

			$response->getBody()->write(Json::encode([
				'jsonrpc' => '2.0',
				'error' => [
					'code' => -32603,
					'message' => 'Internal server error.',
				],
				'id' => null,
			]));

			return $response
				->withStatus(500)
				->withHeader('Content-Type', 'application/json');
		}
	}

}
