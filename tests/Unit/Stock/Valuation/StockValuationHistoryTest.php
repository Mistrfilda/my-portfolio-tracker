<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation;

use App\Stock\Valuation\StockValuation;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockValuationHistoryTest extends TestCase
{

	public function testComparesWholeSnapshotsIncludingMissingValuesAndZero(): void
	{
		$current = ValuationFixture::create(
			['diluted_eps' => 4.75, 'total_cash' => null, 'total_debt' => 0.0, 'payout_ratio' => 89.26],
		);
		$asset = $current->getStockAsset();
		$previousAt = new ImmutableDateTime('2026-09-25 12:00:00');
		$previous = ValuationFixture::data(
			$asset,
			['diluted_eps' => 4.75, 'total_cash' => 20.0, 'total_debt' => null, 'payout_ratio' => 80.0],
			$previousAt,
		);
		$valuation = new StockValuation($asset, $current->getCurrentStockValuationData(), $previous);
		$changes = [];
		foreach ($valuation->getInputChanges() as $change) {
			$changes[$change->type->value] = $change;
		}

		self::assertCount(3, $changes);
		self::assertArrayNotHasKey('diluted_eps', $changes);
		self::assertSame('Nyní chybí', $changes['total_cash']->getLabel());
		self::assertSame('Nově dostupné', $changes['total_debt']->getLabel());
		self::assertSame(0.0, $changes['total_debt']->current?->getFloatValue());
		self::assertEquals($previousAt, $valuation->getPreviousImportedAt());
	}

	public function testFirstSnapshotDoesNotPretendAllValuesChanged(): void
	{
		$valuation = ValuationFixture::create(['diluted_eps' => 4.75]);
		self::assertNull($valuation->getPreviousImportedAt());
		self::assertSame([], $valuation->getInputChanges());
	}

}
