<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Pse;

class PseDataRequest
{

	public function __construct(
		private readonly string $url,
		private readonly int $pricePositionTag,
		private readonly int $tableTdsCount,
	)
	{
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	public function getPricePositionTag(): int
	{
		return $this->pricePositionTag;
	}

	public function getTableTdsCount(): int
	{
		return $this->tableTdsCount;
	}

}
