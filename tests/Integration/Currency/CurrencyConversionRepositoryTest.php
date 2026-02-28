<?php

declare(strict_types = 1);

namespace App\Test\Integration\Currency;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class CurrencyConversionRepositoryTest extends IntegrationTestCase
{

	private CurrencyConversionRepository $currencyConversionRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->currencyConversionRepository = $this->getService(CurrencyConversionRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testFindCurrencyPairConversionForDate(): void
	{
		$now = new ImmutableDateTime();
		$forDate = new ImmutableDateTime('2025-01-15');

		$conversion = new CurrencyConversion(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			23.5,
			CurrencySourceEnum::CNB,
			$now,
			$forDate,
		);

		$this->entityManager->persist($conversion);
		$this->entityManager->flush();

		$result = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$forDate,
		);

		$this->assertNotNull($result);
		$this->assertSame(CurrencyEnum::USD, $result->getFromCurrency());
		$this->assertSame(CurrencyEnum::CZK, $result->getToCurrency());
		$this->assertSame(23.5, $result->getCurrentPrice());
		$this->assertSame(CurrencySourceEnum::CNB, $result->getSource());
	}

	public function testFindCurrencyPairConversionForDateReturnsNullWhenNotFound(): void
	{
		$forDate = new ImmutableDateTime('2099-12-31');

		$result = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
			$forDate,
		);

		$this->assertNull($result);
	}

	public function testGetCurrentCurrencyPairConversion(): void
	{
		$now = new ImmutableDateTime();

		$olderConversion = new CurrencyConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
			25.0,
			CurrencySourceEnum::ECB,
			$now,
			new ImmutableDateTime('2025-01-10'),
		);

		$newerConversion = new CurrencyConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
			25.5,
			CurrencySourceEnum::ECB,
			$now,
			new ImmutableDateTime('2025-01-20'),
		);

		$this->entityManager->persist($olderConversion);
		$this->entityManager->persist($newerConversion);
		$this->entityManager->flush();

		$result = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
		);

		$this->assertSame(25.5, $result->getCurrentPrice());
		$this->assertSame('2025-01-20', $result->getForDate()->format('Y-m-d'));
	}

	public function testFindCurrencyPairConversionForClosestDate(): void
	{
		$now = new ImmutableDateTime();

		$before = new CurrencyConversion(
			CurrencyEnum::GBP,
			CurrencyEnum::CZK,
			30.0,
			CurrencySourceEnum::ECB,
			$now,
			new ImmutableDateTime('2025-03-01'),
		);

		$after = new CurrencyConversion(
			CurrencyEnum::GBP,
			CurrencyEnum::CZK,
			31.0,
			CurrencySourceEnum::ECB,
			$now,
			new ImmutableDateTime('2025-03-10'),
		);

		$this->entityManager->persist($before);
		$this->entityManager->persist($after);
		$this->entityManager->flush();

		$result = $this->currencyConversionRepository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::GBP,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2025-03-04'),
		);

		$this->assertSame(30.0, $result->getCurrentPrice());
		$this->assertSame('2025-03-01', $result->getForDate()->format('Y-m-d'));
	}

	public function testFindCurrencyPairConversionForClosestDateThrowsWhenNoData(): void
	{
		$this->expectException(NoResultException::class);

		$this->currencyConversionRepository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::NOK,
			CurrencyEnum::PLN,
			new ImmutableDateTime('2099-06-15'),
		);
	}

}
