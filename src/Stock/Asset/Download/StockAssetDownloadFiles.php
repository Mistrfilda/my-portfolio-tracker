<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use stdClass;

final readonly class StockAssetDownloadFiles
{

	public function __construct(public JsonDataFolderService $folders, public UuidInterface $assetId)
	{
	}

	public function validate(string $filename): void
	{
		$file = $this->folders->getResultsFolder() . $filename;
		if (!is_file($file)) {
			throw new RuntimeException(sprintf('Missing download result: %s.', $filename));
		}

		$rows = Json::decode(FileSystem::read($file));
		if (!is_array($rows) || count($rows) !== 1 || !$rows[0] instanceof stdClass
			|| ($rows[0]->id ?? null) !== $this->assetId->toString()) {
			throw new RuntimeException('Download result must contain exactly the requested stock asset.');
		}

		TypeValidator::validateInt($rows[0]->downloadedAt ?? null);
	}

	public static function downloadedAt(stdClass $row, ImmutableDateTime $fallback): ImmutableDateTime
	{
		if (!isset($row->downloadedAt)) {
			return $fallback;
		}

		$timestamp = TypeValidator::validateInt($row->downloadedAt);
		if ($timestamp <= 0 || $timestamp > $fallback->getTimestamp() + 60) {
			throw new RuntimeException('Invalid download timestamp.');
		}

		return $fallback->setTimestamp($timestamp);
	}

}
