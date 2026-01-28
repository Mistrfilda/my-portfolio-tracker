<?php

declare(strict_types = 1);

namespace App\Api;

use App\Api\Controller\PingController;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class RouterFactory
{

	public function __construct(private RequestValidationMiddleware $requestValidationMiddleware)
	{
	}

	/**
	 * @param App<ContainerInterface|null> $slimApp
	 */
	public function registerRoutes(App $slimApp): void
	{
		$v1Group = $slimApp->group('/api/v1', function (RouteCollectorProxy $v1): void {
			$v1->get('/ping', PingController::class . ':ping');
		});

		$v1Group->add($this->requestValidationMiddleware);
	}

}
