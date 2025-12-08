<?php

declare(strict_types = 1);

namespace App\Crypto\Asset;

use App\Admin\CurrentAppAdminGetter;
use App\UI\Icon\SvgIcon;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class CryptoAssetFacade
{

	public function __construct(
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(string $name, string $ticker, SvgIcon $svgIcon): CryptoAsset
	{
		if ($this->cryptoAssetRepository->findByTicker($ticker) !== null) {
			throw new CryptoAssetTickerAlreadyExistsException();
		}

		$cryptoAsset = new CryptoAsset(
			$name,
			$ticker,
			$svgIcon,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($cryptoAsset);
		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s create new crypto asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$cryptoAsset->getName(),
				$cryptoAsset->getId()->toString(),
			),
		);

		return $cryptoAsset;
	}

	public function update(
		UuidInterface $id,
		string $name,
		string $ticker,
		SvgIcon $svgIcon,
	): CryptoAsset
	{
		$cryptoAsset = $this->cryptoAssetRepository->getById($id);
		$cryptoAsset->update(
			$name,
			$ticker,
			$svgIcon,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s updated crypto asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$cryptoAsset->getName(),
				$cryptoAsset->getId()->toString(),
			),
		);

		return $cryptoAsset;
	}

}
