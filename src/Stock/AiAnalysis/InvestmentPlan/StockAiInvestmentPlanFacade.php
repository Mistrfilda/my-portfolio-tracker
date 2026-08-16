<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Stock\Position\StockPositionFacade;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class StockAiInvestmentPlanFacade
{

	public function __construct(
		private readonly StockAiInvestmentPlanRepository $investmentPlanRepository,
		private readonly StockAiAnalysisRunRepository $stockAiAnalysisRunRepository,
		private readonly StockPositionFacade $stockPositionFacade,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
		private readonly StockAiInvestmentPlanCalculator $calculator,
		private readonly StockAiInvestmentPlanSnapshotFactory $snapshotFactory,
		private readonly StockAiInvestmentPlanPromptGenerator $promptGenerator,
		private readonly StockAiInvestmentPlanResponseValidator $responseValidator,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(
		float $requestedAmount,
		CurrencyEnum $requestedCurrency,
		UuidInterface $referenceAnalysisRunId,
	): StockAiInvestmentPlan
	{
		if (!is_finite($requestedAmount) || $requestedAmount <= 0) {
			throw new InvalidArgumentException('Investment amount must be greater than zero.');
		}

		$referenceAnalysisRun = $this->stockAiAnalysisRunRepository->getById($referenceAnalysisRunId);
		$this->assertValidReferenceAnalysis($referenceAnalysisRun);

		$now = $this->datetimeFactory->createNow();
		$portfolioValueCzk = $this->stockPositionFacade
			->getCurrentPortfolioValueSummaryPrice(CurrencyEnum::CZK)
			->getPrice();
		$requestedAmountCzk = $requestedCurrency === CurrencyEnum::CZK
			? $requestedAmount
			: $this->currencyConversionFacade->convertSimpleValue(
				$requestedAmount,
				$requestedCurrency,
				CurrencyEnum::CZK,
				$now,
			);
		$requestedAmountCzk = round($requestedAmountCzk, 2);
		$currentPercent = $this->calculator->calculateCurrentPortfolioPercent(
			$requestedAmountCzk,
			$portfolioValueCzk,
		);
		$projectedPercent = $this->calculator->calculateProjectedPortfolioPercent(
			$requestedAmountCzk,
			$portfolioValueCzk,
		);
		$planId = Uuid::uuid4();
		$snapshot = $this->snapshotFactory->create(
			$planId,
			$now,
			$requestedAmount,
			$requestedCurrency,
			$requestedAmountCzk,
			$portfolioValueCzk,
			$currentPercent,
			$projectedPercent,
			$referenceAnalysisRun,
		);
		$plan = new StockAiInvestmentPlan(
			$referenceAnalysisRun,
			new AssetPriceEmbeddable($requestedAmount, $requestedCurrency),
			new AssetPriceEmbeddable($requestedAmountCzk, CurrencyEnum::CZK),
			new AssetPriceEmbeddable($portfolioValueCzk, CurrencyEnum::CZK),
			$currentPercent,
			$projectedPercent,
			$this->promptGenerator->generateTaskPrompt($snapshot),
			$snapshot,
			$now,
			$planId,
		);

		$this->entityManager->persist($plan);
		$this->entityManager->flush();

		return $plan;
	}

	public function get(string $id): StockAiInvestmentPlan
	{
		return $this->investmentPlanRepository->getById(Uuid::fromString($id));
	}

	public function getGeneratedPromptForDisplay(StockAiInvestmentPlan $plan): string
	{
		return sprintf(
			"System instruction:\n\n%s\n\nTask prompt:\n\n%s",
			$this->promptGenerator->generateSystemInstruction($plan->getInputSnapshot()),
			$plan->getGeneratedPrompt(),
		);
	}

	public function processCodexResponse(string $planId, string $rawResponse): void
	{
		$plan = $this->get($planId);
		$response = $this->responseValidator->validate($rawResponse, $plan->getInputSnapshot());
		$id = $plan->getId();

		$this->entityManager->wrapInTransaction(function () use ($id, $rawResponse, $response): void {
			$lockedPlan = $this->investmentPlanRepository->getById($id, LockMode::PESSIMISTIC_WRITE);
			if (!$lockedPlan->canImportCodexResponse()) {
				throw new RuntimeException('Investment plan has already been processed.');
			}

			$structuredData = $this->calculator->enrichResponse(
				$response->toArray(),
				$lockedPlan->getInputSnapshot(),
			);
			$lockedPlan->complete(
				$rawResponse,
				$structuredData,
				StockAiAnalysisProcessingSourceEnum::CODEX,
				$this->datetimeFactory->createNow(),
			);
		});
	}

	private function assertValidReferenceAnalysis(StockAiAnalysisRun $run): void
	{
		if (
			!$run->isV2()
			|| $run->getProcessedAt() === null
			|| !$run->includesPortfolio()
			|| $run->getPortfolioPromptType() !== StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION
			|| $run->getStructuredData() === null
		) {
			throw new InvalidArgumentException(
				'Reference analysis must be a completed v2 comprehensive portfolio evaluation.',
			);
		}
	}

}
