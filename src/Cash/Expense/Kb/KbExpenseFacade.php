<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Kb;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Bank\BankSourceEnum;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;

class KbExpenseFacade implements BankExpenseFacade
{

	public function __construct(
		private KbPdfExpenseParser $kbPdfExpenseParser,
		private BankExpenseRepository $bankExpenseRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private LoggerInterface $logger,
	)
	{

	}

	/**
	 * Returns TRUE if hasErrors, else FALSE
	 */
	public function processFileContents(string $fileContents): bool
	{
		$parsedContents = $this->kbPdfExpenseParser->parse($fileContents);

		foreach ($parsedContents->getProcessedTransactions() as $transaction) {
			$id = $this->computeIdForTransaction($transaction);

			if ($this->bankExpenseRepository->findByIdentifier($id) !== null) {
				continue;
			}

			$expense = new BankExpense(
				$id,
				BankSourceEnum::KOMERCNI_BANKA,
				$transaction->getBankTransactionType(),
				$transaction->getAmount(),
				CurrencyEnum::CZK,
				$transaction->getSettlementDate(),
				$transaction->getTransactionDate(),
				$transaction->getTransactionRawContent() ?? '',
				$this->datetimeFactory->createNow(),
			);

			$this->entityManager->persist($expense);
			$this->entityManager->flush();
		}

		if (count($parsedContents->getUnprocessedTransactions()) !== 0) {
			$this->logger->critical(
				'Some transactions were not processed',
				['transactions' => $parsedContents->getUnprocessedTransactions()],
			);
			return true;
		}

		return false;
	}

	private function computeIdForTransaction(KbTransaction $kbTransaction): string
	{
		return hash(
			'sha256',
			$kbTransaction->getSettlementDate()?->format('Y-m-d') . $kbTransaction->getTransactionDate()?->format(
				'Y-m-d',
			) . $kbTransaction->getTransactionRawContent(),
		);
	}

}
