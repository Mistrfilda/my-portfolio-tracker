<?php

declare(strict_types = 1);

namespace App\FinancialIndependence\UI;

use App\FinancialIndependence\FinancialIndependenceCalculationInput;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;

final readonly class FinancialIndependenceFormFactory
{

	public function __construct(private AdminFormFactory $adminFormFactory)
	{
	}

	/** @param callable(FinancialIndependenceCalculationInput): void $onSuccess */
	public function create(
		FinancialIndependenceCalculationInput $defaults,
		int $currentYear,
		callable $onSuccess,
	): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addFloat('targetMonthlyExpensesCzk', 'Cílové měsíční náklady')
			->setHtmlAttribute('step', '1')
			->setRequired('Zadejte cílové měsíční náklady.')
			->addRule(Form::Min, 'Cílové měsíční náklady musí být alespoň %d CZK.', 1);
		$form->addFloat('currentPortfolioValueCzk', 'Aktuální hodnota portfolia')
			->setHtmlAttribute('step', '1')
			->setRequired('Zadejte aktuální hodnotu portfolia.')
			->addRule(Form::Min, 'Hodnota portfolia nesmí být záporná.', 0);
		$form->addFloat('monthlyContributionCzk', 'Měsíční příspěvek do portfolia')
			->setHtmlAttribute('step', '1')
			->setRequired('Zadejte měsíční příspěvek do portfolia.')
			->addRule(Form::Min, 'Měsíční příspěvek nesmí být záporný.', 0);
		$form->addInteger('birthYear', 'Rok narození')
			->setHtmlAttribute('min', '1900')
			->setHtmlAttribute('max', (string) $currentYear)
			->setRequired('Zadejte rok narození.')
			->addRule(Form::Range, 'Rok narození musí být mezi %d a %d.', [1900, $currentYear]);
		$form->addInteger('plannedLifespanAge', 'Modelovaný věk dožití')
			->setHtmlAttribute('min', '1')
			->setHtmlAttribute('max', '100')
			->setRequired('Zadejte modelovaný věk dožití.')
			->addRule(Form::Range, 'Modelovaný věk dožití musí být mezi %d a %d lety.', [1, 100]);
		$form->addFloat(
			'annualNominalReturnPercentage',
			'Očekávaný celkový roční výnos',
		)
			->setHtmlAttribute('step', 'any')
			->setRequired(
				'Zadejte očekávaný nominální celkový roční výnos včetně čistých dividend před náklady.',
			)
			->addRule(Form::Range, 'Očekávaný nominální celkový výnos musí být mezi %d a %d %%.', [-50, 100]);
		$form->addFloat('annualInflationPercentage', 'Roční inflace')
			->setHtmlAttribute('step', '0.1')
			->setRequired('Zadejte roční inflaci.')
			->addRule(Form::Range, 'Inflace musí být mezi %d a %d %%.', [-50, 100]);
		$form->addFloat('annualCostPercentage', 'Roční náklady portfolia')
			->setHtmlAttribute('step', '0.1')
			->setRequired('Zadejte roční náklady portfolia.')
			->addRule(Form::Range, 'Roční náklady musí být mezi %d a %d %%.', [0, 20]);
		$form->addFloat('withdrawalRatePercentage', 'Roční míra výběru')
			->setHtmlAttribute('step', '0.1')
			->setRequired('Zadejte roční míru výběru.')
			->addRule(Form::Range, 'Míra výběru musí být mezi %.1f a %d %%.', [0.1, 20]);
		$form->addSubmit('submit', 'Přepočítat scénář');

		$form->setDefaults([
			'targetMonthlyExpensesCzk' => round($defaults->targetMonthlyExpensesCzk),
			'currentPortfolioValueCzk' => round($defaults->currentPortfolioValueCzk),
			'monthlyContributionCzk' => round($defaults->monthlyContributionCzk),
			'birthYear' => $defaults->birthYear,
			'plannedLifespanAge' => $defaults->plannedLifespanAge,
			'annualNominalReturnPercentage' => $defaults->annualNominalReturnPercentage,
			'annualInflationPercentage' => $defaults->annualInflationPercentage,
			'annualCostPercentage' => $defaults->annualCostPercentage,
			'withdrawalRatePercentage' => $defaults->withdrawalRatePercentage,
		]);

		$form->onValidate[] = static function (AdminForm $form) use ($currentYear): void {
			if ($form->hasErrors()) {
				return;
			}

			$values = $form->getValues();
			$birthYear = TypeValidator::validateInt($values->birthYear);
			$plannedLifespanAge = TypeValidator::validateInt($values->plannedLifespanAge);
			if ($birthYear + $plannedLifespanAge < $currentYear) {
				$form['plannedLifespanAge']->addError(
					'Modelovaný věk dožití musí sahat alespoň do aktuálního roku.',
				);
			}
		};

		$form->onSuccess[] = static function (AdminForm $form) use ($onSuccess): void {
			$values = $form->getValues();
			$onSuccess(new FinancialIndependenceCalculationInput(
				TypeValidator::validateFloat($values->currentPortfolioValueCzk),
				TypeValidator::validateFloat($values->targetMonthlyExpensesCzk),
				TypeValidator::validateFloat($values->monthlyContributionCzk),
				TypeValidator::validateFloat($values->annualNominalReturnPercentage),
				TypeValidator::validateFloat($values->annualInflationPercentage),
				TypeValidator::validateFloat($values->annualCostPercentage),
				TypeValidator::validateFloat($values->withdrawalRatePercentage),
				TypeValidator::validateInt($values->birthYear),
				TypeValidator::validateInt($values->plannedLifespanAge),
			));
		};

		return $form;
	}

}
