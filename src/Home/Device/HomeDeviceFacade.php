<?php

declare(strict_types = 1);

namespace App\Home\Device;

use App\Home\HomeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class HomeDeviceFacade
{

	public function __construct(
		private HomeDeviceRepository $homeDeviceRepository,
		private HomeRepository $homeRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function create(
		UuidInterface $homeId,
		string $internalId,
		string $name,
		HomeDeviceType $type,
	): HomeDevice
	{
		$home = $this->homeRepository->getById($homeId);

		$homeDevice = new HomeDevice(
			$home,
			$internalId,
			$name,
			$type,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($homeDevice);
		$this->entityManager->flush();

		return $homeDevice;
	}

	public function update(
		UuidInterface $id,
		string $internalId,
		string $name,
		HomeDeviceType $type,
	): HomeDevice
	{
		$homeDevice = $this->homeDeviceRepository->getById($id);
		$homeDevice->update(
			$internalId,
			$name,
			$type,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();

		return $homeDevice;
	}

}
