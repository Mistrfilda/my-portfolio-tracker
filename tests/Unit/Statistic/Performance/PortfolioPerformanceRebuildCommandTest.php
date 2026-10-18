<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Statistic\Performance\Command\PortfolioPerformanceRebuildCommand;
use App\Statistic\PortfolioStatisticBatchFacade;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PortfolioPerformanceRebuildCommandTest extends TestCase
{

	#[TestWith([true])]
	#[TestWith([false])]
	public function testRebuildsAllAccountsAndReportsFailure(bool $successful): void
	{
		$batch = $this->createMock(PortfolioStatisticBatchFacade::class);
		$batch->expects(self::once())->method('rebuildForAll')->willReturn($successful);
		$batch->expects(self::never())->method('saveForAll');
		$tester = new CommandTester(new PortfolioPerformanceRebuildCommand($batch));

		self::assertSame($successful ? 0 : 1, $tester->execute([]));
		self::assertStringContainsString(
			$successful ? 'rebuilt for all accounts' : 'failed for one or more accounts',
			$tester->getDisplay(),
		);
	}

}
