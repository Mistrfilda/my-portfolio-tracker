<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist\Exception;

use Exception;

class StockAssetWatchlistTickerAlreadyExistsException extends Exception
{

	public function __construct()
	{
		parent::__construct('The ticker already exists on the simple stock watchlist.');
	}

}
