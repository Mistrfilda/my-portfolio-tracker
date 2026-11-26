<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Doctrine\NoEntityFoundException;
use App\Mcp\Support\McpInvalidInputException;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\Uuid;
use const DATE_ATOM;

class InvestmentPlansTool
{

	private const int MAX_LIMIT = 20;

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAiInvestmentPlanRepository $investmentPlanRepository,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(int $limit = 5, string|null $planId = null): array
	{
		if ($limit < 1 || $limit > self::MAX_LIMIT) {
			throw new McpInvalidInputException(sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
		}

		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		$trimmedPlanId = $planId !== null ? trim($planId) : '';
		if ($trimmedPlanId !== '') {
			if (!Uuid::isValid($trimmedPlanId)) {
				throw new McpInvalidInputException(sprintf('Invalid planId UUID format: "%s".', $trimmedPlanId));
			}

			try {
				$plans = [$this->investmentPlanRepository->getByIdForAppAdmin(
					Uuid::fromString($trimmedPlanId),
					$owner,
				)];
			} catch (NoEntityFoundException) {
				throw new McpInvalidInputException(
					sprintf('Investment plan with ID "%s" was not found.', $trimmedPlanId),
				);
			}
		} else {
			$plans = $this->investmentPlanRepository->findLatestForAppAdmin($owner, $limit);
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'count' => count($plans),
			'plans' => array_map($this->serializePlan(...), $plans),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializePlan(StockAiInvestmentPlan $plan): array
	{
		$referenceRun = $plan->getReferenceAnalysisRun();

		return [
			'planId' => $plan->getId()->toString(),
			'createdAt' => $plan->getCreatedAt()->format(DATE_ATOM),
			'processedAt' => $plan->getProcessedAt()?->format(DATE_ATOM),
			'schemaVersion' => $plan->getSchemaVersion(),
			'processingSource' => $plan->getProcessingSource()?->value,
			'requestedAmount' => [
				'amount' => round($plan->getRequestedAmount()->getPrice(), 2),
				'currency' => $plan->getRequestedAmount()->getCurrency()->value,
			],
			'requestedAmountCzk' => round($plan->getRequestedAmountInCzk()->getPrice(), 2),
			'portfolioValueCzk' => round($plan->getPortfolioValue()->getPrice(), 2),
			'requestedAmountToPortfolioPercent' => round($plan->getRequestedAmountToPortfolioPercent(), 2),
			'requestedAmountToProjectedPortfolioPercent' => round(
				$plan->getRequestedAmountToProjectedPortfolioPercent(),
				2,
			),
			'referenceAnalysisRun' => [
				'runId' => $referenceRun->getId()->toString(),
				'createdAt' => $referenceRun->getCreatedAt()->format(DATE_ATOM),
				'processedAt' => $referenceRun->getProcessedAt()?->format(DATE_ATOM),
			],
			'decision' => $plan->getDecision(),
			'allocations' => $plan->getAllocations(),
			'portfolioImpact' => $plan->getPortfolioImpact(),
			'alternatives' => $plan->getAlternatives(),
			'keyRisks' => $plan->getKeyRisks(),
		];
	}

}
