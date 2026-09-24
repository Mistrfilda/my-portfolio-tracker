<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Download;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\Download\StockAssetDataDownloadFacade;
use App\Stock\Asset\Download\StockAssetDownloadFiles;
use App\Stock\Asset\Download\StockAssetScraperRunner;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\Json\StockAssetJsonDividendDownloader;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\Downloader\Json\JsonDataDownloaderFacade;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Price\Downloader\Pse\PseDataDownloaderFacade;
use App\Stock\Price\Downloader\TwelveData\TwelveDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Valuation\Data\StockValuationDataFacade;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class StockAssetDataDownloadFacadeTest extends TestCase
{

	/** @param list<string> $scripts */
	#[DataProvider('settings')]
	public function testSelectedAssetRoutingAndCleanup(
		bool $price,
		bool $valuation,
		StockAssetPriceDownloaderEnum $provider,
		StockAssetDividendSourceEnum|null $dividendSource,
		array $scripts,
		bool $failPrice,
	): void
	{
		$id = Uuid::uuid4();
		$folder = sys_get_temp_dir() . '/stock-job-test-' . $id;
		$asset = $this->createStub(StockAsset::class);
		$asset->method('shouldBeUpdated')->willReturn($price);
		$asset->method('shouldDownloadValuation')->willReturn($valuation);
		$asset->method('getAssetPriceDownloader')->willReturn($provider);
		$asset->method('getStockAssetDividendSource')->willReturn($dividendSource);
		$repository = $this->createMock(StockAssetRepository::class);
		$repository->expects($this->once())->method('getById')->with($id)->willReturn($asset);
		$requests = $this->createMock(JsonDataSourceProviderFacade::class);
		$requests->expects($this->exactly(in_array('prices.js', $scripts, true) ? 1 : 0))
			->method('generatePriceSourcesJsonFile')->with($this->isString(), $asset);
		$requests->expects($this->exactly(in_array('dividends.js', $scripts, true) ? 1 : 0))
			->method('generateDividendsJsonFile')->with($this->isString(), $asset);
		$requests->expects($this->exactly($valuation ? 2 : 0))
			->method('generateStockValuationJsonFile')->with($this->isString(), $asset);
		$called = [];
		$jobFolders = [];
		$runner = $this->createMock(StockAssetScraperRunner::class);
		$runner->expects($this->exactly(count($scripts)))->method('run')->willReturnCallback(
			function (string $script, string $jobFolder) use (&$called, &$jobFolders, $folder, $failPrice): void {
				$this->assertStringStartsWith($folder . '/jobs/', $jobFolder);
				$this->assertDirectoryExists($jobFolder . '/results');
				$called[] = $script;
				$jobFolders[] = $jobFolder;
				if ($failPrice && $script === 'prices.js') {
					throw new RuntimeException('Simulated scraper failure.');
				}
			},
		);
		$scope = $this->callback(static fn (StockAssetDownloadFiles $files): bool => $files->assetId->equals($id));
		$prices = $this->createMock(JsonDataDownloaderFacade::class);
		$prices->expects($this->exactly(in_array('prices.js', $scripts, true) && !$failPrice ? 1 : 0))
			->method('getPriceForAssets')->with($scope);
		$dividends = $this->createMock(StockAssetJsonDividendDownloader::class);
		$dividends->expects($this->exactly(in_array('dividends.js', $scripts, true) ? 1 : 0))
			->method('downloadDividendRecords')->with($scope);
		$valuations = $this->createMock(StockValuationDataFacade::class);
		$valuations->expects($this->exactly($valuation ? 1 : 0))->method('processKeyStatistics')->with($scope);
		$valuations->expects($this->exactly($valuation ? 1 : 0))->method('processAnalystInsights')->with($scope);
		$twelve = $this->createMock(TwelveDataDownloaderFacade::class);
		$twelve->expects($this->exactly($price && $provider === StockAssetPriceDownloaderEnum::TWELVE_DATA ? 1 : 0))
			->method('getPriceForAssets')->with($asset);
		$pse = $this->createMock(PseDataDownloaderFacade::class);
		$pse->expects(
			$this->exactly($price && $provider === StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER ? 1 : 0),
		)
			->method('getPriceForAssets')->with($asset);
		$facade = new StockAssetDataDownloadFacade(
			$repository,
			new JsonDataFolderService($folder),
			$requests,
			$runner,
			$prices,
			$dividends,
			$valuations,
			$twelve,
			$pse,
			new NullLogger(),
		);
		try {
			try {
				$facade->download($id);
				$this->assertFalse($failPrice);
			} catch (RuntimeException $exception) {
				$this->assertTrue($failPrice);
				$this->assertStringContainsString('download failed: price', $exception->getMessage());
			}

			$this->assertSame($scripts, $called);
			foreach ($jobFolders as $jobFolder) {
				$this->assertDirectoryDoesNotExist($jobFolder);
			}
		} finally {
			FileSystem::delete($folder);
		}
	}

	/** @return iterable<string, array{bool, bool, StockAssetPriceDownloaderEnum, StockAssetDividendSourceEnum|null, list<string>, bool}> */
	public static function settings(): iterable
	{
		yield 'all web data' => [true, true, StockAssetPriceDownloaderEnum::WEB_SCRAP, StockAssetDividendSourceEnum::WEB,
			['prices.js', 'dividends.js', 'financials.js', 'analyst.js'], false];

		yield 'failure does not skip other data' => [true, true, StockAssetPriceDownloaderEnum::WEB_SCRAP, StockAssetDividendSourceEnum::WEB,
			['prices.js', 'dividends.js', 'financials.js', 'analyst.js'], true];

		yield 'valuation alone' => [false, true, StockAssetPriceDownloaderEnum::WEB_SCRAP, StockAssetDividendSourceEnum::WEB,
			['financials.js', 'analyst.js'], false];

		yield 'manual dividends' => [true, false, StockAssetPriceDownloaderEnum::WEB_SCRAP, StockAssetDividendSourceEnum::MANUAL,
			['prices.js'], false];

		yield 'Twelve Data' => [true, false, StockAssetPriceDownloaderEnum::TWELVE_DATA, null, [], false];
		yield 'PSE' => [true, false, StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER, null, [], false];
		yield 'disabled' => [false, false, StockAssetPriceDownloaderEnum::WEB_SCRAP, null, [], false];
	}

}
