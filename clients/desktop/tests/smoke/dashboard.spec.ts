import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import {
	_electron as electron,
	expect,
	test,
	type ElectronApplication,
} from '@playwright/test';
import { dashboardFixture } from '../fixtures/dashboard';
import type { DashboardSnapshot } from '../../src/shared/dashboard';

test('renders the dashboard, refresh errors, empty data and logout in an isolated profile', async () => {
	const userData = await mkdtemp(join(tmpdir(), 'portfolio-dashboard-test-'));
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
			page.getByRole('button', { name: 'Přihlásit se', exact: true }),
		).toBeVisible();
		// Replace responses only in the isolated test process. Production never has a fixture path.
		await application.evaluate(({ ipcMain, BrowserWindow }, snapshot) => {
			const state = {
				revision: 100,
				status: 'signed-in',
				user: { id: 'fixture-user', name: 'Test Investor', username: 'test' },
				issue: null,
				connection: { name: 'Test', serverUrl: 'https://example.test' },
			};
			ipcMain.removeHandler('session:get');
			ipcMain.handle('session:get', () => state);
			ipcMain.removeHandler('dashboard:get');
			ipcMain.handle(
				'dashboard:get',
				() =>
					new Promise((resolve) => {
						Reflect.set(globalThis, 'resolveTestDashboard', () =>
							resolve(snapshot),
						);
					}),
			);
			BrowserWindow.getAllWindows()[0]!.webContents.send(
				'session:changed',
				state,
			);
		}, dashboardFixture());
		await expect(page.getByText('Načítám vaše portfolio…')).toBeVisible();
		await expect
			.poll(() =>
				application!.evaluate(() =>
					Reflect.has(globalThis, 'resolveTestDashboard'),
				),
			)
			.toBe(true);
		await application.evaluate(() =>
			Reflect.get(globalThis, 'resolveTestDashboard')(),
		);
		await expect(
			page.getByRole('heading', { name: 'Přehled portfolia' }),
		).toBeVisible();
		await expect(
			page.getByRole('heading', { name: 'Složení portfolia' }),
		).toBeVisible();
		await expect(page.getByText('+21,25 %', { exact: true })).toBeVisible();
		await expect(
			page.getByText('Nedostatek dat pro výpočet', { exact: true }),
		).toBeVisible();
		await expect(page.getByText('106,25 US$', { exact: true })).toBeVisible();
		await page.screenshot({
			path: test.info().outputPath('dashboard-light.png'),
		});

		await page.getByRole('link', { name: 'Nastavení', exact: true }).click();
		await page.getByRole('button', { name: 'Tmavý', exact: true }).click();
		const respond = async (snapshot: DashboardSnapshot) => {
			await application!.evaluate(({ ipcMain }, next) => {
				ipcMain.removeHandler('dashboard:get');
				ipcMain.handle('dashboard:get', () => next);
			}, snapshot);
		};
		await respond(dashboardFixture());
		await page.getByRole('link', { name: 'Přehled', exact: true }).click();
		await expect(
			page.getByRole('button', { name: 'Obnovit', exact: true }),
		).toBeEnabled();
		await page.screenshot({
			path: test.info().outputPath('dashboard-dark.png'),
		});
		await page.locator('main').evaluate((element) => {
			element.scrollTop = 850;
		});
		await page.screenshot({
			path: test.info().outputPath('dashboard-details.png'),
		});

		const failed = { data: null, issue: 'network' } as const;
		await respond({ summary: failed, dividends: failed, rates: failed });
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(page.getByText(/Zobrazená data pocházejí/)).toHaveCount(3);
		await expect(
			page.getByRole('heading', { name: 'Složení portfolia' }),
		).toBeVisible();
		await respond(dashboardFixture());
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(page.getByRole('alert')).toHaveCount(0);

		await application.evaluate(({ BrowserWindow }) => {
			BrowserWindow.getAllWindows()[0]!.setSize(920, 640);
		});
		// clientWidth excludes classic scrollbars, whose presence depends on macOS settings.
		await expect.poll(() => page.evaluate(() => window.innerWidth)).toBe(920);
		expect(
			await page
				.locator('main')
				.evaluate((element) => element.scrollWidth <= element.clientWidth),
		).toBe(true);
		await page.screenshot({
			path: test.info().outputPath('dashboard-small.png'),
		});

		const empty = dashboardFixture();
		const zero = {
			investedAmount: 0,
			currentValue: 0,
			profit: 0,
			profitPercent: 0,
		};
		empty.summary.data = {
			...empty.summary.data!,
			total: zero,
			crypto: zero,
			portu: zero,
			stocks: {
				amounts: zero,
				byTradingCurrency: [],
				closedPositionsProfitIncludingDividends: 0,
			},
			dividends: { byYear: [], remainingForecast: null },
			stockMovers: { best: [], worst: [] },
			performance: null,
		};
		empty.dividends = { data: [], issue: null };
		await respond(empty);
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(
			page.getByText(/V portfoliu zatím nejsou žádné otevřené investice/),
		).toBeVisible();
		await expect(
			page.getByText('Zatím nemáte žádné dividendové záznamy.'),
		).toBeVisible();
		await expect(
			page.getByText(/Pro výpočet výkonnosti zatím není dostatek/),
		).toBeVisible();

		// A real lifecycle notification must immediately remove all personal data.
		await application.evaluate(({ BrowserWindow }) => {
			BrowserWindow.getAllWindows()[0]!.webContents.send('session:changed', {
				revision: 101,
				status: 'signed-out',
				user: null,
				issue: null,
				connection: { name: 'Test', serverUrl: 'https://example.test' },
			});
		});
		await expect(
			page.getByRole('button', { name: 'Přihlásit se', exact: true }),
		).toBeVisible();
		await expect(
			page.getByRole('heading', { name: 'Složení portfolia' }),
		).toHaveCount(0);
		expect(errors).toEqual([]);
	} finally {
		try {
			await application?.close();
		} finally {
			await rm(userData, { recursive: true, force: true });
		}
	}
});
