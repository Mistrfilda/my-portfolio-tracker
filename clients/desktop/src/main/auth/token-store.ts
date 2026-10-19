import { randomUUID } from 'node:crypto';
import { mkdir, open, readFile, rename, rm, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { parseStoredTokens, type Tokens } from './client';
import { AuthError } from './errors';

export interface TokenStore {
	read(): Promise<Tokens | null>;
	save(tokens: Tokens): Promise<void>;
	clear(): Promise<void>;
}

export interface Encryption {
	isAsyncEncryptionAvailable(): Promise<boolean>;
	encryptStringAsync(value: string): Promise<Buffer>;
	decryptStringAsync(
		value: Buffer,
	): Promise<{ result: string; shouldReEncrypt: boolean }>;
}

export class EncryptedTokenStore implements TokenStore {
	private readonly directory: string;
	private readonly file: string;

	constructor(
		userData: string,
		private readonly storageKey: string,
		private readonly encryption: Encryption,
	) {
		this.directory = join(userData, 'sessions');
		this.file = join(this.directory, storageKey + '.bin');
	}

	async read(): Promise<Tokens | null> {
		try {
			let size: number;
			try {
				size = (await stat(this.file)).size;
			} catch (error) {
				if (
					error instanceof Error &&
					'code' in error &&
					error.code === 'ENOENT'
				)
					return null;
				throw error;
			}
			if (
				size > 512 * 1024 ||
				!(await this.encryption.isAsyncEncryptionAvailable())
			)
				throw new AuthError('storage');
			const decoded = await this.encryption.decryptStringAsync(
				await readFile(this.file),
			);
			const data: unknown = JSON.parse(decoded.result);
			if (
				typeof data !== 'object' ||
				data === null ||
				!('connection' in data) ||
				data.connection !== this.storageKey ||
				!('tokens' in data)
			)
				throw new AuthError('storage');
			const tokens = parseStoredTokens(data.tokens);
			if (decoded.shouldReEncrypt) await this.save(tokens);
			return tokens;
		} catch {
			throw new AuthError('storage');
		}
	}

	async save(tokens: Tokens): Promise<void> {
		const temporary = this.file + '.' + randomUUID() + '.tmp';
		try {
			if (!(await this.encryption.isAsyncEncryptionAvailable()))
				throw new AuthError('storage');
			const encrypted = await this.encryption.encryptStringAsync(
				JSON.stringify({ connection: this.storageKey, tokens }),
			);
			await mkdir(this.directory, { recursive: true, mode: 0o700 });
			const file = await open(temporary, 'wx', 0o600);
			try {
				await file.writeFile(encrypted);
				await file.sync();
			} finally {
				await file.close();
			}
			await rename(temporary, this.file);
			await this.syncDirectory();
		} catch {
			throw new AuthError('storage');
		} finally {
			await rm(temporary, { force: true }).catch(() => undefined);
		}
	}

	async clear(): Promise<void> {
		try {
			await rm(this.file, { force: true });
			await this.syncDirectory();
		} catch {
			throw new AuthError('storage');
		}
	}

	private async syncDirectory(): Promise<void> {
		try {
			const directory = await open(this.directory, 'r');
			try {
				await directory.sync();
			} finally {
				await directory.close();
			}
		} catch (error) {
			if (error instanceof Error && 'code' in error && error.code === 'ENOENT')
				return;
			throw error;
		}
	}
}
