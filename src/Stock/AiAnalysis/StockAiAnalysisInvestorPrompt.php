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

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public static function withoutInstructions(array $snapshot): array
	{
		unset($snapshot['investorInstructions']);

		return $snapshot;
	}

	public static function generate(string $instructions): string
	{
		if (trim($instructions) === '') {
			return '';
		}

		return implode("\n", [
			'Investor instructions:',
			'Apply these investor preferences and action constraints wherever relevant in company assessments, recommendations, '
				. 'and run-level summaries, respecting their stated scope and exceptions. '
				. 'They take precedence over generic investment preferences, but never change verified facts, independent '
				. 'valuation, immutable identifiers, or the required output schema. Do not conceal risks or force positive conclusions.',
			'Distinguish preferences and current intentions from explicit prohibitions. Do not turn a preference to hold or '
				. 'a current plan not to sell into an unconditional ban. Preserve any conditions under which the investor '
				. 'allows reconsideration, and explain when the evidence meets those conditions.',
			'When the investor identifies holdings as a long-term core or states an intention to retain them, treat the '
				. 'associated allocation as intentional within that scope. Mention the accepted concentration once as neutral '
				. 'context; do not repeatedly present its size alone as a new problem or a reason to sell or trim the core.',
			'Keep material risks visible, but distinguish the accepted allocation from concrete deterioration in fundamentals, '
				. 'dividend safety, or the investment thesis. Escalate those developments with evidence and the relevant '
				. 'reconsideration condition, without implying a change when no comparable earlier evidence is available. '
				. 'Where appropriate, improve diversification through new capital outside the core within the investor constraints.',
			'When the investor explicitly prohibits an action, choose an allowed alternative within the stated scope and '
				. 'exceptions, including in summaries and action checklists. Explain the constraint separately from your '
				. 'financial assessment; a constrained action does not make holding economically attractive.',
			trim($instructions),
		]);
	}

}
