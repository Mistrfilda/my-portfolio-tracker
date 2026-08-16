<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanCalculator;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiInvestmentPlanCalculatorTest extends TestCase
{

	public function testCalculatesRequestedAmountAgainstCurrentAndProjectedPortfolio(): void
	{
		$calculator = new StockAiInvestmentPlanCalculator();

		self::assertSame(1.0, $calculator->calculateCurrentPortfolioPercent(40_000, 4_000_000));
		self::assertSame(0.9901, $calculator->calculateProjectedPortfolioPercent(40_000, 4_000_000));
	}

	public function testEnrichesAllocationWithDeterministicPortfolioWeights(): void
	{
		$stockAssetId = Uuid::uuid4()->toString();
		$calculator = new StockAiInvestmentPlanCalculator();
		$response = $calculator->enrichResponse([
			'decision' => ['deployAmountCzk' => 40_000],
			'allocations' => [[
				'source' => 'portfolio',
				'stockAssetId' => $stockAssetId,
				'allocationAmountCzk' => 40_000,
			]],
		], [
			'capital' => [
				'requestedAmountCzk' => 40_000,
				'currentPortfolioValueCzk' => 4_000_000,
			],
			'portfolio' => [[
				'stockAssetId' => $stockAssetId,
				'portfolioPercentage' => 5.0,
			]],
		]);

		self::assertSame(100.0, $response['calculated']['deployedPercentOfBudget']);
		self::assertSame(4_040_000.0, $response['calculated']['projectedStockPortfolioValueCzk']);
		self::assertSame(5.0, $response['allocations'][0]['calculated']['beforePortfolioPercent']);
		self::assertSame(5.94, $response['allocations'][0]['calculated']['projectedPortfolioPercent']);
	}

}
