<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\Codex;

use App\Stock\AiAnalysis\Codex\StockAiAnalysisCodexBundleFactory;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ZipArchive;

class StockAiAnalysisCodexBundleFactoryTest extends TestCase
{

	public function testCreatesSelfContainedBundleWithSeparateCompanyInputs(): void
	{
		$tempDir = sys_get_temp_dir() . '/stock_ai_analysis_codex_' . bin2hex(random_bytes(8));
		FileSystem::createDir($tempDir);
		$runId = Uuid::uuid4();
		$snapshot = $this->createSnapshot($runId->toString());
		$run = new StockAiAnalysisRun(
			'Prompt',
			true,
			true,
			false,
			null,
			new ImmutableDateTime('2026-07-21 10:00:00'),
			analysisSchemaVersion: 2,
			inputSnapshot: $snapshot,
			id: $runId,
		);
		$schemaFactory = new StockAiAnalysisV2SchemaFactory();
		$factory = new StockAiAnalysisCodexBundleFactory(
			new StockAiAnalysisV2PromptGenerator($schemaFactory),
			$schemaFactory,
			$tempDir,
		);

		try {
			$bundle = $factory->create($run);
			self::assertSame(3, $run->getCodexCompanyTaskCount());
			self::assertFileExists($bundle->filePath);
			self::assertSame(sprintf('stock-ai-analysis-%s.zip', $runId->toString()), $bundle->downloadName);

			$zip = new ZipArchive();
			self::assertTrue($zip->open($bundle->filePath));
			self::assertNotFalse($zip->locateName('AGENTS.md'));
			self::assertNotFalse($zip->locateName('instructions/system.md'));
			self::assertNotFalse($zip->locateName('instructions/task.md'));
			self::assertNotFalse($zip->locateName('schema/company-result.schema.json'));
			self::assertNotFalse($zip->locateName('schema/result.schema.json'));
			self::assertNotFalse($zip->locateName('validate-stock-json.mjs'));
			self::assertNotSame('', trim((string) $zip->getFromName('validate-stock-json.mjs')));
			self::assertNotFalse($zip->locateName('input/context.json'));
			self::assertNotFalse(
				$zip->locateName('input/portfolio-001-' . $snapshot['portfolio'][0]['stockAssetId'] . '.json'),
			);
			self::assertNotFalse(
				$zip->locateName('input/watchlist-001-' . $snapshot['watchlist'][0]['stockAssetId'] . '.json'),
			);
			self::assertNotFalse(
				$zip->locateName(
					'input/simple-watchlist-001-' . $snapshot['simpleWatchlist'][0]['stockAssetId'] . '.json',
				),
			);
			self::assertFalse($zip->locateName('result.json'));
			self::assertStringContainsString(
				StockAiAnalysisCodexBundleFactory::START_PROMPT,
				(string) $zip->getFromName('README.md'),
			);
			self::assertStringContainsString(
				StockAiAnalysisCodexBundleFactory::START_PROMPT,
				(string) $zip->getFromName('AGENTS.md'),
			);
			self::assertStringContainsString(
				'node validate-stock-json.mjs schema/result.schema.json result.json',
				(string) $zip->getFromName('AGENTS.md'),
			);
			self::assertStringContainsString(
				'node validate-stock-json.mjs schema/company-result.schema.json <partial-file>',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertStringContainsString(
				'comprehensive investment assessment as of analysisAsOf',
				(string) $zip->getFromName('instructions/system.md'),
			);
			self::assertStringContainsString(
				'3–5 fiscal years',
				(string) $zip->getFromName('instructions/system.md'),
			);
			self::assertStringContainsString(
				'currentPrice: null` means the application did not provide a quote',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertStringContainsString(
				'state the researched price, currency, and quote date in `valuation.summary`',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertStringContainsString(
				'Do not use `uncertain` solely because input `currentPrice` is null',
				(string) $zip->getFromName('instructions/task.md'),
			);
			self::assertTrue($zip->close());
		} finally {
			FileSystem::delete($tempDir);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSnapshot(string $runId): array
	{
		return [
			'schemaVersion' => 2,
			'runId' => $runId,
			'analysisAsOf' => '2026-07-21T10:00:00+02:00',
			'timezone' => 'Europe/Prague',
			'scope' => [
				'includesPortfolio' => true,
				'includesWatchlist' => true,
				'includesSimpleWatchlist' => true,
				'includesMarketOverview' => false,
				'includesStockAnalysis' => false,
				'portfolioPromptType' => null,
			],
			'conventions' => ['narrativeLanguage' => 'cs'],
			'portfolio' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'Portfolio Corp',
				'stockAssetTicker' => 'PORT',
				'currency' => 'USD',
			]],
			'watchlist' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'Watch Corp',
				'stockAssetTicker' => 'WATCH',
				'currency' => 'EUR',
			]],
			'simpleWatchlist' => [[
				'stockAssetId' => Uuid::uuid4()->toString(),
				'stockAssetName' => 'SIMPLE',
				'stockAssetTicker' => 'SIMPLE',
				'currency' => 'USD',
				'currentPrice' => null,
				'recommendedEntryPrice' => 50.0,
			]],
			'portfolioContext' => [],
			'singleStock' => null,
		];
	}

}
