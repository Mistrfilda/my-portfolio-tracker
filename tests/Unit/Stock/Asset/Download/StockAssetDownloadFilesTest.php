<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Download;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\Download\StockAssetDownloadFiles;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class StockAssetDownloadFilesTest extends TestCase
{

	private string $folder;

	protected function setUp(): void
	{
		$this->folder = sys_get_temp_dir() . '/stock-files-test-' . Uuid::uuid4();
	}

	protected function tearDown(): void
	{
		FileSystem::delete($this->folder);
	}

	public function testOnlyRequestedAssetIsAccepted(): void
	{
		$id = Uuid::uuid4();
		$files = new StockAssetDownloadFiles(new JsonDataFolderService($this->folder), $id);
		FileSystem::write($files->folders->getResultsFolder() . 'prices.json', Json::encode([
			['id' => $id->toString(), 'downloadedAt' => 123],
		]));
		$files->validate('prices.json');
		$this->expectException(RuntimeException::class);
		(new StockAssetDownloadFiles($files->folders, Uuid::uuid4()))->validate('prices.json');
	}

	#[DataProvider('invalidResults')]
	public function testInvalidOrMissingResultFails(string|null $json): void
	{
		$files = new StockAssetDownloadFiles(new JsonDataFolderService($this->folder), Uuid::uuid4());
		if ($json !== null) {
			FileSystem::write($files->folders->getResultsFolder() . 'prices.json', $json);
		}

		$this->expectException(RuntimeException::class);
		$files->validate('prices.json');
	}

	/** @return iterable<array{string|null}> */
	public static function invalidResults(): iterable
	{
		yield [null];
		yield ['[]'];
		yield ['{}'];
		yield ['[{}, {}]'];
	}

	public function testFetchTimeIsPreservedAndFutureTimeRejected(): void
	{
		$now = new ImmutableDateTime('2026-09-24 12:00:00');
		$at = $now->deductHoursFromDatetime(1);
		$this->assertEquals(
			$at,
			StockAssetDownloadFiles::downloadedAt((object) ['downloadedAt' => $at->getTimestamp()], $now),
		);
		$this->expectException(RuntimeException::class);
		StockAssetDownloadFiles::downloadedAt((object) ['downloadedAt' => $now->getTimestamp() + 120], $now);
	}

}
