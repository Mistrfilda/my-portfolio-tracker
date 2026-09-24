<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset;

use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetFacade;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\StockAssetFormFactory;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class StockAssetFormFactoryTest extends TestCase
{

	#[TestWith([true, false])]
	#[TestWith([true, true])]
	#[TestWith([false, false])]
	public function testCreationQueuesEnabledDataAndReportsQueueFailure(bool $enabled, bool $queueFails): void
	{
		$id = Uuid::uuid4();
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getId')->willReturn($id);
		$asset->method('shouldBeUpdated')->willReturn($enabled);
		$asset->method('shouldDownloadValuation')->willReturn(false);
		$saved = false;
		$assets = $this->createMock(StockAssetFacade::class);
		$assets->expects($this->once())->method('create')->willReturnCallback(
			static function () use ($asset, &$saved): StockAsset {
				$saved = true;
				return $asset;
			},
		);
		$jobs = $this->createMock(JobRequestFacade::class);
		$jobs->expects($this->exactly($enabled ? 1 : 0))->method('addStockAssetDownloadToQueue')
			->with($id->toString())->willReturnCallback(function () use (&$saved, $queueFails): void {
				$this->assertTrue($saved);
				if ($queueFails) {
					throw new RuntimeException('Queue unavailable.');
				}
			});
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->exactly($queueFails ? 1 : 0))->method('error');
		$forms = $this->createStub(AdminFormFactory::class);
		$form = $this->getMockBuilder(AdminForm::class)->onlyMethods(['isSubmitted'])->getMock();
		$form->expects($this->atLeastOnce())->method('isSubmitted')->willReturn(false);
		$forms->method('create')->willReturn($form);
		$factory = new StockAssetFormFactory(
			$forms,
			$jobs,
			$assets,
			$this->createStub(StockAssetRepository::class),
			$this->createStub(StockAssetIndustryRepository::class),
			$logger,
		);
		$reported = null;
		$form = $factory->create(null, static function (bool $failed) use (&$reported): void {
			$reported = $failed;
		});
		$form->setValues([
			'name' => 'Test stock', 'ticker' => 'TEST', 'currency' => CurrencyEnum::USD->value,
			'exchange' => StockAssetExchange::NYSE->value,
			'assetPriceDownloader' => StockAssetPriceDownloaderEnum::WEB_SCRAP->value,
			'shouldDownloadPrice' => $enabled, 'shouldDownloadValuation' => false, 'watchlist' => false,
		]);
		$form->onSuccess[0]($form);
		$this->assertSame($queueFails, $reported);
	}

}
