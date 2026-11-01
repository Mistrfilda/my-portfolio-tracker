import { expect, test, type Request } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

test('additional expense tags toggle through AJAX and retain the chart canvas', async ({ page }) => {
	await login(page);
	await page.goto('expense-overview/');
	const frontendErrors = watchFrontendErrors(page);
	let documentRequests = 0;
	const countDocuments = (request: Request): void => {
		if (request.isNavigationRequest() && request.frame() === page.mainFrame()) documentRequests++;
	};
	page.on('request', countDocuments);

	try {
		const tags = page.getByRole('region', { name: 'Doplňkové tagy' });
		const firstTag = tags.locator('a[aria-current]').first();
		await expect(firstTag).toBeVisible();
		const tagId = new URL(await firstTag.getAttribute('href') ?? '', page.url()).searchParams.get('selectedOtherTagId');
		expect(tagId).not.toBeNull();
		const canvas = await page.locator('canvas.chart').elementHandle();

		await firstTag.click();
		await expect(page).toHaveURL((url) => url.searchParams.get('selectedOtherTagId') === tagId);
		await expect(tags.locator('a[aria-current="true"]')).toHaveCount(1);
		await expect(page.locator('[data-filtered="true"] tbody tr').first()).toBeVisible();

		await tags.locator('a[aria-current="true"]').click();
		await expect(page).toHaveURL((url) => !url.searchParams.has('selectedOtherTagId'));
		await expect(tags.locator('a[aria-current="true"]')).toHaveCount(0);
		await expect(tags.getByRole('link', { name: 'Zrušit filtr', exact: true })).toHaveCount(0);

		await firstTag.click();
		await expect(tags.locator('a[aria-current="true"]')).toHaveCount(1);
		await tags.getByRole('link', { name: 'Zrušit filtr', exact: true }).click();
		await expect(tags.locator('a[aria-current="true"]')).toHaveCount(0);
		expect(await canvas!.evaluate((element) => element.isConnected)).toBe(true);
		expect(documentRequests).toBe(0);

		await page.setViewportSize({ width: 390, height: 844 });
		await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
		frontendErrors.assertNoErrors();
	} finally {
		page.off('request', countDocuments);
		frontendErrors.dispose();
	}
});

test('doughnut legend renders signed amounts as positive shares and refreshes via AJAX', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);
	let values = [-75, -25];
	await page.addInitScript(() => {
		const fillText = CanvasRenderingContext2D.prototype.fillText;
		CanvasRenderingContext2D.prototype.fillText = function (...args: Parameters<typeof fillText>) {
			if (this.canvas.matches('canvas.chart')) {
				const texts: string[] = JSON.parse(this.canvas.dataset.drawnLabels ?? '[]');
				if (!texts.includes(args[0])) texts.push(args[0]);
				this.canvas.dataset.drawnLabels = JSON.stringify(texts);
			}
			return fillText.apply(this, args);
		};
	});
	await page.route((url) => (url.searchParams.get('do') ?? '').endsWith('getChartData'), async (route) => {
		await route.fulfill({ json: {
			labels: ['První kategorie', 'Druhá kategorie'],
			tooltipSuffix: 'Kč',
			datasets: [{ label: 'Výdaje', data: values, backgroundColors: [], borderColors: [], stepped: false, weeklyValueLabels: false }],
		} });
	});

	try {
		await page.goto('expense-overview/');
		const canvas = page.locator('canvas.chart');
		await expect(canvas).toHaveAttribute('data-drawn-labels', /První kategorie · 75 %/);
		await expect(canvas).toHaveAttribute('data-drawn-labels', /Druhá kategorie · 25 %/);

		values = [-80, 20];
		await page.getByRole('link', { name: 'Leden', exact: true }).click();
		await expect(canvas).toHaveAttribute('data-drawn-labels', /První kategorie · 80 %/);
		await expect(canvas).toHaveAttribute('data-drawn-labels', /Druhá kategorie · 20 %/);

		values = [0, 0];
		await page.getByRole('link', { name: 'Únor', exact: true }).click();
		await expect(canvas).toHaveAttribute('data-drawn-labels', /První kategorie · 0 %/);
		await expect(canvas).not.toHaveAttribute('data-drawn-labels', /NaN|Infinity/);
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});
