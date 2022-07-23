<?php

declare(strict_types = 1);

namespace App\Admin;

use App\Doctrine\NoEntityFoundException;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

class AppAdminAuthenticator implements Authenticator
{

	public function __construct(private AppAdminRepository $appAdminRepository, private Passwords $passwords)
	{
	}

	/**
	 * @throws AuthenticationException
	 */
	public function authenticate(string $user, string $password): IIdentity
	{
		try {
			$user = $this->appAdminRepository->findByUsername($user);
		} catch (NoEntityFoundException) {
			throw new AuthenticationException('User not found');
		}

		if (!$this->passwords->verify($password, $user->getPassword())) {
			throw new AuthenticationException('Invalid password');
		}

		return new SimpleIdentity($user->getId()->toString());
	}

}
