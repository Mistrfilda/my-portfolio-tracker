<?php

declare(strict_types = 1);

namespace App\Api;

use App\Admin\CurrentAppAdminGetter;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthResponses;
use App\OAuth2\OAuthScope;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenStore;
use Psr\Http\Server\MiddlewareInterface;

class ApiAuthenticationMiddlewareFactory
{

	public function __construct(
		private MiddlewareInterface $apiKeys,
		private OAuthServerFactory $servers,
		private OAuthTokenStore $tokens,
		private OAuthClientRepository $clients,
		private OAuthUserAuthenticator $users,
		private CurrentAppAdminGetter $currentUser,
		private OAuthResponses $responses,
	)
	{
	}

	public function create(OAuthScope $scope, bool $allowLegacy = false): ApiAuthenticationMiddleware
	{
		return new ApiAuthenticationMiddleware(
			$scope,
			$allowLegacy,
			$this->apiKeys,
			$this->servers,
			$this->tokens,
			$this->clients,
			$this->users,
			$this->currentUser,
			$this->responses,
		);
	}

}
