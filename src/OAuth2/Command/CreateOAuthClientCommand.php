<?php

declare(strict_types = 1);

namespace App\OAuth2\Command;

use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\OAuthScope;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Nette\Utils\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('oauth:client:create', 'Register an OAuth application; public native apps do not receive a secret.')]
class CreateOAuthClientCommand extends Command
{

	public function __construct(private OAuthClientFacade $clients)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('name', InputArgument::REQUIRED, 'Application name')
			->addOption(
				'public',
				null,
				InputOption::VALUE_NONE,
				'Native/mobile/desktop application without a client secret',
			)
			->addOption(
				'redirect-uri',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Exact registered callback URI (repeatable)',
			)
			->addOption(
				'scope',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Allowed scope (repeatable; defaults to api:read)',
			)
			->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable credentials');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$scopes = array_values(
				array_map(TypeValidator::validateString(...), TypeValidator::validateArray($input->getOption('scope'))),
			);
			$redirects = array_values(
				array_map(
					TypeValidator::validateString(...),
					TypeValidator::validateArray($input->getOption('redirect-uri')),
				),
			);
			$credentials = $this->clients->create(
				TypeValidator::validateString($input->getArgument('name')),
				!TypeValidator::validateBool($input->getOption('public')),
				$scopes === [] ? [OAuthScope::API_READ->value] : $scopes,
				$redirects,
			);
		} catch (InvalidArgumentException $e) {
			$output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);
			return self::INVALID;
		}

		if ($input->getOption('json') === true) {
			$output->writeln(
				Json::encode(
					['client_id' => $credentials->client->getIdentifier(), 'client_secret' => $credentials->secret],
				),
				OutputInterface::OUTPUT_RAW,
			);
		} else {
			$output->writeln('client_id: ' . $credentials->client->getIdentifier(), OutputInterface::OUTPUT_RAW);
			if ($credentials->secret !== null) {
				$output->writeln('client_secret: ' . $credentials->secret, OutputInterface::OUTPUT_RAW);
				$output->writeln('Store this secret securely. Only its hash is saved; it cannot be displayed again.');
			} else {
				$output->writeln('Public client: use Authorization Code with S256 PKCE, without a client secret.');
			}
		}

		return self::SUCCESS;
	}

}
