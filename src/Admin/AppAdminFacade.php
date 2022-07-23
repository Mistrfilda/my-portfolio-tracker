<?php

declare(strict_types = 1);

namespace App\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Security\Passwords;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class AppAdminFacade
{

	private EntityManagerInterface $entityManager;

	private AppAdminRepository $appAdminRepository;

	private Passwords $passwords;

	private LoggerInterface $logger;

	private DatetimeFactory $datetimeFactory;

	public function __construct(
		EntityManagerInterface $entityManager,
		AppAdminRepository $appAdminRepository,
		Passwords $passwords,
		LoggerInterface $logger,
		DatetimeFactory $datetimeFactory,
	)
	{
		$this->entityManager = $entityManager;
		$this->appAdminRepository = $appAdminRepository;
		$this->passwords = $passwords;
		$this->logger = $logger;
		$this->datetimeFactory = $datetimeFactory;
	}

	public function createAppAdmin(
		string $name,
		string $username,
		string $email,
		string $password,
		bool $forceNewPassword,
		bool $sysAdmin,
	): AppAdmin
	{
		$this->logger->info(
			'Creating app admin',
			[
				'name' => $name,
				'username' => $username,
				'email' => $email,
			],
		);

		$appAdmin = new AppAdmin(
			$name,
			$username,
			$email,
			$this->passwords->hash($password),
			$this->datetimeFactory->createNow(),
			$forceNewPassword,
			$sysAdmin,
		);

		$this->entityManager->persist($appAdmin);
		$this->entityManager->flush();
		$this->entityManager->refresh($appAdmin);

		return $appAdmin;
	}

	public function updateAppAdmin(
		UuidInterface $appAdminId,
		string $name,
		string $email,
		string|null $password,
		bool $forceNewPassword,
		bool $sysAdmin,
	): AppAdmin
	{
		$this->logger->info(
			'Updating app admin',
			[
				'appAdminId' => $appAdminId->toString(),
				'name' => $name,
			],
		);

		$appAdmin = $this->appAdminRepository->findById($appAdminId);
		$appAdmin->update(
			$name,
			$email,
			$password !== null ? $this->passwords->hash($password) : null,
			$this->datetimeFactory->createNow(),
			$forceNewPassword,
			$sysAdmin,
		);

		$this->entityManager->flush();
		$this->entityManager->refresh($appAdmin);

		return $appAdmin;
	}

	public function changeAppAdminPassword(
		UuidInterface $appAdminId,
		string $newPassword,
	): void
	{
		$appAdmin = $this->appAdminRepository->findById($appAdminId);
		$appAdmin->changePassword(
			$this->passwords->hash($newPassword),
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();
		$this->entityManager->refresh($appAdmin);
	}

}
