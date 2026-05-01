<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation;

use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use PHPUnit\Framework\TestCase;

class StockValuationTypeEnumTest extends TestCase
{

	public function testTotalDebtEquityIsPercentage(): void
	{
		$this->assertSame(
			StockValuationTypeValueTypeEnum::PERCENTAGE,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY->getTypeValueType(),
		);
	}

	public function testCurrencyValueClassification(): void
	{
		$this->assertTrue(StockValuationTypeEnum::MARKET_CAP->isCurrencyValue());
		$this->assertTrue(StockValuationTypeEnum::REVENUE_PER_SHARE->isCurrencyValue());
		$this->assertTrue(StockValuationTypeEnum::TOTAL_CASH->isCurrencyValue());
		$this->assertTrue(StockValuationTypeEnum::WEEK_52_HIGH->isCurrencyValue());
		$this->assertTrue(StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE->isCurrencyValue());

		$this->assertFalse(StockValuationTypeEnum::BETA->isCurrencyValue());
		$this->assertFalse(StockValuationTypeEnum::TOTAL_DEBT_EQUITY->isCurrencyValue());
		$this->assertFalse(StockValuationTypeEnum::AVG_VOLUME_3M->isCurrencyValue());
	}

}
