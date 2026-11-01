<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Expense\UI\Control;

use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\UI\Control\ExpenseOverviewCategoryChartDataProvider;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class ExpenseOverviewCategoryChartDataProviderTest extends TestCase
{

	public function testInitialRequestKeepsSelectedPeriodAndAmountsWithCents(): void
	{
		$expense = new BankExpense(
			'chart',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			-123.45,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2025-10-01'),
			null,
			'Chart test',
			new ImmutableDateTime('2025-10-01'),
			$this->createStub(BankAccount::class),
		);
		$repository = $this->createMock(BankExpenseRepository::class);
		$repository->expects(self::once())->method('findForOverview')->with(2025, 10)->willReturn([$expense]);
		$provider = new ExpenseOverviewCategoryChartDataProvider($repository, 2025, 10);
		$provider->processParametersFromRequest([]);
		$data = $provider->getChartData()->jsonSerialize();
		self::assertSame(['Bez hlavního tagu'], $data['labels']);
		self::assertSame('Výdaje', $data['datasets'][0]['label']);
		self::assertSame([-123.45], $data['datasets'][0]['data']);
	}

	public function testAjaxWholeYearClearsTheMonthFromTheOriginalChartUrl(): void
	{
		$repository = $this->createMock(BankExpenseRepository::class);
		$repository->expects(self::once())->method('findForOverview')->with(2025, null)->willReturn([]);
		$provider = new ExpenseOverviewCategoryChartDataProvider($repository, 2025, 10);
		$provider->processParametersFromRequest([
			'originalRequestdo' => 'setMonth',
			'originalRequestselectedYear' => '2025',
		]);
		$provider->getChartData();
	}

	public function testAjaxTagFilteringUsesTheCurrentPeriodAndDefaultYear(): void
	{
		$repository = $this->createMock(BankExpenseRepository::class);
		$repository->expects(self::once())->method('findForOverview')->with(2026, 9)->willReturn([]);
		$provider = new ExpenseOverviewCategoryChartDataProvider($repository, 2025, 10);
		$provider->processParametersFromRequest([
			'originalRequestdo' => 'setOtherTag',
			'originalRequestselectedOtherTagId' => '123',
			'originalRequestselectedMonth' => '9',
		]);
		$provider->getChartData();
	}

}
