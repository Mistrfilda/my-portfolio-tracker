<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StockAiInvestmentPlanTest extends TestCase
{

	public function testPlanCanBeCompletedOnlyOnce(): void
	{
		$now = new ImmutableDateTime('2026-08-16 16:30:00');
		$plan = new StockAiInvestmentPlan(
			$this->createReferenceRun($now),
			new AssetPriceEmbeddable(40_000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(40_000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(4_000_000, CurrencyEnum::CZK),
			1.0,
			0.9901,
			'Prompt',
			['schemaVersion' => 1],
			$now,
		);

		$plan->complete(
			'{"valid":true}',
			['decision' => ['summary' => 'Investovat.']],
			StockAiAnalysisProcessingSourceEnum::CODEX,
			$now,
		);

		self::assertFalse($plan->canImportCodexResponse());
		self::assertSame(StockAiAnalysisProcessingSourceEnum::CODEX, $plan->getProcessingSource());
		self::assertSame('Investovat.', $plan->getDecision()['summary']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Investment plan has already been processed.');
		$plan->complete('{}', [], StockAiAnalysisProcessingSourceEnum::CODEX, $now);
	}

	private function createReferenceRun(ImmutableDateTime $now): StockAiAnalysisRun
	{
		return new StockAiAnalysisRun(
			'Prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now,
			analysisSchemaVersion: 2,
			inputSnapshot: ['schemaVersion' => 2],
		);
	}

}
