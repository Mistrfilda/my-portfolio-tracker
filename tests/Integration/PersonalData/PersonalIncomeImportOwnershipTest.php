<?php

declare(strict_types = 1);

namespace App\Test\Integration\PersonalData;

use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Bank\Kb\KbCashFacade;
use App\Cash\Bank\Kb\KbContentParserResult;
use App\Cash\Bank\Kb\KbCsvCashParser;
use App\Cash\Bank\Kb\KbPdfCashParser;
use App\Cash\Bank\Kb\KbSourceEnum;
use App\Cash\Bank\Kb\KbTransaction;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Income\Bank\BankIncomeRepository;
use App\Cash\Income\WorkMonthlyIncome\HarvestTimeDownloader;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeFacade;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\NullLogger;

class PersonalIncomeImportOwnershipTest extends IntegrationTestCase
{

	private PersonalDataOwnershipFixture $fixture;

	private CurrentAppAdminGetter $currentAdmin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixture = new PersonalDataOwnershipFixture($this->getService(EntityManagerInterface::class));
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	#[TestWith([0])]
	#[TestWith([1])]
	public function testHarvestImportUsesConfiguredOwnerWithoutLoginAndDoesNotOverwriteOtherOwner(int $index): void
	{
		$repository = $this->getService(WorkMonthlyIncomeRepository::class);
		$downloader = $this->createStub(HarvestTimeDownloader::class);
		$downloader->method('getData')->willReturn([
			['spent_date' => '2026-01-05', 'hours' => 12.0],
			['spent_date' => '2026-01-06', 'hours' => 15.0],
			['spent_date' => '2026-02-06', 'hours' => 20.0],
		]);
		$owner = $this->fixture->admins[$index];
		$facade = new WorkMonthlyIncomeFacade(
			900,
			$repository,
			$downloader,
			$this->getDatetimeFactory(),
			$this->getService(
				EntityManagerInterface::class,
			),
			new WorkMonthlyIncomeOwnerProvider(
				$this->getService(AppAdminRepository::class),
				$owner->getUsername(),
			),
			$this->currentAdmin,
		);
		$facade->download();
		// A different logged-in account must not redirect the configured import.
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1 - $index]);
		$facade->download();
		self::assertSame(27.0, $this->fixture->workIncomes[$index]->getHours());
		self::assertSame(500, $this->fixture->workIncomes[$index]->getHourlyRate());
		self::assertSame(10.0 + 1 - $index, $this->fixture->workIncomes[1 - $index]->getHours());
		self::assertCount(2, $repository->findAllForAppAdmin($owner, 2026));
		self::assertCount(1, $repository->findAll(2026));
		self::assertSame($this->fixture->workIncomes[1 - $index], $repository->getByYearAndMonth(2026, 1));
		self::assertSame(900, $repository->getByYearAndMonthForAppAdmin(2026, 2, $owner)?->getHourlyRate());
		self::assertNull($repository->getByYearAndMonthForAppAdmin(2026, 2, $this->fixture->admins[1 - $index]));
	}

	public function testBankImportIsIdempotentWithinAccountAndAllowsSameTransactionsInOtherAccounts(): void
	{
		$entityManager = $this->getService(EntityManagerInterface::class);
		$secondOwnAccount = new BankAccount(
			$this->fixture->admins[0],
			'Second own account',
			'Bank',
			BankAccountTypeEnum::PERSONAL,
		);
		$entityManager->persist($secondOwnAccount);
		$entityManager->flush();
		$transaction = new KbTransaction();
		$transaction->setBankTransactionType(BankTransactionType::CARD_PAYMENT);
		$transaction->setAmount(42.0);
		$transaction->setSettlementDate('01.02.2026');
		$transaction->setTransactionDate('01.02.2026');
		$transaction->setTransactionRawContent('Same imported transaction');
		$parsed = $this->createStub(KbContentParserResult::class);
		$parsed->method('getProcessedTransactions')->willReturn([$transaction]);
		$parsed->method('getIncomingTransactions')->willReturn([$transaction]);
		$parsed->method('getUnprocessedTransactions')->willReturn([]);
		$parser = $this->createStub(KbCsvCashParser::class);
		$parser->method('parse')->willReturn($parsed);
		$expenses = $this->getService(BankExpenseRepository::class);
		$incomes = $this->getService(BankIncomeRepository::class);
		$facade = new KbCashFacade(
			$this->createStub(KbPdfCashParser::class),
			$parser,
			$expenses,
			$incomes,
			$this->getDatetimeFactory(),
			$entityManager,
			new NullLogger(),
			$this->getService(BankAccountRepository::class),
		);
		foreach ([$this->fixture->accounts[0], $this->fixture->accounts[1], $secondOwnAccount] as $account) {
			$this->currentAdmin->setApiAppAdmin($account->getAppAdmin());
			self::assertFalse($facade->processFileContents('synthetic', KbSourceEnum::CSV, $account));
			self::assertFalse($facade->processFileContents('synthetic', KbSourceEnum::CSV, $account));
		}

		self::assertCount(3, $expenses->findAllForAppAdmin($this->fixture->admins[0]));
		self::assertCount(2, $expenses->findAllForAppAdmin($this->fixture->admins[1]));
		self::assertCount(3, $incomes->findAllForAppAdmin($this->fixture->admins[0]));
		self::assertCount(2, $incomes->findAllForAppAdmin($this->fixture->admins[1]));
	}

	public function testDatabaseRejectsRepeatedIncomePeriodForSameOwner(): void
	{
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->persist(
			new WorkMonthlyIncome($this->fixture->admins[0], 2026, 1, 100.0, 900, $this->createDatetime()),
		);
		$this->expectException(UniqueConstraintViolationException::class);
		$entityManager->flush();
	}

	public function testDatabaseRejectsRepeatedTransactionWithinSameAccount(): void
	{
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->persist(new BankExpense(
			$this->fixture->expenses[0]->getIdentifier(),
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			20.0,
			CurrencyEnum::CZK,
			null,
			null,
			'',
			$this->createDatetime(),
			$this->fixture->accounts[0],
		));
		$this->expectException(UniqueConstraintViolationException::class);
		$entityManager->flush();
	}

}
