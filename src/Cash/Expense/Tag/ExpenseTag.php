<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('expense_tag')]
class ExpenseTag implements Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\ManyToOne(targetEntity: ExpenseCategory::class, inversedBy: 'expenseTags')]
	#[ORM\JoinColumn(nullable: true)]
	private ExpenseCategory|null $expenseCategory;

	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childrenTags')]
	#[ORM\JoinColumn(nullable: true)]
	private ExpenseTag|null $parentTag;

	/** @var ArrayCollection<int, ExpenseTag> */
	#[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentTag')]
	private Collection $childrenTags;

	/** @var array<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $regexes;

	/** @var ArrayCollection<int, BankExpense> */
	#[ORM\OneToMany(targetEntity: BankExpense::class, mappedBy: 'mainTag')]
	private Collection $mainExpenses;

	/** @var ArrayCollection<int, BankExpense> */
	#[ORM\ManyToMany(targetEntity: BankExpense::class, mappedBy: 'otherTags')]
	private Collection $otherExpenses;

	/**
	 * @param array<string> $regexes
	 */
	public function __construct(
		string $name,
		ExpenseCategory|null $expenseCategory,
		ExpenseTag|null $parentTag,
		array $regexes,
		ImmutableDateTime $now,
	)
	{
		$this->name = $name;
		$this->expenseCategory = $expenseCategory;
		$this->parentTag = $parentTag;
		$this->childrenTags = new ArrayCollection();
		$this->regexes = $regexes;
		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->mainExpenses = new ArrayCollection();
		$this->otherExpenses = new ArrayCollection();
	}

	/**
	 * @param array<string> $regexes
	 */
	public function update(
		string $name,
		array $regexes,
		ImmutableDateTime $now,
	): void
	{
		$this->name = $name;
		$this->regexes = $regexes;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function isMainTag(): bool
	{
		return $this->expenseCategory !== null;
	}

	public function addOtherBankExpense(BankExpense $bankExpense): void
	{
		$this->otherExpenses->add($bankExpense);
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getExpenseCategory(): ExpenseCategory|null
	{
		return $this->expenseCategory;
	}

	public function getParentTag(): ExpenseTag|null
	{
		return $this->parentTag;
	}

	/**
	 * @return array<ExpenseTag>
	 */
	public function getChildrenTags(): array
	{
		return $this->childrenTags->toArray();
	}

	/**
	 * @return array<string>
	 */
	public function getRegexes(): array
	{
		return $this->regexes;
	}

	/**
	 * @return array<BankExpense>
	 */
	public function getMainExpenses(): array
	{
		return $this->mainExpenses->toArray();
	}

	/**
	 * @return array<BankExpense>
	 */
	public function getOtherExpenses(): array
	{
		return $this->otherExpenses->toArray();
	}

}
