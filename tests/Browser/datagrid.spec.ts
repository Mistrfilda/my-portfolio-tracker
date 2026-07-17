import { expect, test } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

test('stock asset filters and column selection work across AJAX updates', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('stock-asset/');

		const ticker = (await page.locator('tbody tr').first().locator('td').nth(1).innerText()).trim();

		await page.getByRole('button', { name: 'Filtry' }).click();
		await page.locator('input[name$="dg_fi_ticker"]').fill(ticker);
		await page.getByRole('button', { name: 'Použít filtry' }).click();

		await expect(page.getByText(`Ticker: ${ticker}`, { exact: true })).toBeVisible();
		await expect(page.locator('tbody tr').first()).toContainText(ticker);

		const dataSourceHeader = page.getByRole('columnheader', { name: 'Zdroj dat' });
		await expect(dataSourceHeader).toBeHidden();

		await page.getByRole('button', { name: 'Sloupce' }).click();
		await page.getByRole('menu').getByLabel('Zdroj dat').click();
		await expect(dataSourceHeader).toBeVisible();

		await page.getByRole('columnheader', { name: 'Jméno' }).getByRole('link').click();
		await expect(dataSourceHeader).toBeVisible();

		await page.reload();
		await expect(dataSourceHeader).toBeHidden();

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('stock position supports status filter and compact mobile cards', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('stock-position/');

		await page.getByRole('button', { name: 'Filtry' }).click();
		await page.locator('select[name$="dg_fi_status"]').selectOption('null');
		await page.getByRole('button', { name: 'Použít filtry' }).click();

		await expect(page.getByText('Stav pozice: Otevřené', { exact: true })).toBeVisible();

		await page.getByRole('link', { name: 'Odebrat filtr Stav pozice' }).click();
		await expect(page.getByText('Stav pozice: Otevřené', { exact: true })).toBeHidden();

		await page.setViewportSize({ width: 390, height: 844 });

		const mobileGrid = page.locator('ul.sm\\:hidden[role="list"]');
		await expect(mobileGrid).toBeVisible();
		await expect(mobileGrid.getByText('Akcie', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Zisk/ztráta v %', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Konečná cena za kus', { exact: true })).toHaveCount(0);
		await expect(mobileGrid.getByText('Aktuální hodnota pozice', { exact: true })).toHaveCount(0);

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});
