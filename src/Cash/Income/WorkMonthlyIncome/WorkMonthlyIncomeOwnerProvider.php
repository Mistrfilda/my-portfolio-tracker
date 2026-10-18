<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Doctrine\NoEntityFoundException;
use RuntimeException;

class WorkMonthlyIncomeOwnerProvider
{

	public function __construct(
		private AppAdminRepository $appAdminRepository,
		private string $ownerUsername = 'filip',
	)
	{
	}

	public function getOwner(): AppAdmin
	{
		try {
			return $this->appAdminRepository->findByUsername($this->ownerUsername);
		} catch (NoEntityFoundException $exception) {
			throw new RuntimeException(
				sprintf('Configured work income owner "%s" does not exist.', $this->ownerUsername),
				previous: $exception,
			);
		}
	}

	public function getOwnerUsername(): string
	{
		return $this->ownerUsername;
	}

}
