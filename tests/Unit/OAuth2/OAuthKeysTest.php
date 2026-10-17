<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2;

use App\OAuth2\OAuthKeys;
use App\OAuth2\OAuthNotConfiguredException;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OAuthKeysTest extends TestCase
{

	public function testMissingKeysFailExplicitly(): void
	{
		$this->expectException(OAuthNotConfiguredException::class);
		(new OAuthKeys(sys_get_temp_dir() . '/missing-oauth-' . bin2hex(random_bytes(8))))->getPrivateKey();
	}

	public function testGeneratedKeysHaveRestrictedPermissionsAndCannotBeOverwritten(): void
	{
		$directory = sys_get_temp_dir() . '/oauth-key-test-' . bin2hex(random_bytes(8));
		try {
			$keys = new OAuthKeys($directory);
			$keys->generate();
			foreach (['private.key', 'public.key', 'encryption.key'] as $file) {
				self::assertSame(0o600, fileperms($directory . '/' . $file) & 0o777);
			}

			self::assertNotSame('', $keys->getPublicKey()->getKeyContents());
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessage('Refusing to overwrite');
			$keys->generate();
		} finally {
			FileSystem::delete($directory);
		}
	}

}
