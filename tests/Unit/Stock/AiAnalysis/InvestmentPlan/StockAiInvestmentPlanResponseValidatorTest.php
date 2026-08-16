<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanResponseValidator;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanSchemaFactory;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanValidationException;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiInvestmentPlanResponseValidatorTest extends TestCase
{

	public function testValidDividendAllocationMatchesBudgetAndSnapshot(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);

		$validated = $this->createValidator()->validate(Json::encode($response), $snapshot);

		self::assertSame(1, $validated->schemaVersion);
		self::assertSame($snapshot['planId'], $validated->planId);
		self::assertCount(1, $validated->allocations);
		self::assertSame(40_000, $validated->decision['deployAmountCzk']);
	}

	public function testChangedPortfolioIdentityIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['allocations'][0]['stockAssetTicker'] = 'CHANGED';

		$this->assertValidationError($response, $snapshot, 'must match the immutable input snapshot');
	}

	public function testBudgetMismatchIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['decision']['cashReserveCzk'] = 5_000;

		$this->assertValidationError($response, $snapshot, 'must equal the immutable CZK budget');
	}

	public function testUnsafeDividendAllocationIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['allocations'][0]['dividendSafety']['status'] = 'unsafe';

		$this->assertValidationError($response, $snapshot, 'requires strong or acceptable dividend safety');
	}

	public function testHoldingEntireBudgetInCashIsValid(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['decision']['deployment'] = 'hold_cash';
		$response['decision']['deployAmountCzk'] = 0;
		$response['decision']['cashReserveCzk'] = 40_000;
		$response['decision']['stagingPlan'] = null;
		$response['allocations'] = [];

		$validated = $this->createValidator()->validate(Json::encode($response), $snapshot);

		self::assertSame('hold_cash', $validated->decision['deployment']);
		self::assertSame([], $validated->allocations);
	}

	public function testDuplicateTickerIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['allocations'][0]['allocationAmountCzk'] = 20_000;
		$response['allocations'][] = $response['allocations'][0];

		$this->assertValidationError($response, $snapshot, 'duplicates ticker DIV');
	}

	public function testPartialFairValueRangeIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['allocations'][0]['valuation']['method'] = null;

		$this->assertValidationError($response, $snapshot, 'must be all null or all present');
	}

	private function createValidator(): StockAiInvestmentPlanResponseValidator
	{
		return new StockAiInvestmentPlanResponseValidator(new StockAiInvestmentPlanSchemaFactory());
	}

	/** @return array<string, mixed> */
	private function createSnapshot(): array
	{
		return [
			'schemaVersion' => 1,
			'planId' => Uuid::uuid4()->toString(),
			'analysisAsOf' => '2026-08-16T16:30:00+02:00',
			'capital' => [
				'requestedAmountCzk' => 40_000.0,
				'currentPortfolioValueCzk' => 4_000_000.0,
			],
			'portfolio' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'Dividend Corp',
				'stockAssetTicker' => 'DIV',
				'currency' => 'USD',
				'portfolioPercentage' => 5.0,
			]],
			'watchlist' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'Watch Corp',
				'stockAssetTicker' => 'WATCH',
				'currency' => 'EUR',
			]],
		];
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	private function createResponse(array $snapshot): array
	{
		$stock = $snapshot['portfolio'][0];

		return [
			'schemaVersion' => 1,
			'planId' => $snapshot['planId'],
			'analysisAsOf' => $snapshot['analysisAsOf'],
			'decision' => [
				'deployment' => 'invest_all',
				'confidence' => 'high',
				'summary' => 'Celou částku lze investovat při zachování dividendové bezpečnosti.',
				'deployAmountCzk' => 40_000,
				'cashReserveCzk' => 0,
				'stagingPlan' => 'Nakoupit ve dvou stejně velkých tranších.',
			],
			'allocations' => [[
				'source' => 'portfolio',
				'stockAssetId' => $stock['stockAssetId'],
				'stockAssetName' => $stock['stockAssetName'],
				'stockAssetTicker' => $stock['stockAssetTicker'],
				'exchange' => 'NYSE',
				'currency' => $stock['currency'],
				'allocationAmountCzk' => 40_000,
				'estimatedWholeShares' => 10,
				'priceUsed' => 100.0,
				'maxBuyPrice' => 105.0,
				'dataQuality' => ['status' => 'sufficient', 'issues' => []],
				'investmentThesis' => 'Stabilní cash flow a přiměřená valuace.',
				'whyNow' => 'Cena nabízí rozumný dlouhodobý vstup.',
				'dividendSafety' => [
					'status' => 'strong',
					'dividendYieldPercent' => 4.0,
					'earningsPayoutRatioPercent' => 55.0,
					'cashFlowPayoutRatioPercent' => 50.0,
					'dividendHistory' => 'Deset let stabilních nebo rostoucích dividend.',
					'debtAssessment' => 'Dluh je přiměřený a dobře krytý.',
					'sectorAppropriateCoverage' => 'Dividenda je kryta volným cash flow.',
					'summary' => 'Dividenda je udržitelná.',
				],
				'valuation' => [
					'assessment' => 'undervalued',
					'fairValueLow' => 100.0,
					'fairValueBase' => 110.0,
					'fairValueHigh' => 120.0,
					'currency' => 'USD',
					'method' => 'DCF and dividend discount model',
					'summary' => 'Cena je pod konzervativní základní hodnotou.',
				],
				'keyRisks' => ['Cyklický pokles poptávky.'],
			]],
			'portfolioImpact' => [
				'summary' => 'Váha pozice mírně vzroste, ale zůstane přiměřená.',
				'diversificationAssessment' => 'neutral',
				'concentrationRisksAfter' => [],
			],
			'alternatives' => [],
			'keyRisks' => ['Měnové riziko USD/CZK.'],
		];
	}

	/**
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $snapshot
	 */
	private function assertValidationError(array $response, array $snapshot, string $expectedMessage): void
	{
		try {
			$this->createValidator()->validate(Json::encode($response), $snapshot);
			self::fail('The invalid investment plan response must be rejected.');
		} catch (StockAiInvestmentPlanValidationException $exception) {
			self::assertStringContainsString($expectedMessage, implode('\n', $exception->getErrors()));
		}
	}

}
