import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';
import {
	mkdtemp,
	readFile,
	readdir,
	rm,
	stat,
	writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
	EncryptedTokenStore,
	type Encryption,
} from '../../src/main/auth/token-store';
import { AuthError } from '../../src/main/auth/errors';

const directories: string[] = [];
const tokens = {
	accessToken: 'test-access-only',
	refreshToken: 'test-refresh-only',
	expiresAt: 2000000000000,
};

async function fixture() {
	const directory = await mkdtemp(join(tmpdir(), 'portfolio-test-store-'));
	directories.push(directory);
	const key = randomBytes(32);
	const encryption: Encryption = {
		isAsyncEncryptionAvailable: vi.fn(async () => true),
		encryptStringAsync: vi.fn(async (value) => {
			const iv = randomBytes(12);
			const cipher = createCipheriv('aes-256-gcm', key, iv);
			const encrypted = Buffer.concat([
				cipher.update(value, 'utf8'),
				cipher.final(),
			]);
			return Buffer.concat([iv, cipher.getAuthTag(), encrypted]);
		}),
		decryptStringAsync: vi.fn(async (value) => {
			const cipher = createDecipheriv(
				'aes-256-gcm',
				key,
				value.subarray(0, 12),
				{ authTagLength: 16 },
			);
			cipher.setAuthTag(value.subarray(12, 28));
			return {
				result: Buffer.concat([
					cipher.update(value.subarray(28)),
					cipher.final(),
				]).toString('utf8'),
				shouldReEncrypt: false,
			};
		}),
	};
	const store = new EncryptedTokenStore(
		directory,
		'test-connection',
		encryption,
	);
	return {
		directory,
		encryption,
		store,
		file: join(directory, 'sessions/test-connection.bin'),
	};
}

afterEach(async () => {
	await Promise.all(
		directories
			.splice(0)
			.map((directory) => rm(directory, { recursive: true, force: true })),
	);
});

describe('encrypted session storage', () => {
	it('does not access the keychain when there is no saved session', async () => {
		const f = await fixture();
		expect(await f.store.read()).toBeNull();
		expect(f.encryption.isAsyncEncryptionAvailable).not.toHaveBeenCalled();
	});
	it('stores only ciphertext with private permissions and supports atomic replacement', async () => {
		const f = await fixture();
		await f.store.save(tokens);
		expect(
			(await readFile(f.file)).includes(Buffer.from(tokens.refreshToken)),
		).toBe(false);
		expect((await stat(f.file)).mode & 0o777).toBe(0o600);
		expect(await f.store.read()).toEqual(tokens);
		const rotated = { ...tokens, refreshToken: 'test-rotated-refresh' };
		await f.store.save(rotated);
		expect(await f.store.read()).toEqual(rotated);
		expect(await readdir(join(f.directory, 'sessions'))).toEqual([
			'test-connection.bin',
		]);
		await f.store.clear();
		expect(await f.store.read()).toBeNull();
	});
	it('does not overwrite an existing session when encryption becomes unavailable', async () => {
		const f = await fixture();
		await f.store.save(tokens);
		const before = await readFile(f.file);
		vi.mocked(f.encryption.isAsyncEncryptionAvailable).mockResolvedValue(false);
		await expect(
			f.store.save({ ...tokens, refreshToken: 'test-new' }),
		).rejects.toEqual(new AuthError('storage'));
		expect(await readFile(f.file)).toEqual(before);
	});
	it('rejects corrupted ciphertext without exposing its contents', async () => {
		const f = await fixture();
		await f.store.save(tokens);
		await writeFile(f.file, Buffer.from('invalid-encrypted-test-data'));
		await expect(f.store.read()).rejects.toEqual(new AuthError('storage'));
	});
	it('cannot restore a session copied into another connection namespace', async () => {
		const f = await fixture();
		await f.store.save(tokens);
		await writeFile(
			join(f.directory, 'sessions/other-connection.bin'),
			await readFile(f.file),
		);
		await expect(
			new EncryptedTokenStore(
				f.directory,
				'other-connection',
				f.encryption,
			).read(),
		).rejects.toEqual(new AuthError('storage'));
	});
});
