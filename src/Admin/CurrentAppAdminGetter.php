<?php

declare(strict_types = 1);

namespace App\Admin;

use Nette\Security\User;
use Ramsey\Uuid\Uuid;

class CurrentAppAdminGetter
{

	private User $user;

	private AppAdminRepository $appAdminRepository;

	public function __construct(User $user, AppAdminRepository $appAdminRepository)
	{
		$this->user = $user;
		$this->appAdminRepository = $appAdminRepository;
	}

	public function isLoggedIn(): bool
	{
		return $this->user->isLoggedIn();
	}

	public function getAppAdmin(): AppAdmin
	{
		if (!$this->isLoggedIn() || $this->user->getIdentity() === null) {
			throw new AppAdminNotLoggedInException();
		}

		assert(is_string($this->user->getIdentity()->getId()));

		return $this->appAdminRepository->findById(
			Uuid::fromString($this->user->getIdentity()->getId()),
		);
	}

	public function getAppAdminOrNull(): AppAdmin|null
	{
		if (!$this->isLoggedIn() || $this->user->getIdentity() === null) {
			return null;
		}

		assert(is_string($this->user->getIdentity()->getId()));

		return $this->appAdminRepository->findById(
			Uuid::fromString($this->user->getIdentity()->getId()),
		);
	}

	public function login(string $username, string $password): void
	{
		$this->user->login($username, $password);
	}

	public function logout(): void
	{
		$this->user->logout(true);
	}

}
