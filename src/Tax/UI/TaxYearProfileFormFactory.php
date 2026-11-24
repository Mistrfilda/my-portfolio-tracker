<?php

declare(strict_types = 1);

namespace App\Tax\UI;

use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use App\Tax\TaxYearProfile;
use App\Tax\TaxYearProfileFacade;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use function assert;

class TaxYearProfileFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private TaxYearProfileFacade $profileFacade,
	)
	{
	}

	public function create(TaxYearProfile $profile, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addSelect(
			'expenseFlatRate',
			'Výdajový paušál (§ 7)',
			TaxExpenseFlatRateEnum::getOptionsForAdminSelect(),
		)->setRequired();

		$form->addInteger('activeMonthsHealth', 'Měsíce výkonu – Zdravotní pojišťovna')
			->setRequired()
			->addRule(Form::Range, 'Zadejte 0 až 12 měsíců.', [0, 12]);

		$form->addInteger('activeMonthsSocial', 'Měsíce výkonu – ČSSZ')
			->setRequired()
			->addRule(Form::Range, 'Zadejte 0 až 12 měsíců.', [0, 12]);

		$form->addSelect(
			'dividendMode',
			'Režim zdanění zahraničních dividend',
			TaxDividendModeEnum::getOptionsForAdminSelect(),
		)->setRequired();

		$form->addCheckbox(
			'includePreviousDecemberWorkIncome',
			'Započítat prosinec předchozího roku (inkaso v lednu) a odsunout letošní prosinec do dalšího roku',
		);

		$form->addInteger('manualBusinessIncomeAdjustmentCzk', 'Ruční úprava příjmu § 7 (Kč, lze i zápornou)')
			->setRequired();

		$form->addText('manualBusinessIncomeAdjustmentNote', 'Důvod ruční úpravy § 7')
			->setNullable()
			->setRequired(false);

		$form->addText('reserveTargetDate', 'Cílové datum připravenosti rezervy (YYYY-MM-DD)')
			->setNullable()
			->setRequired(false);

		$form->addInteger('expectedMonthlyHealthAdvanceCzk', 'Očekávaná měsíční záloha ZP pro zbývající měsíce (Kč)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Záloha nemůže být záporná.', 0);

		$form->addInteger('expectedMonthlySocialAdvanceCzk', 'Očekávaná měsíční záloha ČSSZ pro zbývající měsíce (Kč)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Záloha nemůže být záporná.', 0);

		$form->addInteger('expectedRemainingIncomeTaxAdvancesCzk', 'Očekávané zbývající zálohy DPFO do konce roku (Kč)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Záloha nemůže být záporná.', 0);

		$form->addInteger('confirmedIncomeTaxAdvancesCzk', 'Potvrzený roční součet záloh FÚ (přepíše Expenses)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('confirmedHealthAdvancesCzk', 'Potvrzený roční součet záloh ZP (přepíše Expenses)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('confirmedSocialAdvancesCzk', 'Potvrzený roční součet záloh ČSSZ (přepíše Expenses)')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('pensionAndLifeDeductionCzk', 'Odpočet DPS / DIP / ŽP (Kč)')
			->setRequired()
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('mortgageInterestDeductionCzk', 'Odpočet úroků z hypotéky (Kč)')
			->setRequired()
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('otherDeductionsCzk', 'Ostatní nezdanitelné odpočty (Kč)')
			->setRequired()
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addInteger('otherTaxCreditsCzk', 'Ostatní slevy na dani nad rámec základní 30 840 Kč (Kč)')
			->setRequired()
			->addRule(Form::Min, 'Částka nemůže být záporná.', 0);

		$form->addCheckbox('isClosed', 'Označit daňový rok jako uzavřený (vypne odhad zbývajících měsíců)');

		$form->addSubmit('submit', 'Uložit nastavení a zařadit přepočet');

		$form->setDefaults([
			'expenseFlatRate' => $profile->getExpenseFlatRate()->value,
			'activeMonthsHealth' => $profile->getActiveMonthsHealth(),
			'activeMonthsSocial' => $profile->getActiveMonthsSocial(),
			'dividendMode' => $profile->getDividendMode()->value,
			'includePreviousDecemberWorkIncome' => $profile->isIncludePreviousDecemberWorkIncome(),
			'manualBusinessIncomeAdjustmentCzk' => $profile->getManualBusinessIncomeAdjustmentCzk(),
			'manualBusinessIncomeAdjustmentNote' => $profile->getManualBusinessIncomeAdjustmentNote(),
			'reserveTargetDate' => $profile->getReserveTargetDate(),
			'expectedMonthlyHealthAdvanceCzk' => $profile->getExpectedMonthlyHealthAdvanceCzk(),
			'expectedMonthlySocialAdvanceCzk' => $profile->getExpectedMonthlySocialAdvanceCzk(),
			'expectedRemainingIncomeTaxAdvancesCzk' => $profile->getExpectedRemainingIncomeTaxAdvancesCzk(),
			'confirmedIncomeTaxAdvancesCzk' => $profile->getConfirmedIncomeTaxAdvancesCzk(),
			'confirmedHealthAdvancesCzk' => $profile->getConfirmedHealthAdvancesCzk(),
			'confirmedSocialAdvancesCzk' => $profile->getConfirmedSocialAdvancesCzk(),
			'pensionAndLifeDeductionCzk' => $profile->getPensionAndLifeDeductionCzk(),
			'mortgageInterestDeductionCzk' => $profile->getMortgageInterestDeductionCzk(),
			'otherDeductionsCzk' => $profile->getOtherDeductionsCzk(),
			'otherTaxCreditsCzk' => $profile->getOtherTaxCreditsCzk(),
			'isClosed' => $profile->isClosed(),
		]);

		$year = $profile->getYear();
		$form->onSuccess[] = function (AdminForm $form) use ($year, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$this->profileFacade->updateSettings(
				year: $year,
				expenseFlatRate: TaxExpenseFlatRateEnum::from(TypeValidator::validateString($values->expenseFlatRate)),
				activeMonthsHealth: TypeValidator::validateInt($values->activeMonthsHealth),
				activeMonthsSocial: TypeValidator::validateInt($values->activeMonthsSocial),
				dividendMode: TaxDividendModeEnum::from(TypeValidator::validateString($values->dividendMode)),
				includePreviousDecemberWorkIncome: TypeValidator::validateBool(
					$values->includePreviousDecemberWorkIncome,
				),
				manualBusinessIncomeAdjustmentCzk: TypeValidator::validateInt(
					$values->manualBusinessIncomeAdjustmentCzk,
				),
				manualBusinessIncomeAdjustmentNote: TypeValidator::validateNullableString(
					$values->manualBusinessIncomeAdjustmentNote,
				),
				reserveTargetDate: TypeValidator::validateNullableString($values->reserveTargetDate),
				expectedMonthlyHealthAdvanceCzk: TypeValidator::validateNullableInt(
					$values->expectedMonthlyHealthAdvanceCzk,
				),
				expectedMonthlySocialAdvanceCzk: TypeValidator::validateNullableInt(
					$values->expectedMonthlySocialAdvanceCzk,
				),
				expectedRemainingIncomeTaxAdvancesCzk: TypeValidator::validateNullableInt(
					$values->expectedRemainingIncomeTaxAdvancesCzk,
				),
				confirmedIncomeTaxAdvancesCzk: TypeValidator::validateNullableInt(
					$values->confirmedIncomeTaxAdvancesCzk,
				),
				confirmedHealthAdvancesCzk: TypeValidator::validateNullableInt($values->confirmedHealthAdvancesCzk),
				confirmedSocialAdvancesCzk: TypeValidator::validateNullableInt($values->confirmedSocialAdvancesCzk),
				pensionAndLifeDeductionCzk: TypeValidator::validateInt($values->pensionAndLifeDeductionCzk),
				mortgageInterestDeductionCzk: TypeValidator::validateInt($values->mortgageInterestDeductionCzk),
				otherDeductionsCzk: TypeValidator::validateInt($values->otherDeductionsCzk),
				otherTaxCreditsCzk: TypeValidator::validateInt($values->otherTaxCreditsCzk),
				isClosed: TypeValidator::validateBool($values->isClosed),
			);

			$onSuccess();
		};

		return $form;
	}

}
