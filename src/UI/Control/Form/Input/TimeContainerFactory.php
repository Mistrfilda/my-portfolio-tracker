<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Input;

use App\UI\Control\Form\AdminForm;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Forms\Container;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;

class TimeContainerFactory
{

	public function __construct(private DatetimeFactory $datetimeFactory)
	{
	}

	public function create(
		AdminForm $form,
		int $minAge,
		int $maxAge,
		string $name,
		string $label,
		bool $required = true,
	): Container
	{
		$container = $form->addAdminContainer($name, $label);
		$container->setTimeContainer();

		$container->addSelect('day', 'Den', $this->getDaysOptions())
			->setRequired($required)
			->setPrompt('Den');
		$container->addSelect('month', 'Měsíc', $this->getMonthsOptions())
			->setRequired($required)
			->setPrompt('Měsíc');
		$container->addSelect('year', 'Rok', $this->getYearsOptions($minAge, $maxAge))
			->setRequired($required)
			->setPrompt('Rok');

		$container->addSelect('hour', 'Hodina', $this->getHoursOptions())
			->setRequired($required)
			->setPrompt('Hodina');
		$container->addSelect('minute', 'Minuta', $this->getMinutesOptions())
			->setRequired($required)
			->setPrompt('Minuta');

		return $form;
	}

	public function setDefaults(Form $form, ImmutableDateTime $datetime, string $name): void
	{
		$form->setDefaults([
			$name => [
				'day' => $datetime->getDay(),
				'month' => $datetime->getMonth(),
				'year' => $datetime->getYear(),
				'hour' => $datetime->getHour(),
				'minute' => $datetime->getMinutes(),
			],
		]);
	}

	public function processValuesFromArrayHash(ArrayHash $values, string $name): ImmutableDateTime|null
	{
		if (
			$values->{$name}->day === null
			|| $values->{$name}->month === null
			|| $values->{$name}->year === null
			|| $values->{$name}->hour === null
			|| $values->{$name}->minute === null
		) {
			return null;
		}

		return (new ImmutableDateTime())
			->setDate($values->{$name}->year, $values->{$name}->month, $values->{$name}->day)
			->setTime($values->{$name}->hour, $values->{$name}->minute);
	}

	/**
	 * @return array<int>
	 */
	private function getDaysOptions(): array
	{
		$range = range(1, 31);

		return array_combine($range, $range);
	}

	/**
	 * @return array<int>
	 */
	private function getMonthsOptions(): array
	{
		$months = range(1, 12);

		return array_combine($months, $months);
	}

	/**
	 * @return array<int>
	 */
	private function getHoursOptions(): array
	{
		$range = range(1, 24);

		return array_combine($range, $range);
	}

	/**
	 * @return array<int>
	 */
	private function getMinutesOptions(): array
	{
		$range = range(0, 60);

		return array_combine($range, $range);
	}

	/**
	 * @return array<int>
	 */
	private function getYearsOptions(int $minAge, int $maxAge): array
	{
		$currentYear = $this->datetimeFactory->createNow()->getYear();
		$startingYear = $currentYear - $maxAge;
		$endingYear = $currentYear - $minAge;
		$years = range($endingYear, $startingYear, +1);

		return array_combine($years, $years);
	}

}
