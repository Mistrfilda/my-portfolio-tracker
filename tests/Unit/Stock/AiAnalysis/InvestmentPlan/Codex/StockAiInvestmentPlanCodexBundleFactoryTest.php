<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan\Codex;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\InvestmentPlan\Codex\StockAiInvestmentPlanCodexBundleFactory;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanPromptGenerator;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanSchemaFactory;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ZipArchive;

class StockAiInvestmentPlanCodexBundleFactoryTest extends TestCase
{

	public function testCreatesSelfContainedCodexBundle(): void
	{
		$tempDir = sys_get_temp_dir() . '/stock_ai_investment_plan_' . bin2hex(random_bytes(8));
		FileSystem::createDir($tempDir);
		$now = new ImmutableDateTime('2026-08-16 16:30:00');
		$planId = Uuid::uuid4();
		$referenceId = Uuid::uuid4();
		$referenceRun = new StockAiAnalysisRun(
			'Reference prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now,
			analysisSchemaVersion: 2,
			inputSnapshot: ['schemaVersion' => 2],
			id: $referenceId,
		);
		$snapshot = [
			'schemaVersion' => 1,
			'planId' => $planId->toString(),
			'analysisAsOf' => '2026-08-16T16:30:00+02:00',
			'capital' => ['requestedAmountCzk' => 40_000.0],
			'investorProfile' => ['strategy' => 'dividend_income_primary'],
			'investorInstructions' => 'Keep Czech holdings.',
			'userContext' => [
				'additionalInstructions' => 'Prefer companies with at least ten years of dividend growth.',
				'consideredCompanies' => ['Realty Income (O)', 'Visa (V)'],
			],
			'portfolio' => [],
			'watchlist' => [],
			'portfolioContext' => [],
			'referenceAnalysis' => ['runId' => $referenceId->toString()],
		];
		$plan = new StockAiInvestmentPlan(
			$referenceRun,
			new AssetPriceEmbeddable(40_000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(40_000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(4_000_000, CurrencyEnum::CZK),
			1.0,
			0.9901,
			'Prompt',
			$snapshot,
			$now,
			$planId,
		);
		$schemaFactory = new StockAiInvestmentPlanSchemaFactory();
		$factory = new StockAiInvestmentPlanCodexBundleFactory(
			new StockAiInvestmentPlanPromptGenerator($schemaFactory),
			$schemaFactory,
			$tempDir,
		);

		try {
			$bundle = $factory->create($plan);
			self::assertFileExists($bundle->filePath);
			self::assertSame(
				sprintf('stock-ai-investment-plan-%s.zip', $planId->toString()),
				$bundle->downloadName,
			);

			$zip = new ZipArchive();
			self::assertTrue($zip->open($bundle->filePath));
			self::assertNotFalse($zip->locateName('AGENTS.md'));
			self::assertNotFalse($zip->locateName('instructions/system.md'));
			self::assertNotFalse($zip->locateName('instructions/task.md'));
			self::assertNotFalse($zip->locateName('schema/result.schema.json'));
			self::assertNotFalse($zip->locateName('input/context.json'));
			self::assertNotFalse($zip->locateName('input/reference-analysis.json'));
			self::assertFalse($zip->locateName('result.json'));
			$context = Json::decode((string) $zip->getFromName('input/context.json'), forceArrays: true);
			self::assertIsArray($context);
			self::assertSame($snapshot['userContext'], $context['userContext']);
			self::assertArrayNotHasKey('investorInstructions', $context);
			self::assertSame(
				1,
				substr_count((string) $zip->getFromName('instructions/system.md'), 'Keep Czech holdings.'),
			);
			self::assertStringNotContainsString(
				'Keep Czech holdings.',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertSame($snapshot, $plan->getInputSnapshot());
			self::assertStringContainsString(
				'Prefer companies with at least ten years of dividend growth.',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertStringContainsString(
				'userContext.consideredCompanies',
				(string) $zip->getFromName('AGENTS.md'),
			);
			self::assertStringContainsString(
				StockAiInvestmentPlanCodexBundleFactory::START_PROMPT,
				(string) $zip->getFromName('AGENTS.md'),
			);
			self::assertTrue($zip->close());
		} finally {
			FileSystem::delete($tempDir);
		}
	}

}
