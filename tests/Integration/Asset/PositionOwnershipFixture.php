<?php

declare(strict_types = 1);

namespace App\Test\Integration\Asset;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Position\Closed\CryptoClosedPosition;
use App\Crypto\Position\CryptoPosition;
use App\Currency\CurrencyEnum;
use App\Portu\Asset\PortuAsset;
use App\Portu\Position\PortuPosition;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\UI\Icon\SvgIcon;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class PositionOwnershipFixture
{

	/** @var array{AppAdmin, AppAdmin} */
	public array $admins;

	public StockAsset $stock;

	public CryptoAsset $crypto;

	public PortuAsset $portu;

	public PortuAsset $otherPortu;

	/** @var array<int, StockPosition> */
	public array $stockPositions = [];

	/** @var array<int, CryptoPosition> */
	public array $cryptoPositions = [];

	/** @var array<int, PortuPosition> */
	public array $portuPositions = [];

	/** @var array<int, StockClosedPosition> */
	public array $stockClosedPositions = [];

	/** @var array<int, CryptoClosedPosition> */
	public array $cryptoClosedPositions = [];

	/** @var array<int, PortuAssetPriceRecord> */
	public array $portuPrices = [];

	public function __construct(private EntityManagerInterface $entityManager)
	{
		$now = new ImmutableDateTime('2026-01-15');
		$this->admins = [$this->createAdmin(true), $this->createAdmin(false)];
		$this->stock = new StockAsset(
			'Ownership stock',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'OWN-' . bin2hex(random_bytes(4)),
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$now,
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: false,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
		$this->crypto = new CryptoAsset(
			'Ownership crypto',
			'OWN-' . bin2hex(random_bytes(4)),
			SvgIcon::CRYPTO_BITCOIN,
			$now,
		);
		$this->portu = new PortuAsset($this->admins[0], 'Ownership Portu', CurrencyEnum::CZK, $now);
		$this->otherPortu = new PortuAsset($this->admins[1], 'Other ownership Portu', CurrencyEnum::CZK, $now);
		foreach ([$this->stock, $this->crypto, $this->portu, $this->otherPortu] as $asset) {
			$this->entityManager->persist($asset);
		}

		foreach ($this->admins as $index => $admin) {
			$pieces = ($index + 1) * 10;
			$stockPosition = new StockPosition(
				$admin,
				$this->stock,
				$pieces,
				100.0,
				$now,
				new AssetPriceEmbeddable($pieces * 100.0, CurrencyEnum::USD),
				false,
				$now,
			);
			$stockClosedPosition = new StockClosedPosition(
				$stockPosition,
				110.0,
				$now,
				false,
				new AssetPriceEmbeddable($pieces * 110.0, CurrencyEnum::USD),
				$now,
			);
			$stockPosition->closePosition($stockClosedPosition);
			$this->stockPositions[] = $stockPosition;
			$this->stockClosedPositions[] = $stockClosedPosition;

			$cryptoPosition = new CryptoPosition(
				$admin,
				$this->crypto,
				($index + 1) * 0.5,
				100.0,
				$now,
				new AssetPriceEmbeddable(($index + 1) * 50.0, CurrencyEnum::USD),
				false,
				$now,
			);
			$cryptoClosedPosition = new CryptoClosedPosition(
				$cryptoPosition,
				110.0,
				$now,
				false,
				new AssetPriceEmbeddable(($index + 1) * 55.0, CurrencyEnum::USD),
				$now,
			);
			$cryptoPosition->closePosition($cryptoClosedPosition);
			$this->cryptoPositions[] = $cryptoPosition;
			$this->cryptoClosedPositions[] = $cryptoClosedPosition;

			foreach ([$stockPosition, $stockClosedPosition, $cryptoPosition, $cryptoClosedPosition] as $entity) {
				$this->entityManager->persist($entity);
			}

			$portuPosition = $this->createPortuPosition($admin);
			$this->portuPositions[] = $portuPosition;
			$this->portuPrices[] = $this->createPortuPrice($portuPosition);
		}

		$this->entityManager->flush();
	}

	public function createPortuPosition(AppAdmin $admin): PortuPosition
	{
		$now = new ImmutableDateTime('2026-01-15');
		$position = new PortuPosition(
			$admin->getId()->equals($this->admins[0]->getId()) ? $this->portu : $this->otherPortu,
			$admin,
			$now,
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(100.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1200.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1100.0, CurrencyEnum::CZK),
			$now,
		);
		$this->entityManager->persist($position);
		return $position;
	}

	public function createPortuPrice(PortuPosition $position): PortuAssetPriceRecord
	{
		$now = new ImmutableDateTime('2026-01-15');
		$price = new PortuAssetPriceRecord(
			$now,
			CurrencyEnum::CZK,
			new AssetPriceEmbeddable(1200.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1100.0, CurrencyEnum::CZK),
			$position,
			$now,
		);
		$this->entityManager->persist($price);
		return $price;
	}

	private function createAdmin(bool $sysAdmin): AppAdmin
	{
		$name = 'position-owner-' . Uuid::uuid4()->toString();
		$admin = new AppAdmin(
			$name,
			$name,
			$name . '@example.test',
			'unused-test-password',
			new ImmutableDateTime('2026-01-15'),
			false,
			$sysAdmin,
		);
		$this->entityManager->persist($admin);
		return $admin;
	}

}
