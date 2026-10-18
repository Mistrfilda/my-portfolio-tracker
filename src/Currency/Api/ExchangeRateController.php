<?php

declare(strict_types = 1);

namespace App\Currency\Api;

use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\OAuth2\Http\OAuthResponses;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use const DATE_ATOM;

class ExchangeRateController
{

	private const array PAIRS = [
		[CurrencyEnum::EUR, CurrencyEnum::CZK],
		[CurrencyEnum::USD, CurrencyEnum::CZK],
		[CurrencyEnum::EUR, CurrencyEnum::USD],
		[CurrencyEnum::GBP, CurrencyEnum::CZK],
		[CurrencyEnum::GBP, CurrencyEnum::EUR],
		[CurrencyEnum::EUR, CurrencyEnum::GBP],
		[CurrencyEnum::PLN, CurrencyEnum::CZK],
		[CurrencyEnum::PLN, CurrencyEnum::EUR],
		[CurrencyEnum::EUR, CurrencyEnum::PLN],
		[CurrencyEnum::NOK, CurrencyEnum::CZK],
		[CurrencyEnum::NOK, CurrencyEnum::EUR],
		[CurrencyEnum::EUR, CurrencyEnum::NOK],
	];

	public function __construct(
		private CurrencyConversionRepository $rates,
		private OAuthResponses $responses,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$items = [];
		try {
			foreach (self::PAIRS as [$from, $to]) {
				$rate = $this->rates->getCurrentCurrencyPairConversion($from, $to);
				$items[] = [
					'fromCurrency' => $rate->getFromCurrency()->value,
					'toCurrency' => $rate->getToCurrency()->value,
					'rate' => $rate->getCurrentPrice(),
					'forDate' => $rate->getForDate()->format('Y-m-d'),
					'updatedAt' => $rate->getUpdatedAt()->format(DATE_ATOM),
					'source' => $rate->getSource()->value,
				];
			}
		} catch (NoResultException) {
			return $this->responses->error(
				$response,
				'data_unavailable',
				'Required exchange rate data is unavailable.',
				503,
			);
		}

		$response->getBody()->write(Json::encode(['items' => $items]));
		return $response->withHeader('Content-Type', 'application/json');
	}

}
