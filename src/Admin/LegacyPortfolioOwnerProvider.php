<?php

declare(strict_types = 1);

namespace App\Admin;

// Temporary owner of data that will gain ownership in isolation steps 6-9.
class LegacyPortfolioOwnerProvider
{

	public function __construct(
		private readonly AppAdminRepository $appAdminRepository,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly string $ownerUsername = 'filip',
	)
	{
	}

	public function getOwner(): AppAdmin
	{
		return $this->appAdminRepository->findByUsername($this->ownerUsername);
	}

	public function isOwner(AppAdmin $appAdmin): bool
	{
		return $appAdmin->getUsername() === $this->ownerUsername;
	}

	public function isCurrentUserOwner(): bool
	{
		return $this->isOwner($this->currentAppAdminGetter->getAppAdmin());
	}

}
