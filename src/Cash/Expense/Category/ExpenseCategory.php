<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Category;

use App\Cash\Expense\Tag\ExpenseTag;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('expense_category')]
class ExpenseCategory implements Entity
{

	use Identifier;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: ExpenseCategoryEnum::class)]
	private ExpenseCategoryEnum $enumName;

	/** @var ArrayCollection<int, ExpenseTag> */
	#[ORM\OneToMany(targetEntity: ExpenseTag::class, mappedBy: 'expenseCategory')]
	private Collection $expenseTags;

	public function __construct(string $name, ExpenseCategoryEnum $enumName, ImmutableDateTime $now)
	{
		$this->name = $name;
		$this->enumName = $enumName;
		$this->createdAt = $now;

		$this->expenseTags = new ArrayCollection();
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getEnumName(): ExpenseCategoryEnum
	{
		return $this->enumName;
	}

	/**
	 * @return array<ExpenseTag>
	 */
	public function getExpenseTags(): array
	{
		return $this->expenseTags->toArray();
	}

}
