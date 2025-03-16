<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag\UI;

use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
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
			->setPrompt('Vyberte')
			->setOption('tomSelect', true);

		$parentTag = $form->addSelect('parentTag', 'Parent Tag', $this->expenseTagRepository->findPairs())
			->setPrompt('Vyberte')
			->setOption('tomSelect', true);

		$form->addCheckbox('isTax', 'Je tag daň?');

		$multiplier = $form->addDynamic(
			'regexes',
			'Regexy',
			static function (Container $container, AdminForm $form): void {
				$container->addText('regex', 'Regex')
					->setNullable()
					->setRequired(false);
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
				if ($regex !== '') {
					$regexes[] = ['regex' => $regex];
				}
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
			foreach (TypeValidator::validateIterable($values->regexes) as $regex) {
				assert($regex instanceof ArrayHash);
				if ($regex->regex !== null && $regex->regex !== '') {
					$regexes[] = TypeValidator::validateString($regex->regex);
				}
			}

			if ($id === null) {
				$this->expenseTagFacade->create(
					TypeValidator::validateString($values->name),
					TypeValidator::validateNullableInt($values->expenseCategory),
					TypeValidator::validateNullableInt($values->parentTag),
					$regexes,
					TypeValidator::validateBool($values->isTax),
				);
			} else {
				$this->expenseTagFacade->update(
					$id,
					TypeValidator::validateString($values->name),
					$regexes,
					TypeValidator::validateBool($values->isTax),
				);
			}

			$onSuccess();
		};

		return $form;
	}

}
