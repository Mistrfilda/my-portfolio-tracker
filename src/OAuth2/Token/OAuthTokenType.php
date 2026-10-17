<?php

declare(strict_types = 1);

namespace App\OAuth2\Token;

enum OAuthTokenType: string
{

	case ACCESS = 'access';
	case REFRESH = 'refresh';
	case CODE = 'code';

}
