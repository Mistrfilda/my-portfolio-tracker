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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('oauth:client:scopes', 'Replace a client scope allowlist and revoke its tokens if permissions change.')]
class UpdateOAuthClientScopesCommand extends Command
{

	public function __construct(private OAuthClientFacade $clients)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('client-id', InputArgument::REQUIRED)
			->addOption(
				'scope',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Complete allowed scope list (repeatable; at least one required)',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$this->clients->updateScopes(
				TypeValidator::validateString($input->getArgument('client-id')),
				array_values(array_map(
					TypeValidator::validateString(...),
					TypeValidator::validateArray($input->getOption('scope')),
				)),
			);
		} catch (InvalidArgumentException $e) {
			$output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);
			return self::INVALID;
		}

		$output->writeln('Client scopes saved. Changed permissions revoke existing tokens; authorize again.');
		return self::SUCCESS;
	}

}
