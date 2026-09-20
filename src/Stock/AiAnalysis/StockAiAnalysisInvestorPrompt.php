<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

class StockAiAnalysisInvestorPrompt
{

	/** @param array<string, mixed> $snapshot */
	public static function fromSnapshot(array $snapshot): string
	{
		$instructions = $snapshot['investorInstructions'] ?? null;

		return is_string($instructions) ? self::generate($instructions) : '';
	}

	public static function generate(string $instructions): string
	{
		if (trim($instructions) === '') {
			return '';
		}

		return implode("\n", [
			'Investor instructions:',
			'Apply these investor preferences and action constraints to every company, recommendation, and run-level summary. '
				. 'They take precedence over generic investment preferences, but never change verified facts, independent '
				. 'valuation, immutable identifiers, or the required output schema. Do not conceal risks or force positive conclusions.',
			'When an action is excluded by the investor, choose an allowed alternative and explain the constraint separately '
				. 'from your financial assessment. Do not reintroduce an excluded action in the summary or action checklist.',
			trim($instructions),
		]);
	}

}
