<?php

declare(strict_types = 1);

namespace App\Test\Integration\Dashboard\Api;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Test\Integration\Api\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Slim\Psr7\Factory\ServerRequestFactory;

class DashboardValueListTest extends ApiTestCase
{

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->entityManager = $this->getService(EntityManagerInterface::class);
		$this->createRequiredCurrencyConversions();
	}

	public function testList(): void
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/dashboard/values')
			->withHeader('X-Api-Key', 'test-api-key');
		$response = $this->app->handle($request);

		$this->assertSame(200, $response->getStatusCode());

		$body = (string) $response->getBody();
		$this->assertJson($body);

		$data = Json::decode($body, Json::FORCE_ARRAY);
		$this->assertIsArray($data);
		$this->assertNotSame([], $data);
		$this->assertArrayHasKey('name', $data[0]);
		$this->assertArrayHasKey('heading', $data[0]);
		$this->assertArrayHasKey('positions', $data[0]);
		$this->assertArrayHasKey('tables', $data[0]);
	}

	private function createRequiredCurrencyConversions(): void
	{
		$now = new ImmutableDateTime('2026-01-01 12:00:00');
		$forDate = new ImmutableDateTime('2026-01-01');

		foreach ([
			[CurrencyEnum::EUR, CurrencyEnum::CZK, 25.1],
			[CurrencyEnum::USD, CurrencyEnum::CZK, 22.9],
			[CurrencyEnum::EUR, CurrencyEnum::USD, 1.09],
			[CurrencyEnum::GBP, CurrencyEnum::CZK, 29.5],
			[CurrencyEnum::GBP, CurrencyEnum::EUR, 1.17],
			[CurrencyEnum::EUR, CurrencyEnum::GBP, 0.85],
			[CurrencyEnum::PLN, CurrencyEnum::CZK, 5.8],
			[CurrencyEnum::PLN, CurrencyEnum::EUR, 0.23],
			[CurrencyEnum::EUR, CurrencyEnum::PLN, 4.35],
			[CurrencyEnum::NOK, CurrencyEnum::CZK, 2.12],
			[CurrencyEnum::NOK, CurrencyEnum::EUR, 0.084],
			[CurrencyEnum::EUR, CurrencyEnum::NOK, 11.9],
		] as [$fromCurrency, $toCurrency, $price]) {
			$this->entityManager->persist(new CurrencyConversion(
				$fromCurrency,
				$toCurrency,
				$price,
				CurrencySourceEnum::ECB,
				$now,
				$forDate,
			));
		}

		$this->entityManager->flush();
	}

}
