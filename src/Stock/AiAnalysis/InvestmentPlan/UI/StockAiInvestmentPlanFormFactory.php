<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\UI;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanFacade;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\Uuid;
use Throwable;
use function assert;

class StockAiInvestmentPlanFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAiInvestmentPlanFacade $investmentPlanFacade,
		private readonly StockAiAnalysisRunRepository $stockAiAnalysisRunRepository,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->setHeading(
			'Nový plán pro volnou hotovost',
			'Částka se přepočítá do CZK a porovná s aktuální hodnotou otevřených akciových pozic.',
		);
		$referenceOptions = $this->createReferenceOptions();

		$referenceSelect = $form->addSelect(
			'referenceAnalysisRun',
			'Referenční komplexní analýza',
			$referenceOptions,
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired('Vyberte dokončenou komplexní analýzu portfolia.');
		if ($referenceOptions !== []) {
			$referenceSelect->setDefaultValue(array_key_first($referenceOptions));
		}

		$form->addText('requestedAmount', 'Částka k investování')
			->setHtmlAttribute('placeholder', 'např. 40000')
			->addRule(Form::Float, 'Částka musí být číslo.')
			->addRule(Form::Min, 'Částka musí být větší než nula.', 0.01)
			->setRequired('Zadejte částku k investování.');

		$form->addSelect('requestedCurrency', 'Měna částky', CurrencyEnum::getOptionsForAdminSelect())
			->setDefaultValue(CurrencyEnum::CZK->value)
			->setRequired('Vyberte měnu částky.');

		$form->addTextArea('additionalInstructions', 'Vlastní doplnění promptu (volitelné)')
			->setHtmlAttribute('rows', 4)
			->setHtmlAttribute(
				'placeholder',
				'Např. upřednostni stabilitu dividendy a neinvestuj do tabákových společností.',
			);

		$form->addTextArea('consideredCompanies', 'Další zvažované společnosti (volitelné)')
			->setHtmlAttribute('rows', 4)
			->setHtmlAttribute('placeholder', "Jedna společnost na řádek, např.\nRealty Income (O)\nVisa (V)");

		$form->addSubmit('submit', 'Vytvořit investiční plán');

		$form->onSuccess[] = function (Form $form) use ($onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			try {
				$plan = $this->investmentPlanFacade->create(
					TypeValidator::validateFloat($values->requestedAmount),
					CurrencyEnum::from(TypeValidator::validateString($values->requestedCurrency)),
					Uuid::fromString(TypeValidator::validateString($values->referenceAnalysisRun)),
					TypeValidator::validateString($values->additionalInstructions),
					TypeValidator::validateString($values->consideredCompanies),
				);
			} catch (Throwable $exception) {
				$form->addError($exception->getMessage());
				return;
			}

			$onSuccess($plan);
		};

		return $form;
	}

	/** @return array<string, string> */
	private function createReferenceOptions(): array
	{
		$options = [];
		foreach ($this->stockAiAnalysisRunRepository->findCompletedPortfolioEvaluations() as $run) {
			$options[$run->getId()->toString()] = sprintf(
				'%s · %s',
				$run->getProcessedAt()?->format('d. m. Y H:i') ?? $run->getCreatedAt()->format('d. m. Y H:i'),
				$run->getId()->toString(),
			);
		}

		return $options;
	}

}
