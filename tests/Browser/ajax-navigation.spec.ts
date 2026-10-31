import { expect, test, type Request } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

test('expense month changes refresh chart data without replacing its canvas', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);

	try {
		await login(page);
		await page.goto('expense-overview/');
		await expect(page.getByRole('heading', { name: 'Přehled výdajů', exact: true })).toBeVisible();
		await expect(page.locator('[id$="--spinner"]')).toBeHidden();
		const originalCanvas = await page.locator('canvas.chart').elementHandle();
		expect(originalCanvas).not.toBeNull();

		for (const month of [
			{ label: 'Leden', value: '1' },
			{ label: 'Únor', value: '2' },
			{ label: 'Zobrazit celý rok', value: null },
		]) {
			const [chartResponse] = await Promise.all([
				page.waitForResponse((response) => {
					const url = new URL(response.url());
					return (url.searchParams.get('do') ?? '').endsWith('getChartData')
						&& url.searchParams.get('originalRequestdo') === 'setMonth'
						&& url.searchParams.get('originalRequestselectedMonth') === month.value;
				}),
				page.getByRole('link', { name: month.label, exact: true }).click(),
			]);

			expect(chartResponse.ok()).toBeTruthy();
			await chartResponse.finished();
			await expect(page).toHaveURL((url) => url.searchParams.get('selectedMonth') === month.value);
			await expect(page.locator('canvas.chart')).toBeVisible();
			expect(await originalCanvas!.evaluate((canvas) => canvas.isConnected)).toBe(true);
		}

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('AJAX history restores filters and Alpine controls without a document reload', async ({ page }) => {
	await login(page);
	await page.goto('stock-asset/');
	const frontendErrors = watchFrontendErrors(page);
	let documentRequests = 0;
	const countDocumentRequests = (request: Request): void => {
		if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
			documentRequests++;
		}
	};
	page.on('request', countDocumentRequests);

	try {
		const ticker = (await page.locator('tbody tr').first().locator('td').nth(1).innerText()).trim();
		const tickerFilter = page.locator('input[name$="dg_fi_ticker"]');
		const filterBadge = page.getByText(`Ticker: ${ticker}`, { exact: true });

		await page.getByRole('button', { name: 'Filtry', exact: true }).click();
		await tickerFilter.fill(ticker);
		await page.getByRole('button', { name: 'Použít filtry', exact: true }).click();
		await expect(filterBadge).toBeVisible();

		await page.goBack();
		await expect(filterBadge).toBeHidden();
		await page.goForward();
		await expect(filterBadge).toBeVisible();

		await page.getByRole('button', { name: /^Filtry/ }).click();
		await expect(tickerFilter).toBeVisible();
		await expect(tickerFilter).toHaveValue(ticker);
		await page.getByRole('link', { name: 'Zrušit vše', exact: true }).click();
		await expect(filterBadge).toBeHidden();

		expect(documentRequests).toBe(0);
		frontendErrors.assertNoErrors();
	} finally {
		page.off('request', countDocumentRequests);
		frontendErrors.dispose();
	}
});
