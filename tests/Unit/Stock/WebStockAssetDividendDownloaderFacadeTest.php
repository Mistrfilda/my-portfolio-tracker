<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\WebStockAssetDividendDownloaderFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class WebStockAssetDividendDownloaderFacadeTest extends TestCase
{

	private WebStockAssetDividendDownloaderFacade $downloader;

	protected function setUp(): void
	{
		$url = 'test-url';
		$psr7RequestFactory = $this->createMock(Psr7RequestFactory::class);
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$stockAssetRepository = $this->createMock(StockAssetRepository::class);
		$stockAssetDividendRepository = $this->createMock(StockAssetDividendRepository::class);
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->downloader = new WebStockAssetDividendDownloaderFacade(
			$url,
			$psr7RequestFactory,
			$psr18ClientFactory,
			$stockAssetRepository,
			$stockAssetDividendRepository,
			$datetimeFactory,
			$entityManager,
			$logger,
		);
	}

	public function testDownloadDividendRecords(): void
	{
		$response = $this->createMock(ResponseInterface::class);
		$stream = $this->createMock(StreamInterface::class);
		$stream->method('getContents')->willReturn('');
		$response->method('getBody')->willReturn($stream);

		$this->downloader->downloadDividendRecords();

		self::assertSame('', $stream->getContents());
	}

}
