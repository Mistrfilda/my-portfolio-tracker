<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use Nette\Utils\Json;

class PortfolioReportPromptGenerator
{

	/**
	 * @param array<string, mixed> $payload
	 */
	public function generate(PortfolioReport $portfolioReport, array $payload): string
	{
		$parts = [];
		$parts[] = 'You are an investment portfolio analyst. Evaluate the provided periodic portfolio report only from the supplied data.';
		$parts[] = sprintf(
			'Report period: %s (%s - %s).',
			$portfolioReport->getPeriodType()->value,
			$portfolioReport->getDateFrom()->format('Y-m-d'),
			$portfolioReport->getDateTo()->format('Y-m-d'),
		);
		$parts[] = 'Rules:';
		$parts[] = '- Be concise, factual and practical.';
		$parts[] = '- Do not invent any numbers or events.';
		$parts[] = '- Explicitly mention main growth drivers, declines, dividend contribution, goal development';
		$parts[] = '  and notable activity/allocation changes.';
		$parts[] = '- Highlight risks such as concentration or weak performers only if supported by data.';
		$parts[] = 'Return strictly valid JSON with this structure:';
		$parts[] = Json::encode([
			'summary' => [
				'headline' => 'string',
				'portfolioPerformance' => 'string',
			],
			'drivers' => [
				'positive' => ['string'],
				'negative' => ['string'],
			],
			'dividends' => [
				'impact' => 'string',
				'highlights' => ['string'],
			],
			'goals' => [
				'summary' => 'string',
				'positiveGoals' => ['string'],
				'negativeGoals' => ['string'],
			],
			'activity' => [
				'summary' => 'string',
				'notableChanges' => ['string'],
			],
			'risks' => ['string'],
			'actionableInsights' => ['string'],
		], pretty: true);
		$parts[] = 'Data to analyze:';
		$parts[] = Json::encode($payload, pretty: true);

		return implode("\n\n", $parts);
	}

}
