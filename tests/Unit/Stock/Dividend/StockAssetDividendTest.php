<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Ramsey\Uuid\Uuid;

class StockAssetDividendTest extends UpdatedTestCase
{

	public function testConstructor(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getId')->andReturn(Uuid::uuid4());

		$exDate = new ImmutableDateTime('2024-01-15');
		$paymentDate = new ImmutableDateTime('2024-02-01');
		$declarationDate = new ImmutableDateTime('2024-01-01');
		$now = new ImmutableDateTime('2024-01-10');

		$dividend = new StockAssetDividend(
			$stockAsset,
			$exDate,
			$paymentDate,
			$declarationDate,
			CurrencyEnum::USD,
			2.50,
			$now,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$this->assertSame($stockAsset, $dividend->getStockAsset());
		$this->assertSame($exDate, $dividend->getExDate());
		$this->assertSame($paymentDate, $dividend->getPaymentDate());
		$this->assertSame($declarationDate, $dividend->getDeclarationDate());
		$this->assertSame(CurrencyEnum::USD, $dividend->getCurrency());
		$this->assertSame(2.50, $dividend->getAmount());
		$this->assertSame(StockAssetDividendTypeEnum::REGULAR, $dividend->getDividendType());
		$this->assertSame($now, $dividend->getCreatedAt());
	}

	public function testConstructorWithNullDeclarationDate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::EUR,
			1.25,
			$now,
		);

		$this->assertNull($dividend->getDeclarationDate());
	}

	public function testUpdate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime('2024-01-10');

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			new ImmutableDateTime('2024-01-01'),
			CurrencyEnum::USD,
			2.50,
			$now,
		);

		$newExDate = new ImmutableDateTime('2024-03-15');
		$newPaymentDate = new ImmutableDateTime('2024-04-01');
		$newDeclarationDate = new ImmutableDateTime('2024-03-01');
		$updateTime = new ImmutableDateTime('2024-02-15');

		$dividend->update(
			$newExDate,
			$newPaymentDate,
			$newDeclarationDate,
			CurrencyEnum::EUR,
			5.00,
			StockAssetDividendTypeEnum::SPECIAL,
			$updateTime,
		);

		$this->assertSame($newExDate, $dividend->getExDate());
		$this->assertSame($newPaymentDate, $dividend->getPaymentDate());
		$this->assertSame($newDeclarationDate, $dividend->getDeclarationDate());
		$this->assertSame(CurrencyEnum::EUR, $dividend->getCurrency());
		$this->assertSame(5.00, $dividend->getAmount());
		$this->assertSame(StockAssetDividendTypeEnum::SPECIAL, $dividend->getDividendType());
		$this->assertSame($updateTime, $dividend->getUpdatedAt());
	}

	public function testIsPaidWithPaymentDateInFuture(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime('2024-01-10');

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			2.50,
			$now,
		);

		$checkDate = new ImmutableDateTime('2024-01-20');
		$this->assertTrue($dividend->isPaid($checkDate));
	}

	public function testIsPaidWithPaymentDateInPast(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime('2024-01-10');

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			2.50,
			$now,
		);

		$checkDate = new ImmutableDateTime('2024-03-01');
		$this->assertFalse($dividend->isPaid($checkDate));
	}

	public function testIsPaidWithNullPaymentDateUsesExDate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime('2024-01-10');

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			null,
			null,
			CurrencyEnum::USD,
			2.50,
			$now,
		);

		$checkDateBefore = new ImmutableDateTime('2024-01-10');
		$this->assertTrue($dividend->isPaid($checkDateBefore));

		$checkDateAfter = new ImmutableDateTime('2024-01-20');
		$this->assertFalse($dividend->isPaid($checkDateAfter));
	}

	public function testGetSummaryPriceWithTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			100.0,
			$now,
		);

		$summaryPrice = $dividend->getSummaryPrice(true);

		$this->assertSame(CurrencyEnum::USD, $summaryPrice->getCurrency());
		$this->assertSame(85.0, $summaryPrice->getPrice());
	}

	public function testGetSummaryPriceWithoutTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			100.0,
			$now,
		);

		$summaryPrice = $dividend->getSummaryPrice(false);

		$this->assertSame(100.0, $summaryPrice->getPrice());
	}

	public function testGetSummaryPriceWithNullDividendTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(null);

		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			100.0,
			$now,
		);

		$summaryPrice = $dividend->getSummaryPrice(true);

		$this->assertSame(100.0, $summaryPrice->getPrice());
	}

	public function testGetRecordsReturnsEmptyArrayByDefault(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			100.0,
			$now,
		);

		$this->assertSame([], $dividend->getRecords());
	}

	public function testGetStockAssetId(): void
	{
		$stockAssetId = Uuid::uuid4();
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getId')->andReturn($stockAssetId);

		$now = new ImmutableDateTime();

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2024-01-15'),
			new ImmutableDateTime('2024-02-01'),
			null,
			CurrencyEnum::USD,
			100.0,
			$now,
		);

		$this->assertSame($stockAssetId, $dividend->getStockAssetId());
	}

}
