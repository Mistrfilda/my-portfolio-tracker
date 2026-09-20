<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Application\UI\Form;

class StockAiAnalysisSettingsFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAiAnalysisSettingsFacade $settingsFacade,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addTextArea('investorInstructions', 'Společný investiční prompt', null, 8)
			->addRule(Form::MaxLength, 'Prompt může mít nejvýše 20 000 znaků.', 20_000)
			->setDefaultValue($this->settingsFacade->getInvestorInstructions());
		$form->addSubmit('submit', 'Uložit společný prompt');
		$form->onSuccess[] = function (Form $form) use ($onSuccess): void {
			$this->settingsFacade->saveInvestorInstructions(
				TypeValidator::validateString($form->getValues()->investorInstructions),
			);
			$onSuccess();
		};

		return $form;
	}

}
