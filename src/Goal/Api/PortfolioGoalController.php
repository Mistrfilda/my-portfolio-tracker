<?php

declare(strict_types = 1);

namespace App\Goal\Api;

use App\Admin\CurrentAppAdminGetter;
use App\Goal\PortfolioGoalRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PortfolioGoalController
{

	public function __construct(
		private PortfolioGoalRepository $goals,
		private PortfolioGoalSerializer $serializer,
		private CurrentAppAdminGetter $currentUser,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$items = $this->goals->findActive($this->datetimeFactory->createNow(), $this->currentUser->getAppAdmin());
		$response->getBody()->write(Json::encode([
			'items' => array_map($this->serializer->serialize(...), $items),
		]));
		return $response->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
	}

}
