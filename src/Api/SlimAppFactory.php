<?php

declare(strict_types = 1);

namespace App\Api;

use Nette\Http\Request;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Middleware\ErrorMiddleware;
use const PHP_SAPI;

class SlimAppFactory
{

	/**
	 * @param array<string> $corsAllowedOrigins
	 */
	public function __construct(
		private array $corsAllowedOrigins,
		private bool $debugMode,
		private ContainerInterface $container,
		private Request $httpRequest,
		private RouterFactory $routerFactory,
		private ErrorMiddleware $errorMiddleware,
	)
	{
	}

	/**
	 * @return App<ContainerInterface|null>
	 */
	public function create(): App
	{
		AppFactory::setContainer($this->container);
		/** @var App<ContainerInterface|null> $app */
		$app = AppFactory::create();

		$basePath = $this->httpRequest->getUrl()->getBasePath();
		$basePath = rtrim($basePath, '/');
		if ($basePath !== '' && PHP_SAPI !== 'cli') {
			$app->setBasePath($basePath);
		}

		$this->routerFactory->registerRoutes($app);

		$app->addBodyParsingMiddleware();
		$app->addRoutingMiddleware();
		$this->addCorsMiddleware($app);

		if (!$this->debugMode) {
			$app->add($this->errorMiddleware);
		}

		$app->add(new ContentLengthMiddleware());

		return $app;
	}

	/**
	 * @param App<ContainerInterface|null> $app
	 */
	private function addCorsMiddleware(App $app): void
	{
		$corsAllowedOrigins = $this->corsAllowedOrigins;
		$app->add(
			function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($corsAllowedOrigins): ResponseInterface {
				$response = $handler->handle($request);
				$origin = $request->getHeaderLine('Origin');

				if (in_array($origin, $corsAllowedOrigins, true)) {
					return $response
						->withHeader('Access-Control-Allow-Origin', $origin)
						->withHeader(
							'Access-Control-Allow-Headers',
							'X-Requested-With, Content-Type, Accept, Origin, Authorization',
						)
						->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
				}

				return $response;
			},
		);
	}

}
