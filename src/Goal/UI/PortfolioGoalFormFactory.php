<?php

declare(strict_types = 1);

namespace App\Goal\UI;

use App\Goal\PortfolioGoalFacade;
use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalTypeEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Ramsey\Uuid\UuidInterface;

class PortfolioGoalFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private PortfolioGoalRepository $portfolioGoalRepository,
		private PortfolioGoalFacade $portfolioGoalFacade,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addDatePicker('startDate', 'Začátek')->setRequired();
		$form->addDatePicker('endDate', 'Konec')->setRequired();
		$type = $form->addSelect('type', 'Typ', [
			PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT->value => PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT->format(),
			PortfolioGoalTypeEnum::TOTAL_INCOME->value => PortfolioGoalTypeEnum::TOTAL_INCOME->format(),
		])->setRequired()->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		$form->addFloat('goal', 'Cílová částka')->setRequired();

		$form->addSubmit('submit', 'Odeslat');

		if ($id !== null) {
			$portfolioGoal = $this->portfolioGoalRepository->getById($id);
			$form->setDefaults([
				'startDate' => $portfolioGoal->getStartDate(),
				'endDate' => $portfolioGoal->getEndDate(),
				'type' => $portfolioGoal->getType()->value,
				'goal' => $portfolioGoal->getGoal(),
			]);

			$type->setDisabled();
		}

		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues();
			if ($id !== null) {
				$this->portfolioGoalFacade->update(
					$id,
					TypeValidator::validateImmutableDatetime($values->startDate),
					TypeValidator::validateImmutableDatetime($values->endDate),
					TypeValidator::validateFloat($values->goal),
				);
			} else {
				$this->portfolioGoalFacade->create(
					TypeValidator::validateImmutableDatetime($values->startDate),
					TypeValidator::validateImmutableDatetime($values->endDate),
					PortfolioGoalTypeEnum::from(TypeValidator::validateString($values->type)),
					TypeValidator::validateFloat($values->goal),
				);
			}

			$onSuccess();
		};

		return $form;
	}

}
