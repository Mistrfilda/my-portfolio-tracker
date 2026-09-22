<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisV2PromptGeneratorTest extends TestCase
{

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
