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
		];
	}

}
