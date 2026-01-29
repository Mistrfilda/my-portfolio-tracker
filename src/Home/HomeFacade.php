<?php

declare(strict_types = 1);

namespace App\Home;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class HomeFacade
{

	public function __construct(
		private HomeRepository $homeRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function create(string $name): Home
	{
		$home = new Home(
			$name,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($home);
		$this->entityManager->flush();

		return $home;
	}

	public function update(UuidInterface $id, string $name): Home
	{
		$home = $this->homeRepository->getById($id);
		$home->update(
			$name,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();

		return $home;
	}

}
