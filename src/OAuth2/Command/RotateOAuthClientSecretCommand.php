<?php

declare(strict_types = 1);

namespace App\OAuth2\Command;

use App\OAuth2\Client\OAuthClientFacade;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('oauth:client:rotate-secret', 'Rotate a confidential client secret and revoke its existing tokens.')]
class RotateOAuthClientSecretCommand extends Command
{

	public function __construct(private OAuthClientFacade $clients)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('client-id', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$credentials = $this->clients->rotateSecret(
				TypeValidator::validateString($input->getArgument('client-id')),
			);
		} catch (InvalidArgumentException $e) {
			$output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);
			return self::INVALID;
		}

		$output->writeln('client_secret: ' . $credentials->secret, OutputInterface::OUTPUT_RAW);
		$output->writeln('Previous credentials and tokens have been revoked. Store the new secret securely.');
		return self::SUCCESS;
	}

}
