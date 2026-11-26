<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Mcp\Support\McpInvalidInputException;
use App\Stock\Asset\StockAssetRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class SearchStocksTool
{

	private const int MAX_LIMIT = 50;

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAssetRepository $stockAssetRepository,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(string $query = '', string|null $sector = null, int $limit = 20): array
	{
		$trimmedQuery = trim($query);
		$trimmedSector = $sector !== null ? trim($sector) : '';

		if ($trimmedQuery === '' && $trimmedSector === '') {
			throw new McpInvalidInputException('Provide a non-empty query or sector filter.');
		}

		if ($limit < 1 || $limit > self::MAX_LIMIT) {
			throw new McpInvalidInputException(
				sprintf('limit must be between 1 and %d.', self::MAX_LIMIT),
			);
		}

		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		/** @var array<string, true> $heldIds */
		$heldIds = [];
		foreach ($this->stockAssetRepository->findWithPositionsForAppAdmin($owner, true) as $heldAsset) {
			$heldIds[$heldAsset->getId()->toString()] = true;
		}

		$matches = $this->stockAssetRepository->search(
			$trimmedQuery,
			$trimmedSector !== '' ? $trimmedSector : null,
			$limit,
		);

		$items = [];
		foreach ($matches as $asset) {
			$id = $asset->getId()->toString();
			$items[] = [
				'stockAssetId' => $id,
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
				'exchange' => $asset->getExchange()->value,
				'sector' => $asset->getIndustry()?->getName(),
				'currency' => $asset->getCurrency()->value,
				'currentPrice' => round($asset->getAssetCurrentPrice()->getPrice(), 4),
				'priceDownloadedAt' => $asset->getPriceDownloadedAt()?->format(DATE_ATOM),
				'inPortfolio' => isset($heldIds[$id]),
				'inWatchlist' => $asset->isWatchlist(),
				'hasValuation' => $asset->getValuationDownloadedAt() !== null,
			];
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'query' => $trimmedQuery,
			'sector' => $trimmedSector !== '' ? $trimmedSector : null,
			'count' => count($items),
			'items' => $items,
		];
	}

}
