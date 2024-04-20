<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Category;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum ExpenseCategoryEnum: string implements DatagridRenderableEnum
{

	case HYPERMARKETS = 'hypermarkets';

	case HOME_UTILITIES = 'home_utilities';

	case ESHOP_TECH = 'eshop_tech';

	case ESHOP_OTHER = 'eshop_other';

	case RESTAURANT = 'restaurant';

	case ONLINE_SUBSCRIPTION = 'online_subscription';

	case INVESTMENT = 'investment';

	case OTHER_BANK_TRANSFER = 'other_bank_transfer';

	case REVOLUT = 'revolut';

	case PETS = 'pets';

	case COSMETIC = 'cosmetic';

	case WORK_SOFTWARE = 'work_software';

	case CASH_OPERATION = 'cash_operation';

	case CLOTHING = 'clothing';

	case HEALTHCARE = 'healthcare';

	case CARS = 'cars';

	case TRAVEL = 'travel';

	public function format(): string
	{
		return match ($this) {
			ExpenseCategoryEnum::HYPERMARKETS => 'Hypermarket',
			ExpenseCategoryEnum::HOME_UTILITIES => 'Domovní poplatky',
			ExpenseCategoryEnum::ESHOP_TECH => 'Eshop - tech',
			ExpenseCategoryEnum::ESHOP_OTHER => 'Eshop - jiné',
			ExpenseCategoryEnum::RESTAURANT => 'Restaurant',
			ExpenseCategoryEnum::ONLINE_SUBSCRIPTION => 'Online předplatné',
			ExpenseCategoryEnum::INVESTMENT => 'Investment',
			ExpenseCategoryEnum::OTHER_BANK_TRANSFER => 'Převod do jiné banky',
			ExpenseCategoryEnum::REVOLUT => 'Revolut',
			ExpenseCategoryEnum::PETS => 'Domácí mazlíčci',
			ExpenseCategoryEnum::COSMETIC => 'Kosmetika',
			ExpenseCategoryEnum::WORK_SOFTWARE => 'Pracovní IT nástroje',
			ExpenseCategoryEnum::CASH_OPERATION => 'Hotovostní operace',
			ExpenseCategoryEnum::CLOTHING => 'Oblečení',
			ExpenseCategoryEnum::HEALTHCARE => 'Zdravotnictví',
			ExpenseCategoryEnum::CARS => 'Auta + benzínové pumpy',
			ExpenseCategoryEnum::TRAVEL => 'Cestování'
		};
	}

	/**
	 * @return array<string, int>
	 */
	public static function getIds(): array
	{
		return [
			ExpenseCategoryEnum::HYPERMARKETS->value => 1,
			ExpenseCategoryEnum::HOME_UTILITIES->value => 2,
			ExpenseCategoryEnum::ESHOP_TECH->value => 3,
			ExpenseCategoryEnum::ESHOP_OTHER->value => 4,
			ExpenseCategoryEnum::RESTAURANT->value => 5,
			ExpenseCategoryEnum::ONLINE_SUBSCRIPTION->value => 6,
			ExpenseCategoryEnum::INVESTMENT->value => 7,
			ExpenseCategoryEnum::OTHER_BANK_TRANSFER->value => 8,
			ExpenseCategoryEnum::REVOLUT->value => 9,
			ExpenseCategoryEnum::PETS->value => 10,
			ExpenseCategoryEnum::COSMETIC->value => 11,
			ExpenseCategoryEnum::WORK_SOFTWARE->value => 12,
			ExpenseCategoryEnum::CASH_OPERATION->value => 13,
			ExpenseCategoryEnum::CLOTHING->value => 14,
			ExpenseCategoryEnum::HEALTHCARE->value => 15,
			ExpenseCategoryEnum::CARS->value => 16,
			ExpenseCategoryEnum::TRAVEL->value => 17,
		];
	}

}
