<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Industry;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\Industry\StockAssetIndustryDownloadFacade;
use App\Stock\Asset\Industry\StockAssetIndustryImportException;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class StockAssetIndustryDownloadFacadeTest extends TestCase
{

	private string $tempDir;

	private JsonDataFolderService $folderService;

	protected function setUp(): void
	{
		parent::setUp();

		$this->tempDir = sys_get_temp_dir() . '/industry-download-' . Uuid::uuid4()->toString();
		FileSystem::createDir($this->tempDir . JsonDataFolderService::RESULTS_FOLDER);
		$this->folderService = new JsonDataFolderService($this->tempDir);
	}

	protected function tearDown(): void
	{
		FileSystem::delete($this->tempDir);

		parent::tearDown();
	}

	public function testDoesNothingWhenResultFileIsMissing(): void
	{
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->expects(self::never())->method('createNow');
		$repository = $this->createMock(StockAssetIndustryRepository::class);
		$repository->expects(self::never())->method('findAll');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::never())->method('updateValue');

		$this->createFacade($datetimeFactory, $repository, systemValueFacade: $systemValueFacade)->process();

		self::assertFileDoesNotExist($this->getResultFile());
	}

	public function testUpdatesMatchingIndustryAndReportsMissingMappings(): void
	{
		$html = <<<'HTML'
			<html lang="en"><body>
			<div></div><div></div><div></div><div></div><div></div><div></div>
			<div><div><table></table><table></table><table><tbody>
			<tr><td>No.</td><td>Name</td><td>Market Cap</td><td>P/E</td><td>Fwd P/E</td>
			<td>PEG</td><td>P/S</td><td>P/B</td><td>P/C</td><td>P/FCF</td></tr><tr>
			<td>1</td><td>Technology</td><td>1000</td><td>12.5</td><td>-</td>
			<td>invalid</td><td>3.0</td><td></td><td>5</td><td>7.5</td>
			</tr></tbody></table></div></div>
			</body></html>
			HTML;
		FileSystem::write($this->getResultFile(), Json::encode([['html' => $html]]));
		$now = new ImmutableDateTime('2026-01-15 10:00:00');
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$technology = $this->createMock(StockAssetIndustry::class);
		$technology->method('getMappingName')->willReturn('Technology');
		$technology->expects(self::once())->method('updateValues')->with(
			$now,
			12.5,
			1000.0,
			7.5,
			5.0,
			null,
			3.0,
			null,
			null,
		);
		$missing = $this->createStub(StockAssetIndustry::class);
		$missing->method('getMappingName')->willReturn('Missing industry');
		$repository = $this->createStub(StockAssetIndustryRepository::class);
		$repository->method('findAll')->willReturn([$technology, $missing]);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with('Missing 1 industries while updating values');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())
			->method('updateValue')
			->with(
				SystemValueEnum::STOCK_VALUATION_UPDATED_STOCK_ASSET_INDUSTRIES_COUNT,
				null,
				1,
			);

		$this->createFacade(
			$datetimeFactory,
			$repository,
			$logger,
			$systemValueFacade,
		)->process();
	}

	#[DataProvider('provideSavedHtml')]
	public function testImportsSavedHtml(string $html): void
	{
		$now = new ImmutableDateTime('2026-10-25 10:00:00');
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$firstIndustry = $this->createIndustry('Industry A & B');
		$secondIndustry = $this->createIndustry('Industry C');
		$repository = $this->createStub(StockAssetIndustryRepository::class);
		$repository->method('findAll')->willReturn([$firstIndustry, $secondIndustry]);
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::once())->method('updateValue')->with(
			SystemValueEnum::STOCK_VALUATION_UPDATED_STOCK_ASSET_INDUSTRIES_COUNT,
			null,
			2,
		);

		$count = $this->createFacade(
			$datetimeFactory,
			$repository,
			systemValueFacade: $systemValueFacade,
		)->processHtml($html);

		self::assertSame(2, $count);
		self::assertSame(150250000000.0, $firstIndustry->getMarketCap());
		self::assertSame(24.5, $firstIndustry->getCurrentPERatio());
		self::assertSame(20.0, $firstIndustry->getForwardPERatio());
		self::assertSame(1.25, $firstIndustry->getPegRatio());
		self::assertSame(3.5, $firstIndustry->getPriceToSales());
		self::assertSame(2.0, $firstIndustry->getPriceToBook());
		self::assertSame(8.75, $firstIndustry->getPriceToCashFlow());
		self::assertSame(15.5, $firstIndustry->getPriceToFreeCashFlow());
		self::assertSame($now, $firstIndustry->getUpdatedAt());
		self::assertNull($secondIndustry->getPriceToCashFlow());
		self::assertSame(12.0, $secondIndustry->getPriceToFreeCashFlow());
		self::assertFileDoesNotExist($this->getResultFile());
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideSavedHtml(): iterable
	{
		$html = FileSystem::read(__DIR__ . '/fixtures/industry-valuations.html');

		yield 'saved browser page' => [$html];
		yield 'page source without tbody' => [str_replace(['<tbody>', '</tbody>'], '', $html)];
		yield 'body fragment from Puppeteer' => [explode('</body>', explode('<body>', $html)[1])[0]];
	}

	public function testImportsColumnsByTheirHeaders(): void
	{
		$html = <<<'HTML'
			<table><tr>
			<th>Name</th><th>No.</th><th>P/B</th><th>P/FCF</th><th>P/C</th>
			<th>P/S</th><th>PEG</th><th>Fwd P/E</th><th>P/E</th><th>Market Cap</th>
			</tr><tr>
			<td>Technology</td><td>1</td><td>4</td><td>20</td><td>10</td>
			<td>3</td><td>1.5</td><td>12</td><td>15</td><td>1.25T</td>
			</tr></table>
			HTML;
		$industry = $this->createIndustry('Technology');
		$repository = $this->createStub(StockAssetIndustryRepository::class);
		$repository->method('findAll')->willReturn([$industry]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-10-25'));

		self::assertSame(1, $this->createFacade($datetimeFactory, $repository)->processHtml($html));
		self::assertSame(1250000000000.0, $industry->getMarketCap());
		self::assertSame(15.0, $industry->getCurrentPERatio());
		self::assertSame(12.0, $industry->getForwardPERatio());
		self::assertSame(1.5, $industry->getPegRatio());
		self::assertSame(3.0, $industry->getPriceToSales());
		self::assertSame(4.0, $industry->getPriceToBook());
		self::assertSame(10.0, $industry->getPriceToCashFlow());
		self::assertSame(20.0, $industry->getPriceToFreeCashFlow());
	}

	#[DataProvider('provideInvalidHtml')]
	public function testRejectsInvalidHtmlBeforeUpdatingAnyIndustries(string $html): void
	{
		$repository = $this->createMock(StockAssetIndustryRepository::class);
		$repository->expects(self::never())->method('findAll');
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->expects(self::never())->method('createNow');
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::never())->method('updateValue');

		$this->expectException(StockAssetIndustryImportException::class);
		$this->createFacade(
			$datetimeFactory,
			$repository,
			systemValueFacade: $systemValueFacade,
		)->processHtml($html);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideInvalidHtml(): iterable
	{
		yield 'empty file' => [''];
		yield 'robot challenge' => ['<html><body><h1>Verify you are human</h1></body></html>'];
		yield 'wrong table' => ['<table><tr><td>Name</td><td>Market Cap</td></tr><tr><td>Technology</td><td>1B</td></tr></table>'];

		$header = '<table><tr><td>No.</td><td>Name</td><td>Market Cap</td><td>P/E</td><td>Fwd P/E</td>'
			. '<td>PEG</td><td>P/S</td><td>P/B</td><td>P/C</td><td>P/FCF</td></tr>';
		$row = '<tr><td>1</td><td>Technology</td><td>1B</td><td>10</td><td>9</td>'
			. '<td>1</td><td>2</td><td>3</td><td>4</td><td>5</td></tr>';

		yield 'header without data' => [$header . '</table>'];
		yield 'incomplete row after valid row' => [$header . $row . '<tr><td>2</td><td>Other</td></tr></table>'];
		yield 'duplicate industry' => [$header . $row . $row . '</table>'];
		yield 'header outside a table' => [substr($header, 7) . $row];
	}

	public function testRejectsHtmlWithNoMatchingIndustryNames(): void
	{
		$industry = $this->createMock(StockAssetIndustry::class);
		$industry->method('getMappingName')->willReturn('Unmatched industry');
		$industry->expects(self::never())->method('updateValues');
		$repository = $this->createStub(StockAssetIndustryRepository::class);
		$repository->method('findAll')->willReturn([$industry]);
		$systemValueFacade = $this->createMock(SystemValueFacade::class);
		$systemValueFacade->expects(self::never())->method('updateValue');

		$this->expectException(StockAssetIndustryImportException::class);
		$this->createFacade(
			$this->createStub(DatetimeFactory::class),
			$repository,
			systemValueFacade: $systemValueFacade,
		)->processHtml(FileSystem::read(__DIR__ . '/fixtures/industry-valuations.html'));
	}

	private function createIndustry(string $mappingName): StockAssetIndustry
	{
		return new StockAssetIndustry(
			$mappingName,
			$mappingName,
			new ImmutableDateTime('2026-01-01'),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		);
	}

	private function createFacade(
		DatetimeFactory $datetimeFactory,
		StockAssetIndustryRepository $repository,
		LoggerInterface|null $logger = null,
		SystemValueFacade|null $systemValueFacade = null,
	): StockAssetIndustryDownloadFacade
	{
		return new StockAssetIndustryDownloadFacade(
			$this->folderService,
			$datetimeFactory,
			$repository,
			$logger ?? $this->createStub(LoggerInterface::class),
			$systemValueFacade ?? $this->createStub(SystemValueFacade::class),
		);
	}

	private function getResultFile(): string
	{
		return $this->folderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_INDUSTRY;
	}

}
