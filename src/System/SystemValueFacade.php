<?php

declare(strict_types = 1);

namespace App\System;

use App\System\Exception\SystemValueInvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueFacade
{

	public function __construct(
		private SystemValueRepository $systemValueRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function updateValue(
		SystemValueEnum $value,
		ImmutableDateTime|null $datetimeValue = null,
		int|null $intValue = null,
		string|null $stringValue = null,
	): void
	{
		$this->validate($datetimeValue, $intValue, $stringValue);
		$existingValue = $this->systemValueRepository->findByEnum($value);
		if ($existingValue !== null) {
			$existingValue->update(
				$this->datetimeFactory->createNow(),
				$datetimeValue,
				$intValue,
				$stringValue,
			);
		} else {
			$this->entityManager->persist(new SystemValue(
				$value,
				$datetimeValue,
				$intValue,
				$stringValue,
				$this->datetimeFactory->createNow(),
			));
		}

		$this->entityManager->flush();
	}

	private function validate(
		ImmutableDateTime|null $datetimeValue,
		int|null $intValue,
		string|null $stringValue,
	): void
	{
		$values = array_filter([$datetimeValue, $intValue, $stringValue]);

		if (count($values) !== 1) {
			throw new SystemValueInvalidArgumentException('Exactly one value must be non-null');
		}
	}

}
