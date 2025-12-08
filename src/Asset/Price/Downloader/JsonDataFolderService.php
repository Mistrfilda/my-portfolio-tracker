<?php

declare(strict_types = 1);

namespace App\Asset\Price\Downloader;

class JsonDataFolderService
{

	public const REQUESTS_FOLDER = '/requests/';

	public const RESULTS_FOLDER = '/results/';

	public const PARSED_RESULTS_FOLDER = '/parsed/';

	public function __construct(private string $folder)
	{

	}

	public function getFolder(): string
	{
		return $this->folder;
	}

	public function getResultsFolder(): string
	{
		return $this->folder . self::RESULTS_FOLDER;
	}

	public function getRequestsFolder(): string
	{
		return $this->folder . self::REQUESTS_FOLDER;
	}

	public function getParsedResultsFolder(): string
	{
		return $this->folder . self::PARSED_RESULTS_FOLDER;
	}

}
