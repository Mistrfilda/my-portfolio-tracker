<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisSettings;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisSettingsFacadeTest extends TestCase
{

	public function testCreatesSettingsOnFirstSave(): void
	{
		$repository = $this->createStub(StockAiAnalysisSettingsRepository::class);
		$repository->method('findSettings')->willReturn(null);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$now = new ImmutableDateTime('2026-09-20 10:00:00');
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$entityManager->expects(self::once())->method('persist')->with(self::callback(
			static fn (StockAiAnalysisSettings $settings): bool => $settings->getId() === 1
				&& $settings->getInvestorInstructions() === 'Keep Czech holdings.'
				&& $settings->getUpdatedAt() === $now,
		));
		$entityManager->expects(self::once())->method('flush');
		$facade = new StockAiAnalysisSettingsFacade($repository, $entityManager, $datetimeFactory);

		self::assertSame('', $facade->getInvestorInstructions());
		$facade->saveInvestorInstructions("  Keep Czech holdings.\n");
	}

	public function testUpdatesAndClearsExistingSettings(): void
	{
		$now = new ImmutableDateTime('2026-09-20 10:00:00');
		$settings = new StockAiAnalysisSettings('Original preferences', $now->deductDaysFromDatetime(1));
		$repository = $this->createStub(StockAiAnalysisSettingsRepository::class);
		$repository->method('findSettings')->willReturn($settings);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('persist');
		$entityManager->expects(self::exactly(2))->method('flush');
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$facade = new StockAiAnalysisSettingsFacade($repository, $entityManager, $datetimeFactory);

		$facade->saveInvestorInstructions('Updated preferences');
		self::assertSame('Updated preferences', $facade->getInvestorInstructions());
		self::assertSame($now, $settings->getUpdatedAt());
		$facade->saveInvestorInstructions(" \n ");
		self::assertSame('', $facade->getInvestorInstructions());
	}

}
