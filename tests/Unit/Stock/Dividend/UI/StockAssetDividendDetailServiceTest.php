<?php

namespace App\Test\Unit\Stock\Dividend\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;


class StockAssetDividendDetailServiceTest extends TestCase
{
    private StockAssetDividendDetailService $stockAssetDividendDetailService;

    protected function setUp(): void
    {
        $mockStockAssetDividendRepository = Mockery::mock(StockAssetDividendRepository::class);
        $mockStockAssetDividendRecordRepository = Mockery::mock(StockAssetDividendRecordRepository::class);
	    $mockStockAssetDividendRecordRepository->shouldIgnoreMissing();
	    $mockStockAssetDividendRepository->shouldIgnoreMissing();
        $this->stockAssetDividendDetailService = new StockAssetDividendDetailService(
            $mockStockAssetDividendRepository,
            $mockStockAssetDividendRecordRepository
        );
    }

    public function testGetDetailDTOFromDate()
    {

        $mockStockAsset = Mockery::mock(StockAsset::class);
		$mockStockAsset->shouldReceive('getCurrency')->andReturn(CurrencyEnum::CZK);
        $mockImmutableDateTime = Mockery::mock(ImmutableDateTime::class);

        $this->assertInstanceOf(
            StockAssetDividendDetailDTO::class,
            $this->stockAssetDividendDetailService->getDetailDTOFromDate($mockStockAsset,$mockImmutableDateTime)
        );
    }
}
