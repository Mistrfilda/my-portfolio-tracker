<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Cash\Income\Bank\BankIncomeRepository;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Base\BaseControl;
use Nette\Application\BadRequestException;

class ExpenseOverviewCategoryControl extends BaseControl
{

	public function __construct(
		private int $year,
		private int|null $month,
		private int|null $selectedOtherTagId,
		private ExpenseTagRepository $expenseTagRepository,
		private BankExpenseRepository $bankExpenseRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private BankIncomeRepository $bankIncomeRepository,
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private BankAccountRepository $bankAccountRepository,
	)
	{
	}

	public function render(): void
	{
		$selectedOtherTag = null;
		if ($this->selectedOtherTagId !== null) {
			$selectedOtherTag = $this->expenseTagRepository->findByIds([$this->selectedOtherTagId])[0] ?? null;
			if ($selectedOtherTag === null || $selectedOtherTag->isMainTag()) {
				throw new BadRequestException('Additional expense tag not found');
			}
		}

		$expenses = $this->bankExpenseRepository->findForOverview($this->year, $this->month);
		$dataSet = new ExpenseOverviewDataSet($expenses, $selectedOtherTag);

		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$investmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$excludedInvestmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$totalIncomeSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$totalWorkIncome = new SummaryPrice(CurrencyEnum::CZK);
		$totalDividendIncome = new SummaryPrice(CurrencyEnum::CZK);

		$bankAccountSummaryPrices = [];
		foreach ($this->bankAccountRepository->findAll() as $bankAccount) {
			$bankAccountSummaryPrices[$bankAccount->getId()->toString()] = new ExpenseOverviewBankAccountSummary(
				$bankAccount,
				new SummaryPrice(CurrencyEnum::CZK),
			);
		}

		foreach ($expenses as $expense) {
			if ($expense->getMainTag()?->getExpenseCategory()?->getExpenseCategoryEnum() === ExpenseCategoryEnum::INVESTMENT) {
				$investmentSummaryPrice->addBankExpense($expense);
			} else {
				$excludedInvestmentSummaryPrice->addBankExpense($expense);
			}

			$totalSummaryPrice->addBankExpense($expense);
			$bankAccountSummaryPrices[$expense->getBankAccount()->getId()->toString()]->getSummaryPrice()->addBankExpense(
				$expense,
			);
		}

		foreach ($this->bankIncomeRepository->findByYearAndMonthAndBankAccountType(
			$this->year,
			$this->month,
			BankAccountTypeEnum::PERSONAL,
		) as $bankIncome) {
			$totalIncomeSummaryPrice->addBankIncome($bankIncome);
		}

		foreach ($this->workMonthlyIncomeRepository->findByYearAndMonth(
			$this->year,
			$this->month,
		) as $workMonthlyIncome) {
			$totalWorkIncome->addWorkMonthlyIncome($workMonthlyIncome);

		}

		foreach ($this->stockAssetDividendRecordRepository->findByYearAndMonth(
			$this->year,
			$this->month,
		) as $stockAssetDividendRecord) {
			$recordPrice = $stockAssetDividendRecord->getSummaryPrice();
			if ($recordPrice->getCurrency() !== $totalDividendIncome->getCurrency()) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$stockAssetDividendRecord->getStockAssetDividend()->getExDate(),
				);
			}

			$totalDividendIncome->addSummaryPrice($recordPrice);
		}

		$template = $this->createTemplate(ExpenseOverviewCategoryControlTemplate::class);
		assert($template instanceof ExpenseOverviewCategoryControlTemplate);
		$template->otherTagSummaries = $dataSet->otherTags;
		$template->selectedOtherTag = $selectedOtherTag;
		$template->selectedSummaryPrice = $dataSet->selectedSummaryPrice;
		$template->totalSummaryPrice = $totalSummaryPrice;
		$template->investmentSummaryPrice = $investmentSummaryPrice;
		$template->excludedInvestmentSummaryPrice = $excludedInvestmentSummaryPrice;
		$template->totalIncomeSummaryPrice = $totalIncomeSummaryPrice;
		$template->totalWorkIncome = $totalWorkIncome;
		$template->totalDividendIncome = $totalDividendIncome;
		$template->data = $dataSet->groups;
		$template->bankAccountSummaryPrices = $bankAccountSummaryPrices;
		$template->setFile(str_replace('.php', '.latte', __FILE__));
		$template->render();
	}

	public function redrawDetailTables(): void
	{
		$this->redrawControl('expenseOverviewArea');
		$this->redrawControl('bankExpenses-0');
		$this->redrawControl('tagSummary');
		foreach ($this->expenseCategoryRepository->findAll() as $expenseCategory) {
			$this->redrawControl('bankExpenses-' . $expenseCategory->getId());
		}
	}

}
