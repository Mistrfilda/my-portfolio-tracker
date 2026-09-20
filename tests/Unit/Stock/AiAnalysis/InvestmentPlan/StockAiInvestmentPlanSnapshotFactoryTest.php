<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanSnapshotFactory;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiInvestmentPlanSnapshotFactoryTest extends TestCase
{

	public function testCreatesNormalizedUserContext(): void
	{
		$analysisPromptGenerator = $this->createStub(StockAiAnalysisPromptGenerator::class);
		$analysisPromptGenerator->method('getAutomaticPortfolioData')->willReturn([]);
		$analysisPromptGenerator->method('getAutomaticWatchlistData')->willReturn([]);
		$settings = $this->createStub(StockAiAnalysisSettingsFacade::class);
		$settings->method('getInvestorInstructions')->willReturn('Keep Czech holdings.');
		$factory = new StockAiInvestmentPlanSnapshotFactory($analysisPromptGenerator, $settings);
		$now = new ImmutableDateTime('2026-08-18 10:00:00');
		$referenceRun = new StockAiAnalysisRun(
			'Prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now,
			analysisSchemaVersion: 2,
			inputSnapshot: ['schemaVersion' => 2],
		);

		$snapshot = $factory->create(
			Uuid::uuid4(),
			$now,
			40_000,
			CurrencyEnum::CZK,
			40_000,
			4_000_000,
			1.0,
			0.9901,
			$referenceRun,
			"  Prefer companies with at least ten years of dividend growth. \n",
			" Realty Income (O)\r\n\r\nVisa (V)\nRealty Income (O) ",
		);

		self::assertSame('Keep Czech holdings.', $snapshot['investorInstructions']);

		self::assertSame([
			'additionalInstructions' => 'Prefer companies with at least ten years of dividend growth.',
			'consideredCompanies' => ['Realty Income (O)', 'Visa (V)'],
		], $snapshot['userContext']);
	}

}
