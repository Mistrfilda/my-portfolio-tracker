<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Prompt;

use App\Mcp\Prompt\InvestmentPrompts;
use App\Mcp\Support\McpInvalidInputException;
use PHPUnit\Framework\TestCase;

class InvestmentPromptsTest extends TestCase
{

	public function testEvaluateBuyCandidateIncludesAllExpectedToolGuidance(): void
	{
		$prompts = new InvestmentPrompts();
		$messages = $prompts->evaluateBuyCandidate('msft', 25000.0);

		self::assertCount(1, $messages);
		self::assertSame('user', $messages[0]['role']);
		self::assertStringContainsString('MSFT', $messages[0]['content']);
		self::assertStringContainsString('get_portfolio_overview', $messages[0]['content']);
		self::assertStringContainsString('get_stock_valuation_detail', $messages[0]['content']);
		self::assertStringContainsString(
			'simulate_stock_purchase(ticker: "MSFT", amount: 25000.00',
			$messages[0]['content'],
		);
	}

	public function testCompareBuyCandidatesValidatesTickerCount(): void
	{
		$prompts = new InvestmentPrompts();
		$messages = $prompts->compareBuyCandidates('msft, jnj, v', 30000.0);

		self::assertCount(1, $messages);
		self::assertStringContainsString('MSFT, JNJ, V', $messages[0]['content']);

		$this->expectException(McpInvalidInputException::class);
		$prompts->compareBuyCandidates('MSFT');
	}

	public function testPortfolioRebalanceCheckReturnsGuidance(): void
	{
		$prompts = new InvestmentPrompts();
		$messages = $prompts->portfolioRebalanceCheck();

		self::assertCount(1, $messages);
		self::assertSame('user', $messages[0]['role']);
		self::assertStringContainsString('get_portfolio_overview', $messages[0]['content']);
		self::assertStringContainsString('get_investment_plans', $messages[0]['content']);
	}

}
