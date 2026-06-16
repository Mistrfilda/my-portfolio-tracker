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

	public function testDoesNotInventMissingJsonStructureForFreeTextAfterKnownAnalysisKey(): void
	{
		$normalizer = new StockAiAnalysisGeminiJsonNormalizer();
		$normalized = $normalizer->normalize(
			'{"portfolioAnalysis":. Klíčová divize rostla, "negativeNews":"High payout ratio"}',
		);

		self::assertException(
			static fn () => Json::decode($normalized, forceArrays: true),
			JsonException::class,
		);
	}

}
