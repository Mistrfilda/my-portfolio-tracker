<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Admin\AppAdmin;
use App\Stock\AiAnalysis\Codex\StockAiAnalysisCodexBundleFactory;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ValidationException;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Process\Process;
use ZipArchive;

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

	/** @param array<string, mixed> $changes */
	#[DataProvider('provideCompanyValidationCases')]
	public function testCompanyRulesApplyToFullAndPartialServerResponses(array $changes, bool $valid): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['portfolioAnalysis'][0] = array_replace_recursive($response['portfolioAnalysis'][0], $changes);
		$validator = $this->createValidator();
		$partialErrors = $validator->validatePartial(
			['portfolioAnalysis' => $response['portfolioAnalysis']],
			new StockAiAnalysisV2SchemaFactory()->createCompanySchema('portfolioAnalysis'),
			'portfolioAnalysis',
			$snapshot['portfolio'][0],
		);
		self::assertSame($valid, $partialErrors === [], implode('\n', $partialErrors));
		if (!$valid) {
			$this->expectException(StockAiAnalysisV2ValidationException::class);
		}

		$validator->validate(Json::encode($response), $snapshot);
	}

	/** @param array<string, mixed> $changes */
	#[DataProvider('provideCompanyValidationCases')]
	public function testBundledCodexValidatorMatchesCompanyBusinessRules(array $changes, bool $valid): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$response['portfolioAnalysis'][0] = array_replace_recursive($response['portfolioAnalysis'][0], $changes);

		$this->assertCodexValidation($snapshot, $response, 'result', $valid);
		$this->assertCodexValidation(
			$snapshot,
			['portfolioAnalysis' => $response['portfolioAnalysis']],
			'company-result',
			$valid,
		);
	}

	/** @return array<string, array{array<string, mixed>, bool}> */
	public static function provideCompanyValidationCases(): array
	{
		$nullValuation = [
			'assessment' => 'uncertain',
			'fairValueLow' => null,
			'fairValueBase' => null,
			'fairValueHigh' => null,
			'currency' => null,
			'method' => null,
		];
		$cases = [
			'valid range' => [[], true],
			'zero low' => [['valuation' => ['fairValueLow' => 0]], false],
			'negative range' => [['valuation' => ['fairValueLow' => -30, 'fairValueBase' => -20, 'fairValueHigh' => -10]], false],
			'low above base' => [['valuation' => ['fairValueLow' => 105]], false],
			'base above high' => [['valuation' => ['fairValueBase' => 120]], false],
			'equal bounds' => [['valuation' => ['fairValueLow' => 100, 'fairValueHigh' => 100]], true],
			'wrong currency' => [['valuation' => ['currency' => 'EUR']], false],
			'unsupported currency' => [['valuation' => ['currency' => 'CHF']], false],
			'insufficient with prices' => [['dataQuality' => ['status' => 'insufficient']], false],
			'insufficient without prices' => [['dataQuality' => ['status' => 'insufficient'], 'valuation' => $nullValuation], true],
			'limited without prices' => [['dataQuality' => ['status' => 'limited'], 'valuation' => $nullValuation], true],
			'null without uncertainty' => [['valuation' => [...$nullValuation, 'assessment' => 'fairly_valued']], false],
			'uncertain with supported prices' => [['valuation' => ['assessment' => 'uncertain']], true],
			'limited with confident hold' => [['dataQuality' => ['status' => 'limited'], 'recommendation' => ['confidence' => 'high']], true],
			'changed ID' => [['stockAssetId' => '018f6d0e-7c2a-7f45-8d82-b9e4653cb960'], false],
			'changed name' => [['stockAssetName' => 'Renamed Corp'], false],
			'changed ticker' => [['stockAssetTicker' => 'OTHER'], false],
		];
		foreach (['fairValueLow', 'fairValueBase', 'fairValueHigh', 'currency', 'method'] as $field) {
			$cases['partial null ' . $field] = [['valuation' => [$field => null]], false];
		}

		return $cases;
	}

	#[DataProvider('provideRunValidationCases')]
	public function testCodexAndServerRejectInvalidMembershipAndMetadata(string $case): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		if ($case === 'duplicate') {
			$snapshot['portfolio'][] = [...$snapshot['portfolio'][0], 'stockAssetId' => Uuid::uuid4()->toString()];
			$response['portfolioAnalysis'][] = $response['portfolioAnalysis'][0];
		} elseif ($case === 'missing') {
			$response['portfolioAnalysis'] = [];
		} elseif ($case === 'extra') {
			$response['portfolioAnalysis'][] = $response['portfolioAnalysis'][0];
		} elseif ($case === 'wrong list') {
			foreach (['stockAssetId', 'stockAssetName', 'stockAssetTicker'] as $key) {
				$response['portfolioAnalysis'][0][$key] = $snapshot['simpleWatchlist'][0][$key];
				$response['simpleWatchlistAnalysis'][0][$key] = $snapshot['portfolio'][0][$key];
			}
		} else {
			$response[$case] = match ($case) {
				'schemaVersion' => 1,
				'runId' => Uuid::uuid4()->toString(),
				default => '2026-07-22T10:00:00+02:00',
			};
		}

		$this->assertCodexValidation($snapshot, $response, 'result', false);
		$this->expectException(StockAiAnalysisV2ValidationException::class);
		$this->createValidator()->validate(Json::encode($response), $snapshot);
	}

	/** @return array<string, array{string}> */
	public static function provideRunValidationCases(): array
	{
		return [
			'duplicate' => ['duplicate'],
			'missing' => ['missing'],
			'extra' => ['extra'],
			'wrong list' => ['wrong list'],
			'schemaVersion' => ['schemaVersion'],
			'runId' => ['runId'],
			'analysisAsOf' => ['analysisAsOf'],
		];
	}

	public function testCodexChecksScopeEvenForStructurallyValidPartials(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$company = $response['simpleWatchlistAnalysis'][0];
		$company['recommendation']['action'] = 'wait';
		$partial = ['watchlistAnalysis' => [$company]];
		self::assertSame([], $this->createValidator()->validateArrayAgainstSchema(
			$partial,
			new StockAiAnalysisV2SchemaFactory()->createCompanyResultSchema(),
		));

		$this->assertCodexValidation($snapshot, $partial, 'company-result', false);
	}

	public function testCodexChecksAllCompanySectionsIncludingUnknownSingleStock(): void
	{
		$snapshot = $this->createSnapshot();
		$response = $this->createResponse($snapshot);
		$snapshot['scope']['includesWatchlist'] = true;
		$snapshot['scope']['includesStockAnalysis'] = true;
		$snapshot['watchlist'] = [[...$snapshot['simpleWatchlist'][0], 'stockAssetId' => Uuid::uuid4()->toString()]];
			$response['watchlistAnalysis'] = [array_replace_recursive($response['simpleWatchlistAnalysis'][0], [
				'stockAssetId' => $snapshot['watchlist'][0]['stockAssetId'],
				'recommendation' => ['action' => 'wait'],
			])];
			$snapshot['singleStock'] = [...$snapshot['portfolio'][0], 'stockAssetId' => null, 'currency' => null];
			$stock = $response['portfolioAnalysis'][0];
			unset($stock['performanceComment']);
			$response['stockAnalysis'] = array_replace($stock, [
				'stockAssetId' => null,
				'businessSummary' => 'Stabilní byznys.',
				'moatAnalysis' => 'Silná značka.',
				'financialHealth' => 'Přiměřené zadlužení.',
				'conclusion' => 'Sledovat ocenění.',
			]);
		$response['stockAnalysis']['valuation']['currency'] = 'EUR';
		self::assertNull(
			$this->createValidator()->validate(Json::encode($response), $snapshot)->stockAnalysis?->stockAssetId,
		);
		$this->assertCodexValidation($snapshot, $response, 'result', true);
		foreach (['portfolioAnalysis', 'watchlistAnalysis', 'simpleWatchlistAnalysis', 'stockAnalysis'] as $rootKey) {
			$this->assertCodexValidation($snapshot, [$rootKey => $response[$rootKey]], 'company-result', true);
		}

		$response['stockAnalysis']['stockAssetTicker'] = 'RENAMED';
		$this->assertCodexValidation(
			$snapshot,
			['stockAnalysis' => $response['stockAnalysis']],
			'company-result',
			false,
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @param array<string, mixed> $data
	 */
	private function assertCodexValidation(array $snapshot, array $data, string $schemaName, bool $valid): void
	{
		$tempDir = sys_get_temp_dir() . '/stock-ai-validator-' . bin2hex(random_bytes(8));
		$schemaFactory = new StockAiAnalysisV2SchemaFactory();
		$factory = new StockAiAnalysisCodexBundleFactory(
			new StockAiAnalysisV2PromptGenerator($schemaFactory),
			$schemaFactory,
			$tempDir,
		);
		$run = new StockAiAnalysisRun(
			$this->createStub(AppAdmin::class),
			'Prompt',
			($snapshot['scope']['includesPortfolio'] ?? false) === true,
			($snapshot['scope']['includesWatchlist'] ?? false) === true,
			false,
			null,
			new ImmutableDateTime('2026-07-21 10:00:00'),
			analysisSchemaVersion: 2,
			inputSnapshot: $snapshot,
			id: Uuid::fromString(TypeValidator::validateString($snapshot['runId'])),
		);

		try {
			$bundle = $factory->create($run);
			$zip = new ZipArchive();
			self::assertTrue($zip->open($bundle->filePath));
			$directory = $tempDir . '/extracted';
			self::assertTrue($zip->extractTo($directory));
			$zip->close();
			FileSystem::write($directory . '/response.json', Json::encode($data));
			$process = new Process([
				'node',
				$directory . '/validate-stock-json.mjs',
				$directory . '/schema/' . $schemaName . '.schema.json',
				$directory . '/response.json',
			], sys_get_temp_dir());
			self::assertSame($valid ? 0 : 1, $process->run(), $process->getOutput() . $process->getErrorOutput());
		} finally {
			FileSystem::delete($tempDir);
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
