<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Filter;

use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use function assert;

class FilterForm
{

	private const COLUMN_PREFIX = 'dg_fi_';

	public function createForm(Datagrid $datagrid): AdminForm
	{
		$form = new AdminForm();
		foreach ($datagrid->getFilters() as $filter) {
			if ($filter->getType() === FilterType::FILTER_TEXT) {
				$form->addText(
					self::COLUMN_PREFIX . $filter->getColumn()->getColumn(),
					$filter->getColumn()->getLabel(),
				)->setNullable();
			}
		}

		$form->addSubmit('submit', 'Filtrovat');

		$form->onSuccess[] = static function (Form $form) use ($datagrid): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$parsedValues = [];
			foreach ($values as $key => $value) {
				if ($value === null || is_string($value) === false) {
					continue;
				}

				$key = Strings::replace($key, '~' . self::COLUMN_PREFIX . '~', '');
				$parsedValues[] = new FilterValue($key, $value);
			}

			$datagrid->resetPagination();
			$datagrid->filter($parsedValues);
		};

		return $form;
	}

}
