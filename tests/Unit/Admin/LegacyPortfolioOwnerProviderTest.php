<?php

declare(strict_types = 1);

namespace App\Test\Unit\Admin;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Admin\LegacyPortfolioOwnerProvider;
use App\Doctrine\NoEntityFoundException;
use PHPUnit\Framework\TestCase;

class LegacyPortfolioOwnerProviderTest extends TestCase
{

	public function testBackgroundWorkUsesFilipWithoutReadingLogin(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$repository = $this->createMock(AppAdminRepository::class);
		$repository->expects(self::once())->method('findByUsername')->with('filip')->willReturn($owner);
		$current = $this->createMock(CurrentAppAdminGetter::class);
		$current->expects(self::never())->method('getAppAdmin');

		self::assertSame($owner, new LegacyPortfolioOwnerProvider($repository, $current)->getOwner());
	}

	public function testMissingLegacyOwnerDoesNotUseLoggedInUser(): void
	{
		$repository = $this->createStub(AppAdminRepository::class);
		$repository->method('findByUsername')->willThrowException(new NoEntityFoundException());
		$current = $this->createMock(CurrentAppAdminGetter::class);
		$current->expects(self::never())->method('getAppAdmin');

		$this->expectException(NoEntityFoundException::class);
		new LegacyPortfolioOwnerProvider($repository, $current)->getOwner();
	}

	public function testVisibilityFollowsUserChangesInOneProcess(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getUsername')->willReturn('filip');
		$anotherOwner = $this->createStub(AppAdmin::class);
		$anotherOwner->method('getUsername')->willReturn('filip2');
		$current = $this->createStub(CurrentAppAdminGetter::class);
		$current->method('getAppAdmin')->willReturn($owner, $anotherOwner, $owner);
		$provider = new LegacyPortfolioOwnerProvider($this->createStub(AppAdminRepository::class), $current);

		self::assertTrue($provider->isCurrentUserOwner());
		self::assertFalse($provider->isCurrentUserOwner());
		self::assertTrue($provider->isCurrentUserOwner());
	}

	public function testMissingLoginDoesNotAllowLegacyWidgets(): void
	{
		$current = $this->createStub(CurrentAppAdminGetter::class);
		$current->method('getAppAdmin')->willThrowException(new AppAdminNotLoggedInException());
		$provider = new LegacyPortfolioOwnerProvider($this->createStub(AppAdminRepository::class), $current);

		$this->expectException(AppAdminNotLoggedInException::class);
		$provider->isCurrentUserOwner();
	}

}
