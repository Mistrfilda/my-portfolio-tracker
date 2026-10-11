<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\AiAnalysis\UI\StockAiAnalysisQuickSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisQuickSummaryTest extends TestCase
{

	#[DataProvider('provideRecommendations')]
	public function testKeepsTheOpeningSentenceWithoutSplittingFinancialFigures(
		string $reasoning,
		string $expected,
	): void
	{
		$result = $this->createStub(StockAiAnalysisStockResult::class);
		$result->method('getReasoning')->willReturn($reasoning);
		$result->method('getAiOpinion')->willReturn('Company overview.');

		self::assertSame($expected, StockAiAnalysisQuickSummary::fromResult($result));
	}

	public function testUsesLegacyOpinionWhenReasoningIsBlank(): void
	{
		$result = $this->createStub(StockAiAnalysisStockResult::class);
		$result->method('getReasoning')->willReturn(' ');
		$result->method('getAiOpinion')->willReturn('Držet díky kryté dividendě. Další podrobnosti.');

		self::assertSame('Držet díky kryté dividendě.', StockAiAnalysisQuickSummary::fromResult($result));
	}

	public function testHandlesMissingNarrative(): void
	{
		$result = $this->createStub(StockAiAnalysisStockResult::class);
		$result->method('getReasoning')->willReturn(null);
		$result->method('getAiOpinion')->willReturn(null);
		$result->method('getConclusion')->willReturn(null);

		self::assertSame('Stručné hodnocení není k dispozici.', StockAiAnalysisQuickSummary::fromResult($result));
	}

	/** @return array<string, array{string, string}> */
	public static function provideRecommendations(): array
	{
		return [
			'opening recommendation' => [
				'Přikoupit postupně díky ocenění, pokud dividenda zůstane krytá. Další nákup závisí na výsledcích.',
				'Přikoupit postupně díky ocenění, pokud dividenda zůstane krytá.',
			],
			'financial figures and currency abbreviation' => [
				'Zisk 1.25 mld. USD a výnos 4,5 % podporují držení. Rizikem je pokles marže.',
				'Zisk 1.25 mld. USD a výnos 4,5 % podporují držení.',
			],
			'company abbreviation' => [
				'AT&T Inc. nabízí krytou dividendu. Dluh je nutné sledovat.',
				'AT&T Inc. nabízí krytou dividendu.',
			],
			'whitespace' => [
				"  Držet díky\n stabilním ziskům.\n\nDalší podrobnosti.  ",
				'Držet díky stabilním ziskům.',
			],
			'no punctuation' => ['Čekat na lepší cenu', 'Čekat na lepší cenu'],
		];
	}

}
