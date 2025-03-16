<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Income\Bank\BankIncome;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('bank_acount')]
class BankAccount implements Entity
{

	use SimpleUuid;

	#[ORM\Column(type: 'string')]
	private string $name;

	#[ORM\Column(type: 'string')]
	private string $bank;

	#[ORM\Column(type: 'string', enumType: BankAccountTypeEnum::class)]
	private BankAccountTypeEnum $type;

	/** @var ArrayCollection<int, BankExpense> */
	#[ORM\OneToMany(targetEntity: BankExpense::class, mappedBy: 'bankAccount')]
	private Collection $expenses;

	/** @var ArrayCollection<int, BankIncome> */
	#[ORM\OneToMany(targetEntity: BankIncome::class, mappedBy: 'bankAccount')]
	private Collection $incomes;

	public function __construct(string $name, string $bank, BankAccountTypeEnum $type)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->bank = $bank;
		$this->type = $type;
		$this->expenses = new ArrayCollection();
		$this->incomes = new ArrayCollection();
	}

	public function update(string $name, string $bank, BankAccountTypeEnum $type): void
	{
		$this->name = $name;
		$this->bank = $bank;
		$this->type = $type;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getFormattedName(): string
	{
		return sprintf('%s (%s)', $this->name, $this->bank);
	}

	public function getBank(): string
	{
		return $this->bank;
	}

	public function getType(): BankAccountTypeEnum
	{
		return $this->type;
	}

	/**
	 * @return Collection<int, BankExpense>
	 */
	public function getExpenses(): Collection
	{
		return $this->expenses;
	}

	/**
	 * @return Collection<int, BankIncome>
	 */
	public function getIncomes(): Collection
	{
		return $this->incomes;
	}

}
