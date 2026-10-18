<?php

declare(strict_types = 1);

namespace App\Api;

use App\Api\Controller\MeController;
use App\Api\Controller\PingController;
use App\Currency\Api\ExchangeRateController;
use App\Dashboard\Api\DashboardSummaryController;
use App\Dashboard\Api\DashboardValueController;
use App\Home\Device\Record\Api\HomeDeviceRecordController;
use App\OAuth2\Http\AuthorizationController;
use App\OAuth2\Http\RevocationController;
use App\OAuth2\Http\TokenController;
use App\OAuth2\OAuthScope;
use App\Stock\Asset\Api\StockAssetController;
use App\Stock\Dividend\Api\StockDividendController;
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
			$v1->get('/stocks', StockAssetController::class . ':list');
		});

		$v1Group->add($this->requestValidationMiddleware);
		$v1Group->add($this->authentication->create(OAuthScope::API_READ));

		$userGroup = $slimApp->group('/api/v1', function (RouteCollectorProxy $v1): void {
			$v1->get('/dashboard/values', DashboardValueController::class . ':list');
			$v1->get('/stocks/{id}', StockAssetController::class . ':detail');
		});

		$userGroup->add($this->requestValidationMiddleware);
		$userGroup->add($this->authentication->create(OAuthScope::API_READ, requireUser: true));

		$slimApp->get('/api/v1/me', MeController::class . ':me')
			->add($this->requestValidationMiddleware)
			->add($this->authentication->create(
				OAuthScope::PORTFOLIO_READ,
				requireUser: true,
				alternativeScopes: [OAuthScope::API_READ],
			));

		$portfolioGroup = $slimApp->group('/api/v1', function (RouteCollectorProxy $v1): void {
			$v1->get('/dashboard/summary', DashboardSummaryController::class . ':summary');
			$v1->get('/portfolio/dividends', StockDividendController::class . ':portfolio');
		});
		$portfolioGroup->add($this->requestValidationMiddleware);
		$portfolioGroup->add($this->authentication->create(OAuthScope::PORTFOLIO_READ, requireUser: true));

		$marketGroup = $slimApp->group('/api/v1', function (RouteCollectorProxy $v1): void {
			$v1->get('/exchange-rates', ExchangeRateController::class . ':list');
			$v1->get('/stock-dividends', StockDividendController::class . ':list');
		});
		$marketGroup->add($this->requestValidationMiddleware);
		$marketGroup->add($this->authentication->create(OAuthScope::MARKET_READ));

		$slimApp->post('/api/v1/home/device/record', HomeDeviceRecordController::class . ':create')
			->add($this->requestValidationMiddleware)
			->add($this->authentication->create(OAuthScope::HOME_WRITE, allowLegacy: true, requireUser: true));
		$slimApp->get('/api/v1/home/device/records', HomeDeviceRecordController::class . ':list')
			->add($this->requestValidationMiddleware)
			->add($this->authentication->create(OAuthScope::HOME_READ, allowLegacy: true, requireUser: true));

		$slimApp->post('/api/oauth/token', TokenController::class . ':token');
		$slimApp->post('/api/oauth/revoke', RevocationController::class . ':revoke');
		$slimApp->map(['GET', 'POST'], '/api/oauth/authorize', AuthorizationController::class . ':authorize');
	}

}
