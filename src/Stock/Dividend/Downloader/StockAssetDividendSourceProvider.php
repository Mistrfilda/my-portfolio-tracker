<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader;

interface StockAssetDividendSourceProvider
{

	public function generateDividendsJsonFile(string $fileLocation): void;

}
