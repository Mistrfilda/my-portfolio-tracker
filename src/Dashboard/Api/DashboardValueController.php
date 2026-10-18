<?php

declare(strict_types = 1);

namespace App\Dashboard\Api;

use App\Admin\LegacyPortfolioOwnerProvider;
use App\Dashboard\DashboardValueBuilderFacade;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardValueController
{

	public function __construct(
		private readonly DashboardValueBuilderFacade $dashboardValueBuilderFacade,
		private readonly LegacyPortfolioOwnerProvider $legacyOwnerProvider,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		// Preserve the legacy API portfolio until the separate API isolation step.
		$data = Json::encode($this->dashboardValueBuilderFacade->buildValues(
			appAdmin: $this->legacyOwnerProvider->getOwner(),
		));

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
