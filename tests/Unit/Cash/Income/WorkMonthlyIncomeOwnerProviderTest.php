<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Income;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Income\WorkMonthlyIncome\HarvestTimeDownloader;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeFacade;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WorkMonthlyIncomeOwnerProviderTest extends TestCase
{

	public function testExistingConfigurationDefaultsToFilip(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$repository = $this->createMock(AppAdminRepository::class);
		$repository->expects(self::once())->method('findByUsername')->with('filip')->willReturn($owner);
		self::assertSame($owner, new WorkMonthlyIncomeOwnerProvider($repository)->getOwner());
	}

	public function testMissingConfiguredOwnerStopsBeforeDownloadingOrWriting(): void
	{
		$repository = $this->createStub(AppAdminRepository::class);
		$repository->method('findByUsername')->willThrowException(new NoEntityFoundException());
		$downloader = $this->createMock(HarvestTimeDownloader::class);
		$downloader->expects(self::never())->method('getData');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('persist');
		$facade = new WorkMonthlyIncomeFacade(
			500,
			$this->createStub(WorkMonthlyIncomeRepository::class),
			$downloader,
			$this->createStub(DatetimeFactory::class),
			$entityManager,
			new WorkMonthlyIncomeOwnerProvider($repository, 'missing-owner'),
			$this->createStub(CurrentAppAdminGetter::class),
		);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Configured work income owner "missing-owner" does not exist.');
		$facade->download();
	}

}
