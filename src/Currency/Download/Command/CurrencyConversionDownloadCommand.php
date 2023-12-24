<?php

declare(strict_types = 1);

namespace App\Currency\Download\Command;

use App\Currency\Download\CurrencyConversionDownloadFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CurrencyConversionDownloadCommand extends Command
{

	/**
	 * @param array<CurrencyConversionDownloadFacade> $currencyConversionFacades
	 */
	public function __construct(private array $currencyConversionFacades)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setName('currency:download');
		$this->setDescription('Download new conversion rates');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading new rates');
		foreach ($this->currencyConversionFacades as $currencyConversionFacade) {
			$output->writeln('<comment>' . $currencyConversionFacade->getConsoleDescription() . '</comment>');
			$newRates = $currencyConversionFacade->downloadNewRates();
			foreach ($newRates as $newRate) {
				$output->writeln(
					sprintf(
						'<info>Downloaded new rate for pair %s - %s: %s</info>',
						$newRate->getFromCurrency()->name,
						$newRate->getToCurrency()->name,
						$newRate->getCurrentPrice(),
					),
				);
			}
		}

		$output->writeln('Rates successfully downloaded');

		return 0;
	}

}
