<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\InvestmentPlan;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanPromptGenerator;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanSchemaFactory;
use PHPUnit\Framework\TestCase;

class StockAiInvestmentPlanPromptGeneratorTest extends TestCase
{

	public function testIncludesAdditionalInstructionsAndConsideredCompanies(): void
	{
		$generator = new StockAiInvestmentPlanPromptGenerator(new StockAiInvestmentPlanSchemaFactory());
		$snapshot = [
			'investorInstructions' => 'Keep Czech holdings.',
			'schemaVersion' => 1,
			'planId' => '018f6d0e-7c2a-7f45-8d82-b9e4653cb956',
			'analysisAsOf' => '2026-08-18T10:00:00+02:00',
			'capital' => ['requestedAmountCzk' => 40_000.0],
			'userContext' => [
				'additionalInstructions' => 'Prefer companies with at least ten years of dividend growth.',
				'consideredCompanies' => ['Realty Income (O)', 'Visa (V)'],
			],
		];

		$taskPrompt = $generator->generateTaskPrompt($snapshot);
		$codexTaskPrompt = $generator->generateCodexTaskPrompt($snapshot);
		$systemInstruction = $generator->generateSystemInstruction($snapshot);
		foreach ([$taskPrompt, $codexTaskPrompt] as $prompt) {
			self::assertSame(1, substr_count($systemInstruction . $prompt, 'Keep Czech holdings.'));
			self::assertSame(1, substr_count($systemInstruction . $prompt, 'Investor instructions:'));
		}

		self::assertSame('Keep Czech holdings.', $snapshot['investorInstructions']);

		foreach ([$taskPrompt, $codexTaskPrompt] as $prompt) {
			self::assertStringContainsString('Additional user instructions', $prompt);
			self::assertStringContainsString(
				'Prefer companies with at least ten years of dividend growth.',
				$prompt,
			);
			self::assertStringContainsString('Realty Income (O)', $prompt);
			self::assertStringContainsString('Visa (V)', $prompt);
			self::assertStringContainsString('do not force a purchase', $prompt);
		}
	}

	public function testOmitsUserContextSectionsWhenNoContextWasProvided(): void
	{
		$generator = new StockAiInvestmentPlanPromptGenerator(new StockAiInvestmentPlanSchemaFactory());
		$snapshot = [
			'schemaVersion' => 1,
			'planId' => '018f6d0e-7c2a-7f45-8d82-b9e4653cb956',
			'analysisAsOf' => '2026-08-18T10:00:00+02:00',
			'capital' => ['requestedAmountCzk' => 40_000.0],
			'userContext' => [
				'additionalInstructions' => null,
				'consideredCompanies' => [],
			],
		];

		$taskPrompt = $generator->generateTaskPrompt($snapshot);

		self::assertStringNotContainsString('Additional user instructions', $taskPrompt);
		self::assertStringNotContainsString('explicitly wants these companies', $taskPrompt);
	}

}
