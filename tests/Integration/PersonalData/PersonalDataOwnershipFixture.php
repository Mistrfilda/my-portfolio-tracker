<?php

declare(strict_types = 1);

namespace App\Test\Integration\PersonalData;

use App\Admin\AppAdmin;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Income\Bank\BankIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Currency\CurrencyEnum;
use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceType;
use App\Home\Device\Record\HomeDeviceRecord;
use App\Home\Device\Record\HomeDeviceRecordUnit;
use App\Home\Home;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class PersonalDataOwnershipFixture
{

	/** @var array<AppAdmin> */
	public array $admins = [];

	/** @var array<BankAccount> */
	public array $accounts = [];

	/** @var array<BankExpense> */
	public array $expenses = [];

	/** @var array<BankIncome> */
	public array $incomes = [];

	/** @var array<ExpenseTag> */
	public array $tags = [];

	/** @var array<ExpenseTag> */
	public array $childTags = [];

	/** @var array<WorkMonthlyIncome> */
	public array $workIncomes = [];

	/** @var array<Home> */
	public array $homes = [];

	/** @var array<HomeDevice> */
	public array $devices = [];

	/** @var array<HomeDeviceRecord> */
	public array $records = [];

	public ExpenseCategory $category;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$now = new ImmutableDateTime('2026-01-15');
		$suffix = Uuid::uuid4()->toString();
		$this->category = new ExpenseCategory('Ownership category ' . $suffix, ExpenseCategoryEnum::RESTAURANT, $now);
		$entityManager->persist($this->category);
		for ($i = 0; $i < 3; $i++) {
			$username = 'personal-' . $i . '-' . $suffix;
			$admin = new AppAdmin($username, $username, $username . '@example.test', 'unused', $now, false, $i === 0);
			$this->admins[] = $admin;
			$entityManager->persist($admin);
			if ($i === 2) {
				continue;
			}

			$account = new BankAccount($admin, 'Account ' . $i, 'Test bank', BankAccountTypeEnum::PERSONAL);
			$tag = new ExpenseTag($admin, 'Main ' . $i, $this->category, null, ['ownership purchase'], $now, false);
			$childTag = new ExpenseTag($admin, 'Child ' . $i, null, $tag, ['ownership purchase'], $now, false);
			$expense = new BankExpense(
				'same-expense-' . $suffix,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::CARD_PAYMENT,
				100.0 + $i,
				CurrencyEnum::CZK,
				$now,
				$now,
				'ownership purchase',
				$now,
				$account,
			);
			$income = new BankIncome(
				'same-income-' . $suffix,
				BankSourceEnum::KOMERCNI_BANKA,
				BankTransactionType::CARD_PAYMENT,
				200.0 + $i,
				CurrencyEnum::CZK,
				$now,
				'ownership income',
				$now,
				$account,
			);
			$workIncome = new WorkMonthlyIncome($admin, 2026, 1, 10.0 + $i, 500, $now);
			$home = new Home($admin, 'Home ' . $i, $now);
			$device = new HomeDevice(
				$home,
				'device-' . $i . '-' . $suffix,
				'Device ' . $i,
				HomeDeviceType::TEMPERATURE,
				$now,
			);
			$record = new HomeDeviceRecord($device, null, null, 20.0 + $i, null, HomeDeviceRecordUnit::CELSIUS, $now);
			foreach ([$account, $tag, $childTag, $expense, $income, $workIncome, $home, $device, $record] as $entity) {
				$entityManager->persist($entity);
			}

			$this->accounts[] = $account;
			$this->tags[] = $tag;
			$this->childTags[] = $childTag;
			$this->expenses[] = $expense;
			$this->incomes[] = $income;
			$this->workIncomes[] = $workIncome;
			$this->homes[] = $home;
			$this->devices[] = $device;
			$this->records[] = $record;
		}

		$entityManager->flush();
	}

}
