<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\Codex;

final readonly class StockAiInvestmentPlanCodexBundle
{

	public function __construct(
		public string $filePath,
		public string $downloadName,
	)
	{
	}

}
