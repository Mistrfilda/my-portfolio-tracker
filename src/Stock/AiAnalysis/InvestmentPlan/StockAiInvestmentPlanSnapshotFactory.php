<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;
use const DATE_ATOM;

class StockAiInvestmentPlanSnapshotFactory
{

	public function __construct(
		private readonly StockAiAnalysisPromptGenerator $stockAiAnalysisPromptGenerator,
		private readonly StockAiAnalysisSettingsFacade $settingsFacade,
	)
	{
	}

	/** @return array<string, mixed> */
	public function create(
		UuidInterface $planId,
		ImmutableDateTime $analysisAsOf,
		float $requestedAmount,
		CurrencyEnum $requestedCurrency,
		float $requestedAmountCzk,
		float $currentPortfolioValueCzk,
		float $requestedAmountToPortfolioPercent,
		float $requestedAmountToProjectedPortfolioPercent,
		StockAiAnalysisRun $referenceAnalysisRun,
		string $additionalInstructions,
		string $consideredCompanies,
	): array
	{
		$portfolio = $this->stockAiAnalysisPromptGenerator->getAutomaticPortfolioData();
		$watchlist = $this->stockAiAnalysisPromptGenerator->getAutomaticWatchlistData();
		$sectorAllocation = [];

		foreach ($portfolio as $index => $item) {
			if (!is_array($item)) {
				continue;
			}

			$percentage = $this->getNumericValue($item['portfolioPercentage'] ?? null);
			$item['currentValueCzk'] = round($currentPortfolioValueCzk * $percentage / 100, 2);
			$portfolio[$index] = $item;
			$sector = is_string($item['sector'] ?? null) ? $item['sector'] : 'Unknown';
			$sectorAllocation[$sector] = round(($sectorAllocation[$sector] ?? 0.0) + $percentage, 2);
		}

		arsort($sectorAllocation);

		return [
			'schemaVersion' => 1,
			'planId' => $planId->toString(),
			'analysisAsOf' => $analysisAsOf->format(DATE_ATOM),
			'timezone' => 'Europe/Prague',
			'investorInstructions' => $this->settingsFacade->getInvestorInstructions(),
			'conventions' => [
				'percentageUnit' => 'percentage_points',
				'priceUnit' => 'major_currency_unit',
				'baseCurrency' => CurrencyEnum::CZK->value,
				'currencyValues' => array_map(
					static fn (CurrencyEnum $currency): string => $currency->value,
					CurrencyEnum::getAll(),
				),
				'narrativeLanguage' => 'cs',
				'jsonKeyLanguage' => 'en',
			],
			'capital' => [
				'requestedAmount' => round($requestedAmount, 2),
				'requestedCurrency' => $requestedCurrency->value,
				'requestedAmountCzk' => round($requestedAmountCzk, 2),
				'currentPortfolioValueCzk' => round($currentPortfolioValueCzk, 2),
				'requestedAmountToPortfolioPercent' => $requestedAmountToPortfolioPercent,
				'projectedPortfolioValueCzk' => round($currentPortfolioValueCzk + $requestedAmountCzk, 2),
				'requestedAmountToProjectedPortfolioPercent' => $requestedAmountToProjectedPortfolioPercent,
			],
			'investorProfile' => [
				'strategy' => 'dividend_income_primary',
				'primaryGoal' => 'growing_sustainable_dividend_income',
				'secondaryGoal' => 'long_term_total_return',
				'horizon' => 'long_term_5_10_plus_years',
				'riskTolerance' => 'medium',
				'rebalancingFrequency' => 'quarterly',
				'preferences' => [
					'Prefer dividend-paying companies with durable cash generation.',
					'Prioritize sustainable dividends over the highest current yield.',
					'Assess payout safety with sector-appropriate measures, leverage, and dividend history.',
					'Avoid materially worsening single-position or sector concentration.',
					'Buy only with a reasonable valuation and margin of safety.',
				],
			],
			'userContext' => [
				'additionalInstructions' => $this->normalizeOptionalText($additionalInstructions),
				'consideredCompanies' => $this->normalizeConsideredCompanies($consideredCompanies),
			],
			'portfolio' => array_values($portfolio),
			'watchlist' => array_values($watchlist),
			'portfolioContext' => [
				'totalPositions' => count($portfolio),
				'sectorAllocation' => $sectorAllocation,
			],
			'referenceAnalysis' => $this->createReferenceAnalysis($referenceAnalysisRun),
		];
	}

	/** @return array<string, mixed> */
	private function createReferenceAnalysis(StockAiAnalysisRun $run): array
	{
		$inputSnapshot = $run->getInputSnapshot();
		$stockResults = [];
		foreach ($run->getResults() as $result) {
			if ($result->getStructuredData() === null) {
				continue;
			}

			$stockResults[] = [
				'type' => $result->getType()->value,
				'analysis' => $result->getStructuredData(),
			];
		}

		return [
			'runId' => $run->getId()->toString(),
			'analysisAsOf' => is_string($inputSnapshot['analysisAsOf'] ?? null)
				? $inputSnapshot['analysisAsOf']
				: $run->getCreatedAt()->format(DATE_ATOM),
			'processedAt' => $run->getProcessedAt()?->format(DATE_ATOM),
			'processingSource' => $run->getProcessingSource()?->value,
			'runAnalysis' => $run->getStructuredData(),
			'stockResults' => $stockResults,
		];
	}

	private function getNumericValue(mixed $value): float
	{
		return is_float($value) || is_int($value) ? (float) $value : 0.0;
	}

	private function normalizeOptionalText(string $value): string|null
	{
		$value = trim($value);

		return $value === '' ? null : $value;
	}

	/** @return list<string> */
	private function normalizeConsideredCompanies(string $value): array
	{
		$companies = [];
		$lines = preg_split('/\R/u', $value);
		foreach ($lines === false ? [] : $lines as $company) {
			$company = trim($company);
			if ($company === '' || in_array($company, $companies, true)) {
				continue;
			}

			$companies[] = $company;
		}

		return $companies;
	}

}
