<?php

declare(strict_types = 1);

namespace App\OAuth2\Authorization;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Doctrine\NoEntityFoundException;
use Nette\Security\Passwords;
use Ramsey\Uuid\Uuid;
use SensitiveParameter;

class OAuthUserAuthenticator
{

	// A public dummy hash equalizes password verification for unknown usernames.
	private const string DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

	public function __construct(private AppAdminRepository $users, private Passwords $passwords)
	{
	}

	public function authenticate(string $username, #[SensitiveParameter] string $password): AppAdmin|null
	{
		try {
			$user = $this->users->findByUsername($username);
		} catch (NoEntityFoundException) {
			$user = null;
		}

		$valid = $this->passwords->verify($password, $user?->getPassword() ?? self::DUMMY_HASH);
		return $valid && $user !== null && !$user->isNewPasswordForced() ? $user : null;
	}

	public function findActive(string $id): AppAdmin|null
	{
		if (!Uuid::isValid($id)) {
			return null;
		}

		try {
			$user = $this->users->findById(Uuid::fromString($id));
			return $user->isNewPasswordForced() ? null : $user;
		} catch (NoEntityFoundException) {
			return null;
		}
	}

}
