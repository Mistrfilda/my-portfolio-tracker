<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\Json;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;

class CryptoJsonDataSourceProviderFacade
{

	private const CRYPTO_DATA_FILE = 'crypto.json';

	public function __construct(
		private string $cryptoTableUrl,
		private JsonDataFolderService $jsonDataFolderService,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function generateRequestFile(): void
	{
		$data = [[
			'id' => 'allCrypto',
			'name' => 'crypto table',
			'currency' => CurrencyEnum::USD->value,
			'url' => $this->cryptoTableUrl,
		]];

		FileSystem::write(
			$this->jsonDataFolderService->getRequestsFolder() . self::CRYPTO_DATA_FILE,
			Json::encode($data),
		);
	}

	public function getCryptoTablePath(): string
	{
		return $this->jsonDataFolderService->getResultsFolder() . self::CRYPTO_DATA_FILE;
	}

	public function getCryptoTableParsedFilePath(): string
	{
		return sprintf(
			'%s%s-%s',
			$this->jsonDataFolderService->getParsedResultsFolder(),
			$this->datetimeFactory->createNow()->getTimestamp(),
			self::CRYPTO_DATA_FILE,
		);

	}

}
