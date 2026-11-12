import { expect, test, type Page } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

const contentRoute = /\/dashboard\/content(?:[/?]|$)/;

test('dashboard loads data after the mobile menu is ready', async ({ page }) => {
	test.setTimeout(60_000);
	await page.setViewportSize({ width: 390, height: 844 });
	const frontendErrors = watchFrontendErrors(page);
	const releaseContent = await holdDashboardContent(page);

	try {
		await login(page);
		await expect(page.getByRole('status')).toHaveText(/Načítání přehledu portfolia/);
		await expect(page.getByRole('heading', { name: 'Celkové hodnoty portfolia' })).toHaveCount(0);
		await page.getByRole('button', { name: 'Open sidebar' }).click();
		await expect(page.getByRole('navigation', { name: 'Sidebar' }).first()).toBeVisible();
		await page.getByRole('button', { name: 'Close sidebar' }).click();
		await expect(page.getByRole('navigation', { name: 'Sidebar' }).first()).toBeHidden();

		releaseContent();
		await expect(page.getByRole('heading', { name: 'Celkové hodnoty portfolia' })).toBeVisible({ timeout: 30_000 });
		await expect(page.getByRole('heading', { name: 'Výkonnost dle roků' })).toBeVisible();
		await expect(page.getByRole('status')).toBeHidden();

		// Alpine must initialize the controls inside the asynchronously inserted HTML.
		const collapseButton = page.getByRole('button', { name: 'Skrýt', exact: true }).first();
		await expect(collapseButton).toHaveAttribute('aria-expanded', 'true');
		await collapseButton.click();
		await expect(page.getByRole('button', { name: 'Zobrazit', exact: true }).first()).toHaveAttribute('aria-expanded', 'false');
		await expect(page.getByRole('main').getByRole('heading', { level: 1 })).toHaveCount(1);
		frontendErrors.assertNoErrors();
	} finally {
		releaseContent();
		await page.unrouteAll({ behavior: 'wait' });
		frontendErrors.dispose();
	}
});

test('mobile navigation works while dashboard data is still pending', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	const frontendErrors = watchFrontendErrors(page);
	const releaseContent = await holdDashboardContent(page);

	try {
		await login(page);
		await expect(page.getByRole('status')).toBeVisible();
		await page.getByRole('button', { name: 'Open sidebar' }).click();
		await page.getByRole('navigation', { name: 'Sidebar' }).first()
			.getByRole('link', { name: 'Měnový přehled', exact: true }).click();
		await expect(page).toHaveURL(/\/currency-overview\/?$/);
		await expect(page.getByRole('heading', { name: 'Výsledek převodu' })).toBeVisible();
		frontendErrors.assertNoErrors();
	} finally {
		releaseContent();
		await page.unrouteAll({ behavior: 'ignoreErrors' });
		frontendErrors.dispose();
	}
});

test('dashboard can retry a failed background response without reloading the page', async ({ page }) => {
	test.setTimeout(60_000);
	const frontendErrors = watchFrontendErrors(page);
	let contentRequests = 0;
	await page.route(contentRoute, async (route) => {
		contentRequests++;
		if (contentRequests === 1) {
			await route.fulfill({ json: {} });
		} else {
			await route.continue();
		}
	});

	try {
		await login(page);
		const originalDocument = await page.locator('html').elementHandle();
		await expect(page.getByRole('alert')).toHaveText(/Přehled portfolia se nepodařilo načíst/);
		await expect(page.getByRole('status')).toBeHidden();
		await page.getByRole('button', { name: 'Zkusit znovu' }).click();
		await expect(page.getByRole('heading', { name: 'Celkové hodnoty portfolia' })).toBeVisible({ timeout: 30_000 });
		await expect(page.getByRole('alert')).toBeHidden();
		expect(contentRequests).toBe(2);
		expect(await originalDocument!.evaluate((element) => element.isConnected)).toBe(true);
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('dashboard background data requires authentication', async ({ request }) => {
	const response = await request.get('dashboard/content', {
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	});
	const payload = await response.json();

	expect(payload.html).toBeUndefined();
	expect(new URL(payload.redirect).pathname).toMatch(/\/login\/?$/);
});

async function holdDashboardContent(page: Page): Promise<() => void> {
	let releaseContent: () => void = () => {};
	const pending = new Promise<void>((resolve) => {
		releaseContent = resolve;
	});
	await page.route(contentRoute, async (route) => {
		await pending;
		await route.continue();
	});
	return releaseContent;
}
