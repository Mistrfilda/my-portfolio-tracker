<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Statistic\PortfolioStatisticBatchFacade;
use App\Statistic\PortfolioStatisticSaveCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PortfolioStatisticSaveCommandTest extends TestCase
{

	#[TestWith([true])]
	#[TestWith([false])]
	public function testSavesSnapshotRebuildsPerformanceAndAppendsFreshValues(bool $successful): void
	{
		$batch = $this->createMock(PortfolioStatisticBatchFacade::class);
		$batch->expects(self::once())->method('saveForAll')->willReturn($successful);
		$batch->expects(self::never())->method('rebuildForAll');
		$tester = new CommandTester(new PortfolioStatisticSaveCommand($batch));

		self::assertSame($successful ? 0 : 1, $tester->execute([]));
		self::assertStringContainsString(
			$successful ? 'saved for all accounts' : 'failed for one or more accounts',
			$tester->getDisplay(),
		);
	}

}
