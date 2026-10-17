<?php

declare(strict_types = 1);

namespace App\OAuth2\Command;

use App\OAuth2\Authorization\OAuthAuthorizationStore;
use App\OAuth2\Http\OAuthRateLimiter;
use App\OAuth2\Token\OAuthTokenStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('oauth:cleanup', 'Remove expired OAuth records while retaining active token-family replay detection.')]
class CleanupOAuthCommand extends Command
{

	public function __construct(
		private OAuthTokenStore $tokens,
		private OAuthAuthorizationStore $authorizations,
		private OAuthRateLimiter $limiter,
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$count = $this->tokens->cleanup();
		$this->authorizations->cleanup();
		$this->limiter->cleanup();
		$output->writeln(sprintf('Removed %d expired tokens and expired authorization/rate-limit records.', $count));
		return self::SUCCESS;
	}

}
