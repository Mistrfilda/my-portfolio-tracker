<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use function assert;

class FilterForm
{

	private const COLUMN_PREFIX = 'dg_fi_';

	public function __construct(private AdminFormFactory $adminFormFactory)
	{
	}

	public function createForm(Datagrid $datagrid): AdminForm
	{
		$form = $this->adminFormFactory->create();
		foreach ($datagrid->getFilters() as $filter) {
			if ($filter instanceof FilterText) {
				$form->addText(
					self::COLUMN_PREFIX . $filter->getKey(),
					$filter->getLabel(),
				)->setNullable();

				continue;
			}

			if ($filter instanceof FilterDateRange) {
				$form->addDatePicker(
					self::COLUMN_PREFIX . $filter->getFromParameter(),
					$filter->getLabel() . ' od',
				)->setNullable();
				$form->addDatePicker(
					self::COLUMN_PREFIX . $filter->getToParameter(),
					$filter->getLabel() . ' do',
				)->setNullable();

				continue;
			}

			if ($filter instanceof FilterSelect) {
				$form->addSelect(
					self::COLUMN_PREFIX . $filter->getKey(),
					$filter->getLabel(),
					$filter->getOptions(),
				)->setPrompt('Vše');
			}
		}

		$form->addSubmit('submit', 'Filtrovat');

		$defaults = [];
		foreach ($datagrid->parameterFilters as $key => $value) {
			$defaults[self::COLUMN_PREFIX . $key] = $value;
		}

		$form->setDefaults($defaults);

		$form->onSuccess[] = static function (Form $form) use ($datagrid): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$parsedValues = [];
			foreach ($values as $key => $value) {
				if ($value === null || $value === '') {
					continue;
				}

				if ($value instanceof ImmutableDateTime) {
					$value = $value->format('Y-m-d');
				}

				if (is_string($value) === false && is_int($value) === false) {
					continue;
				}

				$key = substr($key, strlen(self::COLUMN_PREFIX));
				$parsedValues[] = new FilterValue($key, $value);
			}

			$datagrid->resetPagination();
			$datagrid->filter($parsedValues);
		};

		return $form;
	}

}
