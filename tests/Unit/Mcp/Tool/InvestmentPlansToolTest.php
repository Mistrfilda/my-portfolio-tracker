<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Tool\InvestmentPlansTool;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanRepository;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class InvestmentPlansToolTest extends TestCase
{

	public function testListsPlansForOwnerWithoutRawPromptOrResponse(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$referenceRun = new StockAiAnalysisRun(
			$owner,
			'PROMPT',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now,
			analysisSchemaVersion: 2,
		);
		$plan = new StockAiInvestmentPlan(
			$referenceRun,
			new AssetPriceEmbeddable(25000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(25000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(500000.0, CurrencyEnum::CZK),
			5.0,
			4.76,
			'SECRET PLAN PROMPT',
			[],
			$now,
		);
		$plan->complete(
			'SECRET RAW RESPONSE',
			[
				'decision' => ['summary' => 'Deploy into MSFT and JNJ'],
				'allocations' => [
					['ticker' => 'MSFT', 'amountCzk' => 15000.0],
					['ticker' => 'JNJ', 'amountCzk' => 10000.0],
				],
				'portfolioImpact' => ['techWeightAfter' => 28.5],
				'alternatives' => [],
				'keyRisks' => ['FX volatility'],
			],
			StockAiAnalysisProcessingSourceEnum::GEMINI,
			$now,
		);

		$repository = $this->createMock(StockAiInvestmentPlanRepository::class);
		$repository->expects(self::once())
			->method('findLatestForAppAdmin')
			->with($owner, 5)
			->willReturn([$plan]);

		$tool = new InvestmentPlansTool($currentAdminGetter, $datetimeFactory, $repository);
		$result = $tool->execute(limit: 5);

		self::assertSame(1, $result['count']);
		self::assertIsArray($result['plans']);
		self::assertIsArray($result['plans'][0]);
		self::assertSame($plan->getId()->toString(), $result['plans'][0]['planId']);
		self::assertArrayNotHasKey('generatedPrompt', $result['plans'][0]);
		self::assertArrayNotHasKey('rawResponse', $result['plans'][0]);
		self::assertSame(['summary' => 'Deploy into MSFT and JNJ'], $result['plans'][0]['decision']);
		self::assertSame(['FX volatility'], $result['plans'][0]['keyRisks']);
	}

	public function testThrowsOnUnknownPlanId(): void
	{
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-05-01 12:00:00'));

		$repository = self::createStub(StockAiInvestmentPlanRepository::class);
		$repository->method('getByIdForAppAdmin')->willThrowException(new NoEntityFoundException());

		$tool = new InvestmentPlansTool($currentAdminGetter, $datetimeFactory, $repository);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(planId: Uuid::uuid4()->toString());
	}

}
