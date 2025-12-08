<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\Json;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('crypto:prices:generate')]
class CryptoAssetJsonGenerateSourceFilesCommand extends Command
{

	public function __construct(
		private CryptoJsonDataSourceProviderFacade $cryptoJsonDataSourceProviderFacade,
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
		$output->writeln('Generating source data for Puppeter');
		$this->cryptoJsonDataSourceProviderFacade->generateRequestFile();
		$output->writeln('Data for Puppeter generated');
		return 0;
	}

}
