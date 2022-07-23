<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Input;

use App\UI\Control\Form\DTO\Birthday;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Forms\Container;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;

class BirthdayContainerFactory
{

	public function __construct(private DatetimeFactory $datetimeFactory)
	{
	}

	public function create(Form $form, int $minAge, int $maxAge, bool $required = true): Container
	{
		$container = $form->addContainer('birthday');
		$container->addSelect('day', 'Den', $this->getDaysOptions())
			->setRequired($required)
			->setPrompt('Den');
		$container->addSelect('month', 'Měsíc', $this->getMonthsOptions())
			->setRequired($required)
			->setPrompt('Měsíc');
		$container->addSelect('year', 'Rok', $this->getYearsOptions($minAge, $maxAge))
			->setRequired($required)
			->setPrompt('Rok');

		return $form;
	}

	public function setDefaults(Form $form, Birthday $birthday): void
	{
		$form->setDefaults([
			'birthday' => [
				'day' => $birthday->getDay(),
				'month' => $birthday->getMonth(),
				'year' => $birthday->getYear(),
			],
		]);
	}

	public function processValuesFromArrayHash(ArrayHash $values): Birthday|null
	{
		if (
			$values->birthday->day === null
			|| $values->birthday->month === null
			|| $values->birthday->year === null
		) {
			return null;
		}

		return new Birthday(
			$values->birthday->day,
			$values->birthday->month,
			$values->birthday->year,
		);
	}

	public function getImmutableDatetimeFromArrayHashValues(ArrayHash $values): ImmutableDateTime|null
	{
		$birthday = $this->processValuesFromArrayHash($values);

		if ($birthday === null) {
			return null;
		}

		return DatetimeFactory::createFromFormat(
			sprintf(
				'%d-%d-%d',
				$birthday->getYear(),
				$birthday->getMonth(),
				$birthday->getDay(),
			),
			'Y-m-d',
		);
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
	private function getYearsOptions(int $minAge, int $maxAge): array
	{
		$currentYear = $this->datetimeFactory->createNow()->getYear();
		$startingYear = $currentYear - $maxAge;
		$endingYear = $currentYear - $minAge;
		$years = range($endingYear, $startingYear, +1);

		return array_combine($years, $years);
	}

}
