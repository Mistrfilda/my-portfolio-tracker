<?php

declare(strict_types = 1);

namespace App\OAuth2\Command;

use App\OAuth2\OAuthKeys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('oauth:keys:generate', 'Generate persistent OAuth server keys without overwriting existing keys.')]
class GenerateOAuthKeysCommand extends Command
{

	public function __construct(private OAuthKeys $keys)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->keys->generate();
		$output->writeln('OAuth server keys generated. Keep this directory persistent and backed up securely.');
		return self::SUCCESS;
	}

}
