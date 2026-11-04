<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisV2PromptGeneratorTest extends TestCase
{

	public function testEveryProviderRespectsApplicationApplicabilityAndExplainsValuationDifferences(): void
	{
		foreach ($this->createProviderPrompts($this->createSnapshot('portfolio_evaluation')) as $provider => $prompt) {
			self::assertStringContainsString('already evaluated model applicability', $prompt, $provider);
			self::assertStringContainsString('availability and availabilityReason', $prompt, $provider);
			self::assertStringContainsString('not_applicable, missing_data, or invalid_data', $prompt, $provider);
			self::assertStringContainsString('or re-enable a model marked not_applicable', $prompt, $provider);
			self::assertStringContainsString('you may build a separate independent estimate', $prompt, $provider);
			self::assertStringContainsString('keep the original model status unchanged', $prompt, $provider);
			self::assertStringContainsString(
				'diagnostic identifies indicators without a fair price',
				$prompt,
				$provider,
			);
			self::assertStringContainsString('model agreement and coverage', $prompt, $provider);
			self::assertStringContainsString('scenarios describe sensitivity', $prompt, $provider);
			self::assertStringContainsString('material differences between your fair value', $prompt, $provider);
			self::assertStringContainsString('not financial reporting dates', $prompt, $provider);
			self::assertStringContainsString('not independently verified reporting currencies', $prompt, $provider);
		}
	}

	#[DataProvider('provideResearchModes')]
	public function testEveryProviderCombinesResearchWithImmutableApplicationEvidence(string $mode): void
	{
		foreach ($this->createProviderPrompts($this->createSnapshot($mode)) as $provider => $prompt) {
			self::assertStringContainsString('Web research remains required', $prompt, $provider);
			self::assertStringContainsString(
				'Do not re-collect every supplied metric mechanically',
				$prompt,
				$provider,
			);
			self::assertStringContainsString('All researched evidence must respect analysisAsOf', $prompt, $provider);
			self::assertStringContainsString('first reconcile the reporting period', $prompt, $provider);
			self::assertStringContainsString(
				'prefer verified primary disclosures over imported aggregates',
				$prompt,
				$provider,
			);
			self::assertStringContainsString(
				'Preserve the application snapshot and its model results',
				$prompt,
				$provider,
			);
			self::assertStringContainsString('state both prices', $prompt, $provider);
			self::assertStringContainsString('margin of safety still uses the snapshot price', $prompt, $provider);
		}
	}

	/** @return array<string, array{string}> */
	public static function provideResearchModes(): array
	{
		return ['comprehensive' => ['portfolio_evaluation'], 'daily' => ['daily_brief']];
	}

	public function testManualAndGeminiPromptsPreserveCompanyValuationInput(): void
	{
		$snapshot = $this->createSnapshot('portfolio_evaluation');
		$company = [
			'stockAssetTicker' => 'TEST',
			'fundamentalMetadata' => ['dilutedEps' => ['importedAt' => '2026-09-01T12:00:00+02:00']],
			'internalValuation' => ['models' => [['availabilityReason' => 'Excluded by the supplied industry rule']]],
		];
		$snapshot['portfolio'] = [$company];
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		foreach ([
			$generator->generateManualPrompt($snapshot),
			$generator->generateTaskPrompt($snapshot),
			$generator->generateCompanyPrompt($snapshot, 'portfolioAnalysis', $company),
		] as $prompt) {
			self::assertStringContainsString('Excluded by the supplied industry rule', $prompt);
			self::assertStringContainsString('2026-09-01T12:00:00+02:00', $prompt);
		}
	}

	public function testInvestorInstructionsReachEveryProviderAndRespectSnapshot(): void
	{
		$snapshot = $this->createSnapshot('portfolio_evaluation');
		$snapshot['investorInstructions'] = 'Keep Czech holdings; disclose valuation risks.';
		foreach ($this->createProviderPrompts($snapshot) as $provider => $prompt) {
			self::assertSame(1, substr_count($prompt, 'Keep Czech holdings; disclose valuation risks.'), $provider);
			self::assertSame(1, substr_count($prompt, 'Investor instructions:'), $provider);
			self::assertStringContainsString('never change verified facts', $prompt);
			self::assertStringContainsString(
				'Distinguish preferences and current intentions from explicit prohibitions',
				$prompt,
			);
			self::assertStringContainsString('respecting their stated scope and exceptions', $prompt);
			self::assertStringContainsString('allows reconsideration', $prompt);
			self::assertStringContainsString('treat the associated allocation as intentional', $prompt);
			self::assertStringContainsString('Mention the accepted concentration once as neutral context', $prompt);
			self::assertStringContainsString('concrete deterioration in fundamentals, dividend safety', $prompt);
			self::assertStringContainsString(
				'without implying a change when no comparable earlier evidence is available',
				$prompt,
			);
		}

		self::assertSame('Keep Czech holdings; disclose valuation risks.', $snapshot['investorInstructions']);

		unset($snapshot['investorInstructions']);
		foreach ($this->createProviderPrompts($snapshot) as $prompt) {
			self::assertStringNotContainsString('Investor instructions:', $prompt);
		}
	}

	public function testRecommendationsRequireSpecificReasonsWithoutForcingPurchases(): void
	{
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$prompt = $generator->generateSystemInstruction($this->createSnapshot('portfolio_evaluation'));

		self::assertStringContainsString('not a second downside case', $prompt);
		self::assertStringContainsString('Avoid counting the same risk repeatedly', $prompt);
		self::assertStringContainsString('A missing reason to buy is not itself a reason to sell', $prompt);
		self::assertStringContainsString('name the concrete blocker', $prompt);
		self::assertStringContainsString('Do not target a quota', $prompt);
		self::assertStringContainsString('a purchase is not yet justified', $prompt);
	}

	public function testDefaultDividendProfileReachesEveryProviderWithoutExcludingNonDividendCandidates(): void
	{
		$snapshot = $this->createSnapshot('portfolio_evaluation');
		$snapshot['investorInstructions'] = StockAiAnalysisSettingsFacade::DEFAULT_INVESTOR_INSTRUCTIONS;
		foreach ($this->createProviderPrompts($snapshot) as $provider => $prompt) {
			self::assertSame(
				1,
				substr_count($prompt, StockAiAnalysisSettingsFacade::DEFAULT_INVESTOR_INSTRUCTIONS),
				$provider,
			);
			self::assertStringContainsString('preferencí kvalitních dividendových společností', $prompt, $provider);
			self::assertStringContainsString('watchlistu nemusí vyplácet dividendu', $prompt, $provider);
		}
	}

	public function testEveryProviderIncludesAConciseDecisionAndAvoidsDuplicateConcentrationWarnings(): void
	{
		foreach ($this->createProviderPrompts($this->createSnapshot('portfolio_evaluation')) as $prompt) {
			self::assertStringContainsString(
				'one short, self-contained Czech sentence of approximately 35 words or fewer',
				$prompt,
			);
			self::assertStringContainsString('any essential condition or uncertainty', $prompt);
			self::assertStringContainsString(
				'distinguish intentional allocations from actionable concentration problems',
				$prompt,
			);
			self::assertStringContainsString('Do not repeat the same concentration warning', $prompt);
		}
	}

	public function testEveryProviderCanRecommendBuyingSimpleWatchlistCompaniesAfterPriceResearch(): void
	{
		$snapshot = $this->createSnapshot('portfolio_evaluation', 'includesSimpleWatchlist');
		foreach ($this->createProviderPrompts($snapshot) as $prompt) {
			self::assertStringContainsString('Simple-watchlist companies are eligible for consider_buying', $prompt);
			self::assertStringContainsString('Prior promotion to the full watchlist is not required', $prompt);
			self::assertStringContainsString('currentPrice: null means a local quote is missing', $prompt);
			self::assertStringContainsString('never as a verified market quote', $prompt);
			self::assertStringContainsString('explain the gap and use a non-buy action', $prompt);
			self::assertStringNotContainsString('distinguish this schema limitation', $prompt);
		}
	}

	#[DataProvider('provideComprehensiveScopes')]
	public function testAllProviderPromptsRequireComprehensiveAssessment(
		string|null $portfolioPromptType,
		string $scopeKey,
	): void
	{
		$snapshot = $this->createSnapshot($portfolioPromptType, $scopeKey);
		foreach ($this->createProviderPrompts($snapshot) as $provider => $prompt) {
			self::assertStringContainsString(
				'comprehensive investment assessment as of analysisAsOf',
				$prompt,
				$provider,
			);
			self::assertStringContainsString('latest reported quarter or interim period', $prompt, $provider);
			self::assertStringContainsString('3–5 fiscal years', $prompt, $provider);
			self::assertStringContainsString('primary evidence', $prompt, $provider);
			self::assertStringContainsString('business quality', $prompt, $provider);
			self::assertStringContainsString('financial health', $prompt, $provider);
			self::assertStringContainsString('dividend sustainability', $prompt, $provider);
			self::assertStringContainsString('only to recent news and short-term performance', $prompt, $provider);
			self::assertStringContainsString('last 7 calendar days ending at analysisAsOf', $prompt, $provider);
			self::assertStringNotContainsString(
				'Use older information only as clearly identified background',
				$prompt,
				$provider,
			);
			self::assertStringNotContainsString('Use older facts only as background', $prompt, $provider);
			self::assertStringNotContainsString('otherwise focus on the last 7 calendar days', $prompt, $provider);
		}
	}

	public function testDailyPromptsKeepTheExactDailyMonitoringScope(): void
	{
		foreach ($this->createProviderPrompts($this->createSnapshot('daily_brief')) as $provider => $prompt) {
			self::assertStringContainsString('exact 24 hours ending at analysisAsOf', $prompt, $provider);
			self::assertStringContainsString('daily monitoring brief', $prompt, $provider);
			self::assertStringNotContainsString('3–5 fiscal years', $prompt, $provider);
			self::assertStringNotContainsString('last 7 calendar days', $prompt, $provider);
			self::assertStringNotContainsString('comprehensive investment assessment', $prompt, $provider);
		}
	}

	public function testSharedRulesKeepFundamentalsVisibleAndValuationSupported(): void
	{
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$instruction = $generator->generateSystemInstruction($this->createSnapshot('portfolio_evaluation'));

		self::assertStringContainsString('2026-09-07T09:59:41+02:00', $instruction);
		self::assertStringContainsString('publicly available at or before analysisAsOf', $instruction);
		self::assertStringContainsString('earnings.summary', $instruction);
		self::assertStringContainsString('dividend.summary', $instruction);
		self::assertStringContainsString('valuation.summary', $instruction);
		self::assertStringContainsString('recommendation.reasoning', $instruction);
		self::assertStringContainsString('portfolioEvaluation.summary', $instruction);
		self::assertStringContainsString('marketOverview.summary', $instruction);
		self::assertStringContainsString('low/base/high', $instruction);
		self::assertStringContainsString('assumptions', $instruction);
		self::assertStringContainsString('not rely only on an analyst target', $instruction);
		self::assertStringContainsString('Use null values when support is insufficient', $instruction);
	}

	public function testCodexMapsEveryInputAndSharesCompanyPortfolioContext(): void
	{
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$prompt = $generator->generateCodexTaskPrompt($this->createSnapshot('portfolio_evaluation'));
		foreach ([
			'portfolio-*.json` -> `portfolioAnalysis',
			'watchlist-*.json` -> `watchlistAnalysis',
			'simple-watchlist-*.json` -> `simpleWatchlistAnalysis',
			'stock.json` -> `stockAnalysis',
			'not a company task',
			'Use it in company assessments for portfolio fit and exposure',
			'one-item array; stockAnalysis is a single object',
		] as $instruction) {
			self::assertStringContainsString($instruction, $prompt);
		}
	}

	#[DataProvider('provideResearchModes')]
	public function testEveryProviderReceivesBusinessRulesAndEnumDefinitions(string $mode): void
	{
		foreach ($this->createProviderPrompts($this->createSnapshot($mode)) as $provider => $prompt) {
			foreach ([
				'0 < fairValueLow <= fairValueBase <= fairValueHigh',
				'currency equal to the input asset currency',
				'dataQuality.status = insufficient requires all five fields null and assessment uncertain',
				'Keep the valuation object and its explanatory summary',
				'recommendation.confidence measures the strength and consistency of evidence for the selected action',
				'Limited valuation data can still support a high-confidence decision to wait',
				'uncertain is also valid with a supported range',
				'materialEvents.timeHorizon describes the duration of investment impact',
				'catalysts.horizon describes expected realization',
				'Risk likelihood is the evidence-based chance',
				'Impact is severity if it occurs',
				'Use mixed for material metrics pointing in different directions',
				'dividend.status at_risk requires concrete evidence',
				'null means the calculation is unavailable',
				'not a daily or weekly return',
				'do not claim an aggregate percentage or total return',
				'portfolioContext.sectorAllocation as the source',
				'outside the schema currency enum',
				'next 30 calendar days',
				'Listing currency alone does not establish business exposure',
			] as $instruction) {
				self::assertStringContainsString($instruction, $prompt, $provider);
			}
		}
	}

	public function testDailyRulesDefineValuationScopeAndFollowUpPriority(): void
	{
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$prompt = $generator->generateSystemInstruction($this->createSnapshot('daily_brief'));
		foreach ([
			'a daily brief does not require rebuilding every model',
			'No new news alone is not a reason for insufficient data',
			'performanceComment, describe performance1Day',
			'portfolioImpactSummary is null only when portfolio analysis is outside scope',
			'watchlistSummary is null only when both watchlist scopes are excluded',
			'choose review_positions and retain the watchlist task in checklist',
			'Routine price volatility alone is not critical',
		] as $instruction) {
			self::assertStringContainsString($instruction, $prompt);
		}
	}

	public function testReduceReceivesNumericPerformanceAndRunScope(): void
	{
		$snapshot = $this->createSnapshot('portfolio_evaluation');
		$snapshot['portfolioContext'] = ['positions' => [[
			'stockAssetId' => '018f6d0e-7c2a-7f45-8d82-b9e4653cb957',
			'portfolioPercentage' => 25.0,
			'performance1Day' => null,
			'performance7Days' => -3.5,
			'profitLossPercent' => 12.5,
		]]];
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$prompt = $generator->generateReducePrompt($snapshot, [], [], []);

		self::assertStringContainsString(Json::encode($snapshot['portfolioContext'], pretty: true), $prompt);
		self::assertStringContainsString(Json::encode($snapshot['scope'], pretty: true), $prompt);
	}

	/**
	 * @return array<string, array{string|null, string}>
	 */
	public static function provideComprehensiveScopes(): array
	{
		return [
			'portfolio evaluation' => ['portfolio_evaluation', 'includesPortfolio'],
			'portfolio without explicit mode' => [null, 'includesPortfolio'],
			'watchlist only' => [null, 'includesWatchlist'],
			'simple watchlist only' => [null, 'includesSimpleWatchlist'],
			'single stock only' => [null, 'includesStockAnalysis'],
			'market only' => [null, 'includesMarketOverview'],
		];
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, string>
	 */
	private function createProviderPrompts(array $snapshot): array
	{
		$generator = new StockAiAnalysisV2PromptGenerator(new StockAiAnalysisV2SchemaFactory());
		$system = $generator->generateSystemInstruction($snapshot) . "\n";
		$prompts = [
			'manual' => $system . $generator->generateManualPrompt($snapshot),
			'codex' => $system . $generator->generateCodexTaskPrompt($snapshot),
			'gemini full' => $system . $generator->generateTaskPrompt($snapshot),
			'gemini reduce' => $system . $generator->generateReducePrompt($snapshot, [], [], []),
		];
		foreach (['portfolioAnalysis', 'watchlistAnalysis', 'simpleWatchlistAnalysis', 'stockAnalysis'] as $rootKey) {
			$prompts[$rootKey] = $system . $generator->generateCompanyPrompt($snapshot, $rootKey, []);
		}

		return $prompts;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSnapshot(string|null $portfolioPromptType, string $scopeKey = 'includesPortfolio'): array
	{
		return [
			'schemaVersion' => 2,
			'runId' => '018f6d0e-7c2a-7f45-8d82-b9e4653cb956',
			'analysisAsOf' => '2026-09-07T09:59:41+02:00',
			'scope' => [
				'includesPortfolio' => false,
				'includesWatchlist' => false,
				'includesSimpleWatchlist' => false,
				'includesMarketOverview' => false,
				'includesStockAnalysis' => false,
				'portfolioPromptType' => $portfolioPromptType,
				$scopeKey => true,
			],
		];
	}

}
