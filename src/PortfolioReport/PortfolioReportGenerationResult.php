<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

class PortfolioReportGenerationResult
{

	/**
	 * @param array<int, PortfolioReportAssetPerformance> $assetPerformances
	 * @param array<int, PortfolioReportDividend> $dividends
	 * @param array<int, PortfolioReportGoalProgress> $goalProgressItems
	 * @param array<string, mixed> $snapshot
	 */
	public function __construct(
		private readonly float $portfolioValueStartCzk,
		private readonly float $portfolioValueEndCzk,
		private readonly float $investedAmountStartCzk,
		private readonly float $investedAmountEndCzk,
		private readonly float $dividendsTotalCzk,
		private readonly string|null $goalsProgressSummary,
		private readonly string|null $summaryText,
		private readonly string $aiPrompt,
		private readonly array $assetPerformances,
		private readonly array $dividends,
		private readonly array $goalProgressItems,
		private readonly array $snapshot,
	)
	{
	}

	public function getPortfolioValueStartCzk(): float
	{
		return $this->portfolioValueStartCzk;
	}

	public function getPortfolioValueEndCzk(): float
	{
		return $this->portfolioValueEndCzk;
	}

	public function getInvestedAmountStartCzk(): float
	{
		return $this->investedAmountStartCzk;
	}

	public function getInvestedAmountEndCzk(): float
	{
		return $this->investedAmountEndCzk;
	}

	public function getDividendsTotalCzk(): float
	{
		return $this->dividendsTotalCzk;
	}

	public function getGoalsProgressSummary(): string|null
	{
		return $this->goalsProgressSummary;
	}

	public function getSummaryText(): string|null
	{
		return $this->summaryText;
	}

	public function getAiPrompt(): string
	{
		return $this->aiPrompt;
	}

	/** @return array<int, PortfolioReportAssetPerformance> */
	public function getAssetPerformances(): array
	{
		return $this->assetPerformances;
	}

	/** @return array<int, PortfolioReportDividend> */
	public function getDividends(): array
	{
		return $this->dividends;
	}

	/** @return array<int, PortfolioReportGoalProgress> */
	public function getGoalProgressItems(): array
	{
		return $this->goalProgressItems;
	}

	/** @return array<string, mixed> */
	public function getSnapshot(): array
	{
		return $this->snapshot;
	}

}
