<?php

declare(strict_types = 1);

namespace App\OAuth2;

use App\Utils\TypeValidator;
use League\OAuth2\Server\CryptKey;
use Nette\Utils\FileSystem;
use RuntimeException;
use const LOCK_EX;
use const LOCK_NB;
use const OPENSSL_KEYTYPE_RSA;

class OAuthKeys
{

	public function __construct(private string $directory)
	{
	}

	public function getPrivateKey(): CryptKey
	{
		return new CryptKey($this->getPath('private.key'));
	}

	public function getPublicKey(): CryptKey
	{
		return new CryptKey($this->getPath('public.key'));
	}

	public function getEncryptionKey(): string
	{
		$key = trim(FileSystem::read($this->getPath('encryption.key')));
		if (strlen($key) < 64) {
			throw new OAuthNotConfiguredException('OAuth encryption key is invalid.');
		}

		return $key;
	}

	public function generate(): void
	{
		FileSystem::createDir($this->directory, 0o700);
		$lock = fopen($this->directory . '/.generate.lock', 'c');
		if ($lock === false) {
			throw new RuntimeException('Unable to lock OAuth key generation.');
		}

		$previousMask = umask(0o077);
		try {
			if (!flock($lock, LOCK_EX | LOCK_NB)) {
				throw new RuntimeException('OAuth key generation is already running.');
			}

			$this->writeNewKeys();
		} finally {
			umask($previousMask);
			fclose($lock);
		}
	}

	private function writeNewKeys(): void
	{
		foreach (['private.key', 'public.key', 'encryption.key'] as $name) {
			if (file_exists($this->directory . '/' . $name)) {
				throw new RuntimeException('OAuth keys already exist. Refusing to overwrite them.');
			}
		}

		$key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		if ($key === false || !openssl_pkey_export($key, $private)) {
			throw new RuntimeException('Unable to generate OAuth signing keys.');
		}

		$details = openssl_pkey_get_details($key);
		if ($details === false) {
			throw new RuntimeException('Unable to export OAuth public key.');
		}

		FileSystem::write($this->directory . '/private.key', TypeValidator::validateString($private), 0o600);
		FileSystem::write($this->directory . '/public.key', TypeValidator::validateString($details['key']), 0o600);
		FileSystem::write($this->directory . '/encryption.key', bin2hex(random_bytes(32)), 0o600);
	}

	private function getPath(string $name): string
	{
		$path = $this->directory . '/' . $name;
		if (!is_file($path) || !is_readable($path)) {
			throw new OAuthNotConfiguredException('OAuth keys are not configured. Run oauth:keys:generate.');
		}

		return $path;
	}

}
