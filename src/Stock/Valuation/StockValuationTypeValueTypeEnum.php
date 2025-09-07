<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

enum StockValuationTypeValueTypeEnum: string
{

	case FLOAT = 'float';

	case PERCENTAGE = 'percentage';

	case TEXT = 'text';

}
