<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Data;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataFacade;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StockValuationDataFacadeTest extends TestCase
{

	private string $folder;

	protected function setUp(): void
	{
		parent::setUp();

		$this->folder = __DIR__ . '/../../../../../temp/phpunit-stock-valuation-' . uniqid('', true);
		FileSystem::createDir($this->folder . JsonDataFolderService::RESULTS_FOLDER);
		FileSystem::createDir($this->folder . JsonDataFolderService::PARSED_RESULTS_FOLDER);
	}

	protected function tearDown(): void
	{
		if (is_dir($this->folder)) {
			FileSystem::delete($this->folder);
		}

		parent::tearDown();
	}

	public function testProcessKeyStatisticsKeepsMissingPercentagesNullAndNormalizesCurrencyValues(): void
	{
		$now = new ImmutableDateTime('2026-05-01 14:00:00');
		$stockAsset = new StockAsset(
			'Test GBP Stock',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'TST.L',
			StockAssetExchange::LSE,
			CurrencyEnum::GBP,
			$now,
			null,
			StockAssetDividendSourceEnum::WEB,
			null,
			null,
			true,
			true,
			false,
		);

		FileSystem::write(
			$this->folder . JsonDataFolderService::RESULTS_FOLDER . JsonDataSourceProviderFacade::STOCK_ASSET_KEY_STATISTICS_FILENAME,
			Json::encode([
				[
					'id' => $stockAsset->getId()->toString(),
					'currency' => CurrencyEnum::GBP->value,
					'textContent' => '',
					'html' => <<<'HTML'
						<html>
							<body>
								<h1>Test GBP Stock (TST.L)</h1>
								<table>
									<tr>
										<td>Market Cap</td>
										<td>100.00</td>
									</tr>
									<tr>
										<td>Forward Annual Dividend Yield</td>
										<td>--</td>
									</tr>
								</table>
							</body>
						</html>
						HTML,
				],
			]),
		);

		$persisted = [];
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects($this->atLeastOnce())->method(
			'persist',
		)->willReturnCallback(
			static function (object $entity) use (&$persisted): void {
				if ($entity instanceof StockValuationData) {
					$persisted[$entity->getValuationType()->value] = $entity;
				}
			},
		);
		$entityManager->expects($this->atLeastOnce())->method('flush');

		$repository = $this->createMock(StockAssetRepository::class);
		$repository->expects($this->once())->method('getById')->willReturn($stockAsset);

		$valuationDataRepository = $this->createMock(StockValuationDataRepository::class);
		$valuationDataRepository->expects($this->once())->method('removeTodayData')->with($stockAsset, $now);
		$valuationDataRepository->expects($this->once())->method('updateLastActive')->with($stockAsset);

		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->expects($this->once())->method('createNow')->willReturn($now);

		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects($this->exactly(2))->method('updateValue');

		$facade = new StockValuationDataFacade(
			new JsonDataFolderService($this->folder),
			$repository,
			$datetimeFactory,
			$entityManager,
			$valuationDataRepository,
			$systemValueFacade,
			new NullLogger(),
		);

		$facade->processKeyStatistics();

		$this->assertArrayHasKey(StockValuationTypeEnum::MARKET_CAP->value, $persisted);
		$this->assertArrayHasKey(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value, $persisted);
		$this->assertSame(100.0, $persisted[StockValuationTypeEnum::MARKET_CAP->value]->getFloatValue());
		$this->assertNull($persisted[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value]->getFloatValue());
		$this->assertNull($persisted[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value]->getStringValue());
	}

}
