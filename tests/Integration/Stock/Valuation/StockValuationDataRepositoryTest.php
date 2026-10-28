<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock\Valuation;

use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Test\Integration\IntegrationTestCase;
use App\Test\Unit\Stock\Valuation\ValuationFixture;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockValuationDataRepositoryTest extends IntegrationTestCase
{

	public function testPreviousSnapshotIncludesNullsAndIgnoresNewerAnalystImport(): void
	{
		$em = $this->getService(EntityManagerInterface::class);
		$asset = ValuationFixture::create([])->getStockAsset();
		$em->persist($asset);
		foreach ([
			['2026-09-01', ['diluted_eps' => 3.0, 'total_cash' => 10.0]],
			['2026-10-01', ['diluted_eps' => 4.0, 'total_cash' => null]],
			['2026-10-24', ['analyst_price_target_average' => 100.0]],
			['2026-10-25', ['diluted_eps' => 4.0, 'total_cash' => 20.0]],
		] as [$at, $values]) {
			foreach (ValuationFixture::data($asset, $values, new ImmutableDateTime($at)) as $row) {
				$row->setLastActive($at === '2026-10-25');
				$em->persist($row);
			}
		}

		$em->flush();
		$repository = $this->getService(StockValuationDataRepository::class);
		$previous = $repository->findPreviousForStockAsset($asset, new ImmutableDateTime('2026-10-25'));
		self::assertCount(2, $previous);
		self::assertSame(4.0, $previous['diluted_eps']->getFloatValue());
		self::assertNull($previous['total_cash']->getFloatValue());
		self::assertSame('2026-10-01', $previous['total_cash']->getParsedAt()->format('Y-m-d'));
		self::assertSame([], $repository->findPreviousForStockAsset($asset, new ImmutableDateTime('2026-09-01')));
	}

}
