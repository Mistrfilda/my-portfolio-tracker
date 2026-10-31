<?php

declare(strict_types = 1);

namespace App\Stock\Position\Api;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\MissingCurrencyPairException;
use App\OAuth2\Http\OAuthResponses;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StockPortfolioController
{

	public function __construct(
		private StockPortfolioProvider $portfolio,
		private CurrentAppAdminGetter $currentUser,
		private OAuthResponses $responses,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try {
			$data = $this->portfolio->getPortfolio($this->currentUser->getAppAdmin());
		} catch (NoResultException | MissingCurrencyPairException) {
			return $this->responses->error(
				$response,
				'data_unavailable',
				'Required exchange rate data is unavailable.',
				503,
			);
		}

		$response->getBody()->write(Json::encode($data));
		return $response->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
	}

}
