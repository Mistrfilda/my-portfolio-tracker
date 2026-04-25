<?php

declare(strict_types = 1);

namespace App\Dashboard\Api;

use App\Dashboard\DashboardValueBuilderFacade;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardValueController
{

	public function __construct(
		private readonly DashboardValueBuilderFacade $dashboardValueBuilderFacade,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = Json::encode($this->dashboardValueBuilderFacade->buildValues());

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
