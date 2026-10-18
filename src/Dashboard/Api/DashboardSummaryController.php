<?php

declare(strict_types = 1);

namespace App\Dashboard\Api;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\MissingCurrencyPairException;
use App\OAuth2\Http\OAuthResponses;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardSummaryController
{

	public function __construct(
		private DashboardSummaryProvider $summary,
		private CurrentAppAdminGetter $currentUser,
		private OAuthResponses $responses,
	)
	{
	}

	public function summary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try {
			$data = $this->summary->getSummary($this->currentUser->getAppAdmin());
		} catch (NoResultException | MissingCurrencyPairException) {
			return $this->responses->error(
				$response,
				'data_unavailable',
				'Required exchange rate data is unavailable.',
				503,
			);
		}

		$response->getBody()->write(Json::encode($data));
		return $response->withHeader('Content-Type', 'application/json');
	}

}
