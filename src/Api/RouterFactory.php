<?php

declare(strict_types = 1);

namespace App\Api;

use App\Api\Controller\PingController;
use App\Stock\Asset\Api\StockAssetController;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class RouterFactory
{

	public function __construct(
		private RequestValidationMiddleware $requestValidationMiddleware,
		private MiddlewareInterface $apiKeyMiddleware,
	)
	{
	}

	/**
	 * @param App<ContainerInterface|null> $slimApp
	 */
	public function registerRoutes(App $slimApp): void
	{
		$v1Group = $slimApp->group('/api/v1', function (RouteCollectorProxy $v1): void {
			$v1->get('/ping', PingController::class . ':ping');
			$v1->get('/stocks', StockAssetController::class . ':list');
		});

		$v1Group->add($this->requestValidationMiddleware);
		$v1Group->add($this->apiKeyMiddleware);
	}

}
