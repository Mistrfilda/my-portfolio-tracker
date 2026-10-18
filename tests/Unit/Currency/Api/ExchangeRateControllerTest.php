<?php

declare(strict_types = 1);

namespace App\Test\Unit\Currency\Api;

use App\Currency\Api\ExchangeRateController;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\OAuth2\Http\OAuthResponses;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class ExchangeRateControllerTest extends TestCase
{

	public function testReturnsDashboardPairsWithEachRatesOwnDateAndPrecision(): void
	{
		$rates = $this->createMock(CurrencyConversionRepository::class);
		$rates->expects(self::exactly(12))->method('getCurrentCurrencyPairConversion')->willReturnCallback(
			static fn (CurrencyEnum $from, CurrencyEnum $to): CurrencyConversion => new CurrencyConversion(
				$from,
				$to,
				1.23456789,
				CurrencySourceEnum::ECB,
				new ImmutableDateTime(
					$from === CurrencyEnum::NOK ? '2026-10-17 11:30:00+02:00' : '2026-10-16 10:00:00+02:00',
				),
				new ImmutableDateTime($from === CurrencyEnum::NOK ? '2026-10-17' : '2026-10-16'),
			),
		);
		$response = new ExchangeRateController($rates, new OAuthResponses())->list(
			new ServerRequestFactory()->createServerRequest('GET', '/api/v1/exchange-rates'),
			new Response(),
		);
		self::assertSame(200, $response->getStatusCode());
		$data = Json::decode((string) $response->getBody(), true);
		self::assertCount(12, $data['items']);
		$pairs = array_map(
			static fn (array $item): string => $item['fromCurrency'] . '/' . $item['toCurrency'],
			$data['items'],
		);
		self::assertSame([
			'EUR/CZK', 'USD/CZK', 'EUR/USD', 'GBP/CZK', 'GBP/EUR', 'EUR/GBP',
			'PLN/CZK', 'PLN/EUR', 'EUR/PLN', 'NOK/CZK', 'NOK/EUR', 'EUR/NOK',
		], $pairs);
		self::assertSame(1.23456789, $data['items'][0]['rate']);
		self::assertSame('2026-10-16', $data['items'][0]['forDate']);
		self::assertSame('2026-10-17', $data['items'][9]['forDate']);
		self::assertSame('2026-10-17T11:30:00+02:00', $data['items'][9]['updatedAt']);
		self::assertSame('ECB', $data['items'][9]['source']);
	}

	public function testMissingRateReturnsUnavailableInsteadOfZeroOrPartialList(): void
	{
		$rates = $this->createStub(CurrencyConversionRepository::class);
		$rates->method('getCurrentCurrencyPairConversion')->willThrowException(new NoResultException());
		$response = new ExchangeRateController($rates, new OAuthResponses())->list(
			new ServerRequestFactory()->createServerRequest('GET', '/api/v1/exchange-rates'),
			new Response(),
		);
		self::assertSame(503, $response->getStatusCode());
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		self::assertSame([
			'error' => 'data_unavailable',
			'error_description' => 'Required exchange rate data is unavailable.',
		], Json::decode((string) $response->getBody(), true));
	}

}
