<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Api;

use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use const DATE_ATOM;

class StockDividendSerializer
{

	/** @return array<string, mixed> */
	public function serialize(StockAssetDividend $dividend): array
	{
		$stock = $dividend->getStockAsset();
		return [
			'id' => $dividend->getId()->toString(),
			'stock' => [
				'id' => $stock->getId()->toString(),
				'name' => $stock->getName(),
				'ticker' => $stock->getTicker(),
			],
			'type' => $dividend->getDividendType()->value,
			'exDate' => $dividend->getExDate()->format('Y-m-d'),
			'paymentDate' => $dividend->getPaymentDate()?->format('Y-m-d'),
			'declarationDate' => $dividend->getDeclarationDate()?->format('Y-m-d'),
			'currency' => $dividend->getCurrency()->value,
			'grossAmountPerShare' => $dividend->getSummaryPrice(false)->getPrice(),
			'netAmountPerShare' => $dividend->getSummaryPrice()->getPrice(),
			'taxPercent' => $dividend->getDividendTax(),
		];
	}

	/** @return array<string, mixed> */
	public function serializeRecord(StockAssetDividendRecord $record): array
	{
		return [
			'id' => $record->getId()->toString(),
			'dividend' => $this->serialize($record->getStockAssetDividend()),
			'piecesHeldAtExDate' => $record->getTotalPiecesHeldAtExDate(),
			'currency' => $record->getCurrency()->value,
			'grossAmount' => $record->getSummaryPrice(false)->getPrice(),
			'netAmount' => $record->getSummaryPrice()->getPrice(),
			'reinvested' => $record->isReinvested(),
			'updatedAt' => $record->getUpdatedAt()->format(DATE_ATOM),
		];
	}

}
