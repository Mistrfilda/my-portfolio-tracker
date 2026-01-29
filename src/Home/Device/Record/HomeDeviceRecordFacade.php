<?php

declare(strict_types = 1);

namespace App\Home\Device\Record;

use App\Admin\CurrentAppAdminGetter;
use App\Home\Device\HomeDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class HomeDeviceRecordFacade
{

	public function __construct(
		private HomeDeviceRepository $homeDeviceRepository,
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function createByInternalId(
		string $internalId,
		string|null $stringValue,
		float|null $floatValue,
		HomeDeviceRecordUnit|null $unit,
	): HomeDeviceRecord
	{
		$homeDevice = $this->homeDeviceRepository->getByInternalId($internalId);

		$record = new HomeDeviceRecord(
			$homeDevice,
			$this->currentAppAdminGetter->getAppAdminOrNull(),
			$stringValue,
			$floatValue,
			$unit,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($record);
		$this->entityManager->flush();

		return $record;
	}

}
