<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisGeminiJsonNormalizer;
use App\Test\UpdatedTestCase;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class StockAiAnalysisGeminiJsonNormalizerTest extends UpdatedTestCase
{

	public function testKeepsValidJsonObject(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();

		self::assertSame(
			'{"portfolioAnalysis":[]}',
			$normalizer->normalize(' {"portfolioAnalysis":[]} '),
		);
	}

	public function testExtractsMarkdownFencedJsonObject(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();

		self::assertSame(
			'{"portfolioAnalysis":[]}',
			$normalizer->normalize("```json\n{\"portfolioAnalysis\":[]}\n```"),
		);
	}

	public function testRemovesSingleStrayDotBeforeKnownAnalysisArray(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();
		$normalized = $normalizer->normalize('{"portfolioAnalysis":.[{"stockAssetTicker":"PEP"}]}');

		self::assertSame(
			[
				'portfolioAnalysis' => [
					[
						'stockAssetTicker' => 'PEP',
					],
				],
			],
			Json::decode($normalized, forceArrays: true),
		);
	}

	public function testRepairsMissingPortfolioAnalysisArrayObjectAndPositiveNewsKey(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();
		$normalized = $normalizer->normalize(
			'{"portfolioAnalysis":. Silné výsledky produkce za Q1 FY26.","negativeNews":"Pokles zisku."}]}',
		);

		self::assertSame(
			[
				'portfolioAnalysis' => [
					[
						'positiveNews' => 'Silné výsledky produkce za Q1 FY26.',
						'negativeNews' => 'Pokles zisku.',
					],
				],
			],
			Json::decode($normalized, forceArrays: true),
		);
	}

	public function testRepairsMissingWatchlistAnalysisArrayObjectAndNewsKey(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();
		$normalized = $normalizer->normalize(
			'{"watchlistAnalysis":. Nový kontrakt může podpořit růst.","earningsCommentary":"Výsledky byly solidní."}]}',
		);

		self::assertSame(
			[
				'watchlistAnalysis' => [
					[
						'news' => 'Nový kontrakt může podpořit růst.',
						'earningsCommentary' => 'Výsledky byly solidní.',
					],
				],
			],
			Json::decode($normalized, forceArrays: true),
		);
	}

	public function testDoesNotInventMissingJsonStructureForUnrecognizedFreeTextAfterKnownAnalysisKey(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();
		$normalized = $normalizer->normalize(
			'{"portfolioAnalysis":. Klíčová divize rostla}',
		);

		self::assertException(
			static fn () => Json::decode($normalized, forceArrays: true),
			JsonException::class,
		);
	}

}
