import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import {
	_electron as electron,
	expect,
	test,
	type ElectronApplication,
} from '@playwright/test';
import { stocksFixture } from '../fixtures/stocks';
import { dashboardFixture } from '../fixtures/dashboard';
import type { StockPortfolioResult } from '../../src/shared/stocks';

test('shows holdings and purchase lots, filters and sorts, and clears personal data on account changes', async () => {
	const userData = await mkdtemp(join(tmpdir(), 'portfolio-stocks-test-'));
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
		await application.evaluate(
			({ ipcMain, BrowserWindow }, fixtures) => {
				const state = {
					revision: 100,
					status: 'signed-in',
					user: {
						id: 'fixture-owner',
						name: 'Test Investor',
						username: 'test',
					},
					issue: null,
					connection: { name: 'Test', serverUrl: 'https://example.test' },
				};
				ipcMain.removeHandler('session:get');
				ipcMain.handle('session:get', () => state);
				ipcMain.removeHandler('dashboard:get');
				ipcMain.handle('dashboard:get', () => fixtures.dashboard);
				ipcMain.removeHandler('stocks:get');
				ipcMain.handle(
					'stocks:get',
					() =>
						new Promise((resolve) => {
							Reflect.set(globalThis, 'resolveTestStocks', () =>
								resolve({ data: fixtures.stocks, issue: null }),
							);
						}),
				);
				BrowserWindow.getAllWindows()[0]!.webContents.send(
					'session:changed',
					state,
				);
			},
			{ dashboard: dashboardFixture(), stocks: stocksFixture() },
		);
		await page
			.getByRole('link', { name: 'Akcie a pozice', exact: true })
			.click();
		await expect(page.getByText('Načítám akcie a pozice…')).toBeVisible();
		await expect
			.poll(() =>
				application!.evaluate(() =>
					Reflect.has(globalThis, 'resolveTestStocks'),
				),
			)
			.toBe(true);
		await application.evaluate(() =>
			Reflect.get(globalThis, 'resolveTestStocks')(),
		);
		const respond = async (next: StockPortfolioResult) => {
			await application!.evaluate(({ ipcMain }, response) => {
				ipcMain.removeHandler('stocks:get');
				ipcMain.handle('stocks:get', () => response);
			}, next);
		};
		const ready = { data: stocksFixture(), issue: null } as const;
		await respond(ready);
		const holdings = page.getByRole('table', {
			name: 'Přehled držených akcií',
		});
		await expect(holdings.getByRole('row')).toHaveCount(3);
		await expect(
			page.getByRole('heading', {
				name: 'Pozice · Example Technology',
				exact: true,
			}),
		).toBeVisible();
		await expect(
			page
				.getByRole('table', { name: 'Jednotlivé nákupy Example Technology' })
				.getByRole('row'),
		).toHaveCount(3);
		await expect(
			page.getByText('Obchodní měna · USD', { exact: true }),
		).toBeVisible();
		await expect(
			page.getByText('Měna brokera · CZK', { exact: true }),
		).toBeVisible();
		await page.screenshot({
			path: test.info().outputPath('stocks-light.png'),
			animations: 'disabled',
		});

		const search = page.getByRole('searchbox', {
			name: 'Hledat akcii podle názvu nebo tickeru',
		});
		await search.fill('ceska');
		await expect(holdings.getByRole('row')).toHaveCount(2);
		await expect(
			page.getByRole('heading', {
				name: 'Pozice · Česká energie',
				exact: true,
			}),
		).toBeVisible();
		await expect(page.getByText('38 831 Kč', { exact: true })).toBeVisible();
		await search.fill('no-such-stock');
		await expect(
			page.getByText('Zadanému hledání neodpovídá žádná akcie.'),
		).toBeVisible();
		await expect(page.getByRole('heading', { name: /^Pozice ·/ })).toHaveCount(
			0,
		);
		await page.getByRole('button', { name: 'Vymazat filtry' }).click();
		await page
			.getByRole('combobox', { name: 'Obchodní měna' })
			.selectOption('EUR');
		await expect(holdings.getByRole('row')).toHaveCount(2);
		await page
			.getByRole('combobox', { name: 'Obchodní měna' })
			.selectOption('');
		await page
			.getByRole('combobox', { name: 'Řazení akcií' })
			.selectOption('profit-asc');
		await expect(holdings.getByRole('row').nth(1)).toContainText('ENRG');
		await page
			.getByRole('button', { name: 'Zobrazit pozice Example Technology' })
			.click();
		await expect(
			page.getByRole('heading', {
				name: 'Pozice · Example Technology',
				exact: true,
			}),
		).toBeVisible();
		await page
			.getByRole('heading', { name: 'Dividendový výnos před zdaněním' })
			.scrollIntoViewIfNeeded();
		await page.screenshot({
			path: test.info().outputPath('stock-positions-light.png'),
			animations: 'disabled',
		});

		await page.getByRole('link', { name: 'Nastavení', exact: true }).click();
		await page.getByRole('button', { name: 'Tmavý', exact: true }).click();
		await page
			.getByRole('link', { name: 'Akcie a pozice', exact: true })
			.click();
		await expect(holdings.getByRole('row')).toHaveCount(3);
		await page.screenshot({
			path: test.info().outputPath('stocks-dark.png'),
			animations: 'disabled',
		});
		await application.evaluate(({ BrowserWindow }) =>
			BrowserWindow.getAllWindows()[0]!.setSize(920, 640),
		);
		await expect.poll(() => page.evaluate(() => window.innerWidth)).toBe(920);
		expect(
			await page
				.locator('main')
				.evaluate((element) => element.scrollWidth <= element.clientWidth),
		).toBe(true);
		await page.screenshot({
			path: test.info().outputPath('stocks-small.png'),
			animations: 'disabled',
		});
		await holdings.scrollIntoViewIfNeeded();
		await page.screenshot({
			path: test.info().outputPath('stock-table-small.png'),
			animations: 'disabled',
		});

		const failed = { data: null, issue: 'network' } as const;
		await respond(failed);
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(page.getByText(/Zobrazená data pocházejí/)).toBeVisible();
		await expect(holdings.getByRole('row')).toHaveCount(3);
		await respond(ready);
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(page.getByRole('alert')).toHaveCount(0);
		await page.getByRole('link', { name: 'Nastavení', exact: true }).click();
		await respond(failed);
		await page
			.getByRole('link', { name: 'Akcie a pozice', exact: true })
			.click();
		await expect(page.getByRole('alert')).toHaveCount(1);
		await expect(holdings).toHaveCount(0);
		await expect(
			page.getByText('Zatím nemáte žádné otevřené akciové pozice.'),
		).toHaveCount(0);

		const empty = stocksFixture();
		empty.items = [];
		empty.total = {
			investedAmount: 0,
			currentValue: 0,
			profit: 0,
			profitPercent: 0,
		};
		await respond({ data: empty, issue: null });
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(
			page.getByText('Zatím nemáte žádné otevřené akciové pozice.'),
		).toBeVisible();
		await respond(ready);
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(holdings.getByRole('row')).toHaveCount(3);

		// The next account must never see the preceding account's cached portfolio.
		await respond(failed);
		await application.evaluate(({ BrowserWindow }) =>
			BrowserWindow.getAllWindows()[0]!.webContents.send('session:changed', {
				revision: 101,
				status: 'signed-in',
				user: { id: 'other-owner', name: 'Other Investor', username: 'other' },
				issue: null,
				connection: { name: 'Test', serverUrl: 'https://example.test' },
			}),
		);
		await expect(page.getByRole('alert')).toHaveCount(1);
		await expect(holdings).toHaveCount(0);
		await expect(page.getByText(/Zobrazená data pocházejí/)).toHaveCount(0);
		await respond(ready);
		await page.getByRole('button', { name: 'Obnovit', exact: true }).click();
		await expect(holdings.getByRole('row')).toHaveCount(3);
		await application.evaluate(({ BrowserWindow }) =>
			BrowserWindow.getAllWindows()[0]!.webContents.send('session:changed', {
				revision: 102,
				status: 'signed-out',
				user: null,
				issue: null,
				connection: { name: 'Test', serverUrl: 'https://example.test' },
			}),
		);
		await expect(
			page.getByRole('button', { name: 'Přihlásit se', exact: true }),
		).toBeVisible();
		await expect(holdings).toHaveCount(0);
		expect(errors).toEqual([]);
	} finally {
		try {
			await application?.close();
		} finally {
			await rm(userData, { recursive: true, force: true });
		}
	}
});
