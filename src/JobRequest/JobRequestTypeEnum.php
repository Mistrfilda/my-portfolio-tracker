<?php

declare(strict_types = 1);

namespace App\JobRequest;

enum JobRequestTypeEnum: string
{

	case EXPENSE_TAG_PROCESS = 'expense_tag_process';

	case STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE = 'stock_asset_dividend_forecast_recalculate';

	case STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE_ALL = 'stock_asset_dividend_forecast_recalculate_all';

}
