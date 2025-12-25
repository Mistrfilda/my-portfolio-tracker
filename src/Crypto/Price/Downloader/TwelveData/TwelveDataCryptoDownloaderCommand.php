<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\TwelveData;

use App\Crypto\Price\CryptoAssetPriceRecord;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('crypto:twelve-data:download')]
class TwelveDataCryptoDownloaderCommand extends Command
{

	public function __construct(
		private readonly TwelveDataCryptoDownloaderFacade $twelveDataDownloader,
		private readonly LoggerInterface $logger,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Download new price data from twelve data');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading new data from twelve data');

		try {
			foreach ($this->twelveDataDownloader->getPriceForAssets() as $newPrice) {
				assert($newPrice instanceof CryptoAssetPriceRecord);
				$output->writeln(
					sprintf(
						'<info>Downloaded new price for crypto %s - current price: %s %s</info>',
						$newPrice->getCryptoAsset()->getTicker(),
						$newPrice->getPrice(),
						$newPrice->getCurrency()->format(),
					),
				);
			}
		} catch (Throwable $e) {
			$this->logger->critical($e);
		}

		$output->writeln('Rates successfully downloaded');

		return 0;
	}

}
