<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag\UI;

use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Container;
use Nette\Utils\ArrayHash;
use function assert;

class ExpenseTagFormFactory
{

	public function __construct(
		private ExpenseTagRepository $expenseTagRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private ExpenseTagFacade $expenseTagFacade,
		private AdminFormFactory $formFactory,
	)
	{
	}

	public function create(int|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název')->setRequired();

		$expenseCategory = $form->addSelect(
			'expenseCategory',
			'Kategorie',
			$this->expenseCategoryRepository->findPairs(),
		)
			->setPrompt('Vyberte');

		$parentTag = $form->addSelect('parentTag', 'Parent Tag', $this->expenseTagRepository->findPairs())
			->setPrompt('Vyberte');

		$multiplier = $form->addDynamic(
			'regexes',
			'Regexy',
			static function (Container $container, AdminForm $form): void {
				$container->addText('regex', 'Regex')
					->setRequired();
			},
			1,
			20,
		);

		$multiplier->setDivId('regexes');

		$multiplier->addCreateButton('Přidat regex')
			->addClass('btn btn-primary');
		$multiplier->addRemoveButton('Odstranit regex')
			->addClass('btn btn-danger');

		$form->addSubmit('submit', 'Submit');

		if ($id !== null) {
			$parentTag->setDisabled();
			$expenseCategory->setDisabled();
			$expenseTag = $this->expenseTagRepository->getById($id);

			$regexes = [];
			foreach ($expenseTag->getRegexes() as $regex) {
				$regexes[]['regex'] = $regex;
			}

			$form->setDefaults([
				'name' => $expenseTag->getName(),
				'expenseCategory' => $expenseTag->getExpenseCategory()?->getId(),
				'parentTag' => $expenseTag->getParentTag()?->getId(),
				'regexes' => $regexes,
			]);
		}

		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);
			if ($id === null && $values->expenseCategory === null && $values->parentTag === null) {
				$form['expenseCategory']->addError('Vyberte kategorii nebo parent tag');
				$form['parentTag']->addError('Vyberte kategorii nebo parent tag');
				return;
			}

			if ($id === null && $values->expenseCategory !== null && $values->parentTag !== null) {
				$form['expenseCategory']->addError('Vyberte pouze kategorii nebo parent tag');
				$form['parentTag']->addError('Vyberte pouze kategorii nebo parent tag');
				return;
			}

			$regexes = [];
			foreach ($values->regexes as $regex) {
				$regexes[] = $regex->regex;
			}

			if ($id === null) {
				$this->expenseTagFacade->create(
					$values->name,
					$values->expenseCategory,
					$values->parentTag,
					$regexes,
				);
			} else {
				$this->expenseTagFacade->update($id, $values->name, $regexes);
			}

			$onSuccess();
		};

		return $form;
	}

}
