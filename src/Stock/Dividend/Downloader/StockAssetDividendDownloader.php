<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader;

interface StockAssetDividendDownloader
{

	public function downloadDividendRecords(): void;

}
