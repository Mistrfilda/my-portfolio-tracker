<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ValidationException;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisV2ResponseValidatorTest extends TestCase
{

	public function testValidResponseMatchesSnapshot(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);

		$validated = $this->createValidator()->validate(Json::encode($response), $snapshot);

		self::assertSame(2, $validated->schemaVersion);
		self::assertSame($snapshot['runId'], $validated->runId);
		self::assertCount(1, $validated->portfolioAnalysis ?? []);
	}

	public function testResponseWithChangedStockIdIsRejected(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['portfolioAnalysis'][0]['stockAssetId'] = Uuid::uuid4()->toString();

		try {
			$this->createValidator()->validate(Json::encode($response), $snapshot);
			self::fail('A response with a changed stock ID must be rejected.');
		} catch (StockAiAnalysisV2ValidationException $exception) {
			self::assertStringContainsString('unexpected stockAssetId', implode('\n', $exception->getErrors()));
		}
	}

	private function createValidator(): StockAiAnalysisV2ResponseValidator
	{
		return new StockAiAnalysisV2ResponseValidator(new StockAiAnalysisV2SchemaFactory());
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSnapshot(): array
	{
		return [
			'schemaVersion' => 2,
			'runId' => Uuid::uuid4()->toString(),
			'analysisAsOf' => '2026-07-21T10:00:00+02:00',
			'scope' => [
				'includesPortfolio' => true,
				'includesWatchlist' => false,
				'includesMarketOverview' => false,
				'includesStockAnalysis' => false,
				'portfolioPromptType' => null,
			],
			'portfolio' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'Example Corp',
				'stockAssetTicker' => 'EXM',
				'currency' => 'USD',
				'currentPrice' => 90.0,
			]],
			'watchlist' => [],
			'portfolioContext' => [],
			'singleStock' => null,
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
			'schemaVersion' => 2,
			'runId' => $snapshot['runId'],
			'analysisAsOf' => $snapshot['analysisAsOf'],
			'portfolioAnalysis' => [[
				'stockAssetId' => $stock['stockAssetId'],
				'stockAssetName' => $stock['stockAssetName'],
				'stockAssetTicker' => $stock['stockAssetTicker'],
				'summary' => 'Firma zůstává fundamentálně stabilní.',
				'dataQuality' => ['status' => 'sufficient', 'issues' => []],
				'materialEvents' => [],
				'earnings' => [
					'latestPeriod' => 'Q2 2026',
					'resultVsExpectations' => 'met',
					'nextEarningsDate' => null,
					'summary' => 'Výsledky odpovídaly očekávání.',
				],
				'dividend' => ['status' => 'stable', 'summary' => 'Dividenda je stabilní.'],
				'catalysts' => [],
				'risks' => [],
				'valuation' => [
					'assessment' => 'fairly_valued',
					'fairValueLow' => 90.0,
					'fairValueBase' => 100.0,
					'fairValueHigh' => 110.0,
					'currency' => 'USD',
					'method' => 'DCF and peer multiples',
					'summary' => 'Ocenění je přibližně férové.',
				],
				'recommendation' => [
					'action' => 'hold',
					'confidence' => 'medium',
					'reasoning' => 'Poměr rizika a výnosu je vyvážený.',
					'watchConditions' => [],
				],
				'performanceComment' => 'Cena se za sledované období významně nezměnila.',
			]],
			'portfolioEvaluation' => [
				'summary' => 'Portfolio je stabilní.',
				'performance7DaysSummary' => 'Výkonnost byla za sedm dní přibližně neutrální.',
				'concentrationRisks' => [],
				'actions' => [],
			],
		];
	}

}
