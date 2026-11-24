<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxDonationItemDTO
{

	public function __construct(
		public string $source,
		public string $id,
		public string $recipientName,
		public int $amountCzk,
		public string|null $donatedAt,
		public string|null $note,
	)
	{
	}

}
