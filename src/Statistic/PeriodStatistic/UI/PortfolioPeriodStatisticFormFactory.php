<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticPresetEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;

class PortfolioPeriodStatisticFormFactory
{

	public const CUSTOM_PRESET = 'custom';

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private PortfolioPeriodStatisticFacade $portfolioPeriodStatisticFacade,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$presetOptions = [];
		foreach (PortfolioPeriodStatisticPresetEnum::cases() as $preset) {
			$presetOptions[$preset->value] = $preset->format();
		}

		$presetOptions[self::CUSTOM_PRESET] = 'Vlastní období';

		$form->addSelect('preset', 'Období', $presetOptions)
			->setDefaultValue(PortfolioPeriodStatisticPresetEnum::THIRTY_DAYS->value)
			->setRequired();

		$now = $this->datetimeFactory->createNow();
		$form->addDatePicker('startDate', 'Od')
			->setDefaultValue($now->deductDaysFromDatetime(30))
			->setRequired();
		$form->addDatePicker('endDate', 'Do')
			->setDefaultValue($now)
			->setRequired();
		$form->addSubmit('submit', 'Vygenerovat přehled');

		$form->onSuccess[] = function (AdminForm $form) use ($onSuccess): void {
			$values = $form->getValues();
			$presetValue = TypeValidator::validateString($values->preset);
			if ($presetValue === self::CUSTOM_PRESET) {
				$report = $this->portfolioPeriodStatisticFacade->create(
					TypeValidator::validateImmutableDatetime($values->startDate),
					TypeValidator::validateImmutableDatetime($values->endDate),
				);
			} else {
				$report = $this->portfolioPeriodStatisticFacade->createForPreset(
					PortfolioPeriodStatisticPresetEnum::from($presetValue),
				);
			}

			$onSuccess($report);
		};

		return $form;
	}

}
