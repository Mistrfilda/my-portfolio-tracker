<?php

declare(strict_types = 1);

namespace App\Tax\UI;

use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use function assert;

class TaxForecastFormFactory
{

	public function __construct(private AdminFormFactory $adminFormFactory,)
	{
	}

	/**
	 * @param callable(int): void $onPreview
	 */
	public function create(int|null $initialAnnualIncomeCzk, callable $onPreview): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addInteger('annualIncomeCzk', 'Kolik celkem očekávám za práci za celý rok (Kč)?')
			->setRequired('Zadejte očekávaný roční příjem z práce v Kč.')
			->addRule(Form::Min, 'Příjem nemůže být záporný.', 0)
			->setOption(
				'description',
				'Celkový příjem § 7 v daném roce před 60% paušálem, včetně dosud přijatých plateb.',
			);

		$form->addSubmit('submit', 'Přepočítat scénář');

		if ($initialAnnualIncomeCzk !== null) {
			$form->setDefaults([
				'annualIncomeCzk' => $initialAnnualIncomeCzk,
			]);
		}

		$form->onSuccess[] = static function (AdminForm $form) use ($onPreview): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$onPreview(TypeValidator::validateInt($values->annualIncomeCzk));
		};

		return $form;
	}

}
