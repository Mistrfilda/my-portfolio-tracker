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

test('Portu position grid keeps key values visible and moves secondary data to column selection', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('portu-position/positions/9c88789f-cfaa-480e-babb-aa4df75a9671');

		const grid = page.locator('div[x-data^="datagrid("]').first();
		await expect(grid.getByRole('button', { name: 'Sloupce' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Datum vzniku' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Celková investovaná částka' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Aktuální hodnota pozice' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Zisk/ztráta v %' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Úvodní vklad' })).toBeHidden();

		await grid.getByRole('button', { name: 'Akce' }).first().click();
		await expect(grid.getByRole('menuitem', { name: 'Editovat' }).first()).toBeVisible();
		await expect(grid.getByRole('menuitem', { name: 'Hodnoty portfolia' }).first()).toBeVisible();
		await grid.getByRole('menuitem', { name: 'Editovat' }).first().click({ trial: true });

		await page.setViewportSize({ width: 390, height: 844 });
		const mobileGrid = grid.locator('ul.sm\\:hidden[role="list"]');
		await expect(mobileGrid).toBeVisible();
		await expect(mobileGrid.getByText('Datum vzniku', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Úvodní vklad', { exact: true })).toHaveCount(0);
		await expect(mobileGrid.getByText('Měsíční vklad', { exact: true })).toHaveCount(0);

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('dividend grid uses compact dates and status badges without saturated row backgrounds', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('stock-asset-dividend-record/');

		const grid = page.locator('div[x-data^="datagrid("]').first();
		await expect(grid.getByRole('button', { name: 'Sloupce' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Ex date' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Celková hodnota po zdanění' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Stav' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Datum vyplacení' })).toBeHidden();
		await expect(grid.getByRole('columnheader', { name: 'Celková hodnota před zdaněním' })).toBeHidden();

		const firstRow = grid.locator('tbody tr').first();
		await expect(firstRow).not.toHaveClass(/bg-(blue|emerald)-300/);
		await expect(firstRow.locator('td').nth(1)).toHaveText(/^\s*\d{4}-\d{2}-\d{2}\s*$/);
		await expect(firstRow.getByText(/Očekávaná|Vyplacená/)).toBeVisible();

		await page.setViewportSize({ width: 390, height: 844 });
		const mobileGrid = grid.locator('ul.sm\\:hidden[role="list"]');
		await expect(mobileGrid.getByText('Stav', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Datum vyplacení', { exact: true })).toHaveCount(0);
		await expect(mobileGrid.getByText('Počet držených akcií (ex date)', { exact: true })).toHaveCount(0);

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('KB expense grid prioritizes transaction data and groups row actions', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('expense/kb');

		const grid = page.locator('div[x-data^="datagrid("]').first();
		await expect(grid.getByRole('button', { name: 'Sloupce' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Datum transakce' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Hodnota' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Hlavní tag' })).toBeVisible();
		await expect(grid.getByRole('columnheader', { name: 'Datum zúčtování' })).toBeHidden();
		await expect(grid.getByRole('columnheader', { name: 'Zdroj' })).toBeHidden();

		await grid.getByRole('button', { name: 'Akce' }).first().click();
		await expect(grid.getByRole('menuitem', { name: 'Detail' }).first()).toBeVisible();
		await expect(grid.getByRole('menuitem', { name: 'Editovat' }).first()).toBeVisible();
		await expect(grid.getByRole('menuitem', { name: 'Duplikovat' }).first()).toBeVisible();

		await page.setViewportSize({ width: 390, height: 844 });
		const mobileGrid = grid.locator('ul.sm\\:hidden[role="list"]');
		await expect(mobileGrid.getByText('Datum transakce', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Hodnota', { exact: true }).first()).toBeVisible();
		await expect(mobileGrid.getByText('Zdroj', { exact: true })).toHaveCount(0);
		await expect(mobileGrid.getByText('Z bankovního účtu', { exact: true })).toHaveCount(0);

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});
