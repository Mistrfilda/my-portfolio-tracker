<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Industry;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\Industry\StockAssetIndustryDownloadFacade;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
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
			<div><div><table></table><table></table><table><tbody><tr>
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
