<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\Json;

use App\UI\Filter\CurrencyFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('crypto:prices:download')]
class CryptoAssetJsonDownloaderCommand extends Command
{

	public function __construct(
		private CryptoAssetJsonDownloaderFacade $cryptoAssetJsonDownloaderFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Prepare crypto source data for Puppeter');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading crypto prices');
		foreach ($this->cryptoAssetJsonDownloaderFacade->processResults() as $priceRecord) {
			$output->writeln(
				sprintf(
					'<info>Downloaded new rate for pair %s - %s: %s</info>',
					$priceRecord->getCryptoAsset()->getTicker(),
					$priceRecord->getCurrency()->format(),
					CurrencyFilter::format($priceRecord->getPrice(), $priceRecord->getCurrency()),
				),
			);
		}

		$output->writeln('Done');
		return 0;
	}

}
