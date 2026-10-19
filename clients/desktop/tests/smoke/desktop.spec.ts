import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { defaultConnection } from '../../src/main/auth/config';
import {
	_electron as electron,
	expect,
	test,
	type ElectronApplication,
} from '@playwright/test';

test('isolates Node, saves appearance, and starts and cancels PKCE login', async () => {
	const userData = await mkdtemp(join(tmpdir(), 'portfolio-desktop-test-'));
	const executablePath = process.env.DESKTOP_EXECUTABLE;
	let application: ElectronApplication | undefined;
	try {
		application = await electron.launch({
			...(executablePath ? { executablePath } : {}),
			args: [
				...(executablePath ? [] : [resolve('.')]),
				'--user-data-dir=' + userData,
			],
		});
		const page = await application.firstWindow();
		const errors: string[] = [];
		page.on('pageerror', (error) => errors.push(error.message));
		await expect(
			page.getByRole('heading', { name: 'Vítejte v My Portfolio.' }),
		).toBeVisible();

		const info = await page.evaluate(async () => {
			const bridge = window.desktop;
			if (!bridge) {
				throw new Error('The desktop preload bridge is unavailable.');
			}
			return {
				app: await bridge.getAppInfo(),
				hasNode:
					Reflect.has(window, 'require') || Reflect.has(window, 'process'),
				bridgeKeys: Object.keys(bridge),
			};
		});
		expect(info.app.version).toMatch(/^\d+\.\d+\.\d+/);
		expect(info.hasNode).toBe(false);
		expect(info.bridgeKeys).toEqual(['getAppInfo', 'session']);

		const rendererProcesses = await application.evaluate(({ app }) =>
			app
				.getAppMetrics()
				.filter((metric) => metric.type === 'Tab')
				.map((metric) => metric.sandboxed),
		);
		expect(rendererProcesses).not.toHaveLength(0);
		expect(rendererProcesses.every((sandboxed) => sandboxed === true)).toBe(
			true,
		);

		await page.getByRole('link', { name: 'Upravit vzhled' }).click();
		await expect(
			page.getByRole('heading', { name: 'Nastavení', exact: true }),
		).toBeVisible();
		await expect(page.getByText('Verze ' + info.app.version)).toBeVisible();
		await page.getByRole('button', { name: 'Tmavý', exact: true }).click();
		await expect(page.locator('html')).toHaveClass('dark');
		await page.reload();
		await expect(
			page.getByRole('heading', { name: 'Vítejte v My Portfolio.' }),
		).toBeVisible();
		await expect(page.locator('html')).toHaveClass('dark');
		expect(errors).toEqual([]);

		// Intercept only the OS browser opener in this isolated test process.
		// No real account, browser profile or Keychain credential is used.
		await application.evaluate(({ shell }) => {
			shell.openExternal = async (url) => {
				Reflect.set(globalThis, 'testAuthorizationUrl', url);
			};
		});
		await page
			.getByRole('button', { name: 'Přihlásit se', exact: true })
			.click();
		await expect(
			page.getByRole('button', { name: 'Zrušit přihlášení' }),
		).toBeVisible();
		await expect
			.poll(() =>
				application!.evaluate(() =>
					Reflect.has(globalThis, 'testAuthorizationUrl'),
				),
			)
			.toBe(true);
		const authorization = await application.evaluate(() => {
			const url = new URL(
				String(Reflect.get(globalThis, 'testAuthorizationUrl')),
			);
			return {
				origin: url.origin,
				path: url.pathname,
				pkce: url.searchParams.get('code_challenge_method'),
				hasSecret: url.searchParams.has('client_secret'),
				callbackHost: new URL(url.searchParams.get('redirect_uri')!).hostname,
			};
		});
		expect(authorization).toEqual({
			origin: new URL(defaultConnection.authorizationUrl).origin,
			path: new URL(defaultConnection.authorizationUrl).pathname,
			pkce: 'S256',
			hasSecret: false,
			callbackHost: '127.0.0.1',
		});
		await page.getByRole('button', { name: 'Zrušit přihlášení' }).click();
		await expect(
			page.getByRole('button', { name: 'Přihlásit se', exact: true }),
		).toBeVisible();
		expect(
			await page.evaluate(
				async () => (await window.desktop!.session.getState()).status,
			),
		).toBe('signed-out');
		await page.screenshot({
			path: test.info().outputPath('desktop-account.png'),
		});
		expect(errors).toEqual([]);
	} finally {
		try {
			await application?.close();
		} finally {
			await rm(userData, { recursive: true, force: true });
		}
	}
});
