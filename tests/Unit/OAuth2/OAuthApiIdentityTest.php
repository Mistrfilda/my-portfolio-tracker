<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use Nette\Security\User;
use Nette\Security\UserStorage;
use PHPUnit\Framework\TestCase;

class OAuthApiIdentityTest extends TestCase
{

	public function testApiContextNeverFallsBackToNetteSessionAndClearsPreviousIdentity(): void
	{
		$storage = $this->createMock(UserStorage::class);
		$storage->expects(self::never())->method('getState');
		$storage->expects(self::never())->method('saveAuthentication');
		$user = new User($storage);
		$getter = new CurrentAppAdminGetter($user, self::createStub(AppAdminRepository::class));
		$getter->beginApiRequest();
		self::assertFalse($getter->isLoggedIn());
		self::assertNull($getter->getAppAdminOrNull());
		$admin = self::createStub(AppAdmin::class);
		$getter->setApiAppAdmin($admin);
		self::assertTrue($getter->isLoggedIn());
		self::assertSame($admin, $getter->getAppAdmin());
		$getter->endApiRequest();
		$getter->beginApiRequest();
		$this->expectException(AppAdminNotLoggedInException::class);
		$getter->getAppAdmin();
	}

}
