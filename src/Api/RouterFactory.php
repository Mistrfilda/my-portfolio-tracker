<?php

declare(strict_types = 1);

namespace App\Api;

use App\Api\Controller\MeController;
use App\Api\Controller\PingController;
use App\Dashboard\Api\DashboardValueController;
use App\Home\Device\Record\Api\HomeDeviceRecordController;
use App\OAuth2\Http\AuthorizationController;
use App\OAuth2\Http\RevocationController;
use App\OAuth2\Http\TokenController;
use App\OAuth2\OAuthScope;
use App\Stock\Asset\Api\StockAssetController;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class RouterFactory
{

	public function __construct(
		private RequestValidationMiddleware $requestValidationMiddleware,
		private ApiAuthenticationMiddlewareFactory $authentication,
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
			$v1->get('/me', MeController::class . ':me');
			$v1->get('/dashboard/values', DashboardValueController::class . ':list');
			$v1->get('/stocks', StockAssetController::class . ':list');
			$v1->get('/stocks/{id}', StockAssetController::class . ':detail');
		});

		$v1Group->add($this->requestValidationMiddleware);
		$v1Group->add($this->authentication->create(OAuthScope::API_READ));

		$slimApp->post('/api/v1/home/device/record', HomeDeviceRecordController::class . ':create')
			->add($this->requestValidationMiddleware)
			->add($this->authentication->create(OAuthScope::HOME_WRITE, true));
		$slimApp->get('/api/v1/home/device/records', HomeDeviceRecordController::class . ':list')
			->add($this->requestValidationMiddleware)
			->add($this->authentication->create(OAuthScope::HOME_READ, true));

		$slimApp->post('/api/oauth/token', TokenController::class . ':token');
		$slimApp->post('/api/oauth/revoke', RevocationController::class . ':revoke');
		$slimApp->map(['GET', 'POST'], '/api/oauth/authorize', AuthorizationController::class . ':authorize');
	}

}
