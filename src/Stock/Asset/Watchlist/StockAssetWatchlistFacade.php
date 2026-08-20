<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Watchlist\Exception\StockAssetWatchlistTickerAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class StockAssetWatchlistFacade
{

	public function __construct(
		private readonly StockAssetWatchlistRepository $stockAssetWatchlistRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(
		string $name,
		string $ticker,
		float|null $recommendedEntryPrice,
		CurrencyEnum|null $currency,
	): StockAssetWatchlist
	{
		$ticker = $this->normalizeTicker($ticker);
		$this->assertTickerIsUnique($ticker);

		$stockAssetWatchlist = new StockAssetWatchlist(
			$name,
			$ticker,
			$recommendedEntryPrice,
			$currency,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->persist($stockAssetWatchlist);
		$this->entityManager->flush();

		return $stockAssetWatchlist;
	}

	public function update(
		UuidInterface $id,
		string $name,
		string $ticker,
		float|null $recommendedEntryPrice,
		CurrencyEnum|null $currency,
	): StockAssetWatchlist
	{
		$ticker = $this->normalizeTicker($ticker);
		$this->assertTickerIsUnique($ticker, $id);

		$stockAssetWatchlist = $this->stockAssetWatchlistRepository->getById($id);
		$stockAssetWatchlist->update(
			$name,
			$ticker,
			$recommendedEntryPrice,
			$currency,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();

		return $stockAssetWatchlist;
	}

	public function delete(UuidInterface $id): void
	{
		$this->entityManager->remove($this->stockAssetWatchlistRepository->getById($id));
		$this->entityManager->flush();
	}

	private function assertTickerIsUnique(string $ticker, UuidInterface|null $editedId = null): void
	{
		$existing = $this->stockAssetWatchlistRepository->findByTicker($ticker);
		if ($existing !== null && ($editedId === null || !$existing->getId()->equals($editedId))) {
			throw new StockAssetWatchlistTickerAlreadyExistsException();
		}
	}

	private function normalizeTicker(string $ticker): string
	{
		return strtoupper(trim($ticker));
	}

}
