import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import {
	_electron as electron,
	expect,
	test,
	type ElectronApplication,
} from '@playwright/test';

test('opens the desktop, keeps Node isolated, and saves appearance', async () => {
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
		expect(info.bridgeKeys).toEqual(['getAppInfo']);

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
	} finally {
		try {
			await application?.close();
		} finally {
			await rm(userData, { recursive: true, force: true });
		}
	}
});
