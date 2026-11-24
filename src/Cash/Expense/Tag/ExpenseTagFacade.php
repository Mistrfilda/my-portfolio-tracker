<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Tax\Payment\TaxPaymentRoleEnum;
use App\Tax\TaxYearReportFacade;
use App\Utils\Console\ConsoleCurrentOutputHelper;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpenseTagFacade
{

	public function __construct(
		private ExpenseTagRepository $expenseTagRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private BankExpenseRepository $bankExpenseRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private ConsoleCurrentOutputHelper $consoleCurrentOutputHelper,
		private SystemValueFacade $systemValueFacade,
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private AppAdminRepository $appAdminRepository,
		private TaxYearReportFacade|null $taxYearReportFacade = null,
	)
	{

	}

	/**
	 * @param array<string> $regexes
	 */
	public function create(
		string $name,
		int|null $expenseCategory,
		int|null $parentTag,
		array $regexes,
		bool $isTax,
		TaxPaymentRoleEnum|null $taxPaymentRole = null,
	): ExpenseTag
	{
		if ($expenseCategory !== null && $parentTag !== null) {
			throw new ExpenseTagException('A tag cannot have both a category and a parent tag');
		}

		$now = $this->datetimeFactory->createNow();
		$expenseCategoryEntity = null;
		$parentTagEntity = null;

		if ($expenseCategory !== null) {
			$expenseCategoryEntity = $this->expenseCategoryRepository->getById($expenseCategory);
		}

		if ($parentTag !== null) {
			$parentTagEntity = $this->expenseTagRepository->getById($parentTag);
		}

		$expenseTag = new ExpenseTag(
			$this->currentAppAdminGetter->getAppAdmin(),
			$name,
			$expenseCategoryEntity,
			$parentTagEntity,
			$regexes,
			$now,
			$isTax,
			$taxPaymentRole,
		);
		$this->entityManager->persist($expenseTag);
		$this->entityManager->flush();
		$this->entityManager->refresh($expenseTag);

		if ($taxPaymentRole !== null) {
			$this->taxYearReportFacade?->enqueueOpenYearRefresh($now->getYear(), $expenseTag->getAppAdmin());
		}

		return $expenseTag;
	}

	/**
	 * @param array<string> $regexes
	 */
	public function update(
		int $id,
		string $name,
		array $regexes,
		bool $isTax,
		TaxPaymentRoleEnum|null $taxPaymentRole = null,
	): ExpenseTag
	{
		$expenseTag = $this->expenseTagRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $expenseTag->getTaxPaymentRole() : null;
		$now = $this->datetimeFactory->createNow();
		$expenseTag->update($name, $regexes, $now, $isTax, $taxPaymentRole);
		$this->entityManager->flush();

		if ($this->taxYearReportFacade !== null && ($previousRole !== null || $taxPaymentRole !== null)) {
			$this->taxYearReportFacade->enqueueOpenYearRefresh($now->getYear(), $expenseTag->getAppAdmin());
		}

		return $expenseTag;
	}

	public function processExpenses(OutputInterface|null $output = null): void
	{
		foreach ($this->appAdminRepository->findAll() as $appAdmin) {
			$this->processExpensesForAppAdmin($appAdmin, $output);
		}
	}

	public function processExpensesForAppAdminId(UuidInterface $appAdminId): void
	{
		$this->processExpensesForAppAdmin($this->appAdminRepository->findById($appAdminId));
	}

	public function processExpensesForAppAdmin(AppAdmin $appAdmin, OutputInterface|null $output = null): void
	{
		if ($output === null) {
			$output = $this->consoleCurrentOutputHelper->getOutput();
		}

		$output?->writeln('Processing expenses...');
		$expenseTags = $this->expenseTagRepository->findAllForAppAdmin($appAdmin);
		$affectedTaxYears = [];
		foreach ($this->bankExpenseRepository->findAllForAppAdmin($appAdmin) as $bankExpense) {
			//remove new lines
			$bankExpenseRawContent = str_replace("\n", ' ', $bankExpense->getTransactionRawContent());
			//remove tabs
			$bankExpenseRawContent = str_replace("\t", ' ', $bankExpenseRawContent);
			//remove extensive spaces
			$bankExpenseRawContent = preg_replace('/\s+/', ' ', $bankExpenseRawContent);
			assert(is_string($bankExpenseRawContent));

			foreach ($expenseTags as $expenseTag) {
				$matched = false;
				foreach ($expenseTag->getRegexes() as $regex) {
					$pattern = sprintf('~.*%s.*~', preg_quote($regex, '~'));
					if (preg_match($pattern, $bankExpenseRawContent) === 1) {
						$matched = true;
						break;
					}
				}

				if ($matched) {
					$changed = false;
					if ($expenseTag->isMainTag()) {
						if ($bankExpense->isMainTagSetManually()) {
							continue;
						}

						if (
							$bankExpense->getMainTag() !== null
							&& $bankExpense->getMainTag()->getId() !== $expenseTag->getId()
						) {
							throw new ExpenseTagMatchException(
								sprintf(
									'Duplicate main bank expense tag for bank expense %s, new expense tag id %s',
									$bankExpense->getId()->toString(),
									$expenseTag->getId(),
								),
							);
						}

						if ($bankExpense->getMainTag() === null) {
							$changed = true;
						}

						$bankExpense->setMainTag($expenseTag);
					} elseif ($bankExpense->isOtherTagSetManually() === false) {
						if (!in_array($expenseTag, $bankExpense->getOtherTags(), true)) {
							$changed = true;
						}

						$bankExpense->addOtherTag($expenseTag);
					}

					$this->entityManager->flush();

					if (
						$this->taxYearReportFacade !== null
						&& $changed
						&& $bankExpense->resolveTaxPaymentRole() !== null
					) {
						foreach ($this->resolveExpenseAffectedTaxYears($bankExpense) as $affectedYear) {
							$affectedTaxYears[$affectedYear] = true;
						}
					}
				}
			}
		}

		if ($this->taxYearReportFacade !== null) {
			foreach (array_keys($affectedTaxYears) as $taxYear) {
				$this->taxYearReportFacade->enqueueOpenYearRefresh($taxYear, $appAdmin);
			}
		}

		$output?->writeln('Done');
		$this->systemValueFacade->updateValue(
			SystemValueEnum::EXPENSE_TAGS_PROCESSED_AT,
			$this->datetimeFactory->createNow(),
		);
	}

	public function manuallySetMainTag(UuidInterface $id, int $tagId): void
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $bankExpense->resolveTaxPaymentRole() : null;
		$bankExpense->setManuallyMainTag(
			$this->expenseTagRepository->getById($tagId),
		);
		$this->entityManager->flush();

		if (
			$this->taxYearReportFacade !== null
			&& (
				$previousRole !== null
				|| $bankExpense->resolveTaxPaymentRole() !== null
			)
		) {
			$this->enqueueTaxRefreshForExpense($bankExpense);
		}
	}

	public function manuallySetOtherTag(UuidInterface $id, int $tagId): void
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $bankExpense->resolveTaxPaymentRole() : null;
		$bankExpense->addManuallyOtherTag(
			$this->expenseTagRepository->getById($tagId),
		);
		$this->entityManager->flush();

		if (
			$this->taxYearReportFacade !== null
			&& (
				$previousRole !== null
				|| $bankExpense->resolveTaxPaymentRole() !== null
			)
		) {
			$this->enqueueTaxRefreshForExpense($bankExpense);
		}
	}

	public function manuallyRemoveOtherTag(UuidInterface $id, int $tagId): void
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $bankExpense->resolveTaxPaymentRole() : null;
		$bankExpense->manuallyRemoveOtherTag(
			$this->expenseTagRepository->getById($tagId),
		);
		$this->entityManager->flush();

		if (
			$this->taxYearReportFacade !== null
			&& (
				$previousRole !== null
				|| $bankExpense->resolveTaxPaymentRole() !== null
			)
		) {
			$this->enqueueTaxRefreshForExpense($bankExpense);
		}
	}

	private function enqueueTaxRefreshForExpense(BankExpense $bankExpense): void
	{
		if ($this->taxYearReportFacade === null) {
			return;
		}

		$owner = $bankExpense->getBankAccount()->getAppAdmin();
		foreach ($this->resolveExpenseAffectedTaxYears($bankExpense) as $taxYear) {
			$this->taxYearReportFacade->enqueueOpenYearRefresh($taxYear, $owner);
		}
	}

	/**
	 * @return list<int>
	 */
	private function resolveExpenseAffectedTaxYears(BankExpense $bankExpense): array
	{
		$date = $bankExpense->getDate();
		$years = [
			$bankExpense->getTaxYearOverride() ?? $date->getYear(),
		];

		if ($bankExpense->getTaxYearOverride() === null && $date->getMonth() === 1 && $date->getDay() <= 10) {
			$years[] = $date->getYear() - 1;
		}

		return array_values(array_unique($years));
	}

}
