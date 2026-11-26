<?php

declare(strict_types = 1);

namespace App\Mcp\Support;

use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use Ramsey\Uuid\Uuid;

class StockAssetResolver
{

	public function __construct(
		private readonly StockAssetRepository $stockAssetRepository,
	)
	{
	}

	public function resolve(string|null $ticker = null, string|null $stockAssetId = null): StockAsset
	{
		$trimmedId = $stockAssetId !== null ? trim($stockAssetId) : '';
		$trimmedTicker = $ticker !== null ? trim($ticker) : '';

		if ($trimmedId === '' && $trimmedTicker === '') {
			throw new McpInvalidInputException('Provide either ticker or stockAssetId.');
		}

		if ($trimmedId !== '') {
			if (!Uuid::isValid($trimmedId)) {
				throw new McpInvalidInputException(sprintf('Invalid stockAssetId UUID format: "%s".', $trimmedId));
			}

			try {
				return $this->stockAssetRepository->getById(Uuid::fromString($trimmedId));
			} catch (NoEntityFoundException) {
				throw new McpInvalidInputException(sprintf('Stock asset with ID "%s" was not found.', $trimmedId));
			}
		}

		$asset = $this->stockAssetRepository->findByTickerCaseInsensitive($trimmedTicker);
		if ($asset === null) {
			throw new McpInvalidInputException(sprintf('Stock asset with ticker "%s" was not found.', $trimmedTicker));
		}

		return $asset;
	}

}
