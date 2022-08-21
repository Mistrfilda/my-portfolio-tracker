<?php

declare(strict_types = 1);

namespace App\Test\Unit\Helper;

use App\Currency\CurrencyConversionFacade;
use Mockery;
use Mockery\MockInterface;

class CurrencyConversionMockFacade
{

	public function create(): MockInterface
	{
		return Mockery::mock(CurrencyConversionFacade::class)->makePartial();
	}

}
