<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ValidationException;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisV2ResponseValidatorTest extends TestCase
{

	#[DataProvider('provideSimpleWatchlistActions')]
	public function testSimpleWatchlistActionsPassFullAndPartialValidation(string $action): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['simpleWatchlistAnalysis'][0]['recommendation']['action'] = $action;
		$validator = $this->createValidator();

		$validated = $validator->validate(Json::encode($response), $snapshot);

		self::assertSame($action, $validated->simpleWatchlistAnalysis[0]->recommendation['action']);
		self::assertNull($snapshot['simpleWatchlist'][0]['currentPrice']);
		$schemaFactory = new StockAiAnalysisV2SchemaFactory();
		$partial = ['simpleWatchlistAnalysis' => $response['simpleWatchlistAnalysis']];
		foreach ([
			$schemaFactory->createCompanyResultSchema(),
			$schemaFactory->createCompanySchema('simpleWatchlistAnalysis'),
		] as $schema) {
			self::assertSame([], $validator->validateArrayAgainstSchema($partial, $schema));
			$geminiSchema = $schemaFactory->toGeminiResponseSchema($schema);
			self::assertContains(
				$action,
				$geminiSchema['properties']['simpleWatchlistAnalysis']['items']['properties']['recommendation']['properties']['action']['enum'],
			);
		}
	}

	/** @return array<string, array{string}> */
	public static function provideSimpleWatchlistActions(): array
	{
		return [
			'purchase' => ['consider_buying'],
			'track' => ['watch_closely'],
			'wait' => ['wait'],
			'not interesting' => ['not_interesting'],
		];
	}

	#[DataProvider('providePortfolioOnlyActions')]
	public function testSimpleWatchlistStillRejectsPortfolioOnlyActions(string $action): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['simpleWatchlistAnalysis'][0]['recommendation']['action'] = $action;

		$this->expectException(StockAiAnalysisV2ValidationException::class);
		$this->createValidator()->validate(Json::encode($response), $snapshot);
	}

	/** @return array<string, array{string}> */
	public static function providePortfolioOnlyActions(): array
	{
		return [
			'hold' => ['hold'],
			'add' => ['add_more'],
			'sell' => ['consider_selling'],
		];
	}

	public function testValidResponseMatchesSnapshot(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);

		$validated = $this->createValidator()->validate(Json::encode($response), $snapshot);

		self::assertSame(2, $validated->schemaVersion);
		self::assertSame($snapshot['runId'], $validated->runId);
		self::assertCount(1, $validated->portfolioAnalysis ?? []);
		self::assertCount(1, $validated->simpleWatchlistAnalysis ?? []);
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
				'includesSimpleWatchlist' => true,
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
			'simpleWatchlist' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'WATCH',
				'stockAssetTicker' => 'WATCH',
				'currency' => 'USD',
				'currentPrice' => null,
				'recommendedEntryPrice' => 80.0,
			]],
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

		$response = [
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
		$simpleWatchlistStock = $snapshot['simpleWatchlist'][0];
		$simpleWatchlistAnalysis = $response['portfolioAnalysis'][0];
		$simpleWatchlistAnalysis['stockAssetId'] = $simpleWatchlistStock['stockAssetId'];
		$simpleWatchlistAnalysis['stockAssetName'] = $simpleWatchlistStock['stockAssetName'];
		$simpleWatchlistAnalysis['stockAssetTicker'] = $simpleWatchlistStock['stockAssetTicker'];
		$simpleWatchlistAnalysis['recommendation']['action'] = 'watch_closely';
		$response['simpleWatchlistAnalysis'] = [$simpleWatchlistAnalysis];

		return $response;
	}

}
