import { expect, test, type Page } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

test('monthly income dashboard keeps the primary progress and detailed scenarios accessible', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('work-monthly-income/');

		await expect(page.getByRole('heading', { name: 'Aktuální měsíc' })).toBeVisible();
		await expect(page.getByText('Vyděláno', { exact: true })).toBeVisible();
		await expect(page.getByText('Odpracováno', { exact: true })).toBeVisible();
		await expect(page.getByText('Hodinová sazba', { exact: true })).toBeVisible();
		await expect(page.getByRole('heading', { name: 'Nejbližší cíle' })).toBeVisible();
		await expect(page.getByRole('heading', { name: /Přehled za rok/ })).toBeVisible();

		const goalsDetails = page.locator('details').filter({ hasText: 'Kompletní plán cílů' });
		const incomeDetails = page.locator('details').filter({ hasText: 'Kalkulačka příjmu' });
		await expect(goalsDetails).not.toHaveAttribute('open');
		await expect(incomeDetails).not.toHaveAttribute('open');
		await page.getByText('Kompletní plán cílů', { exact: true }).click();
		await expect(goalsDetails).toHaveAttribute('open');

		await page.setViewportSize({ width: 390, height: 844 });
		await expectNoHorizontalPageOverflow(page);
		await expect(page.getByText('Vyděláno', { exact: true })).toBeVisible();

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('currency overview converts an amount and switches the responsive quick table', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('currency-overview/');

		const amountInput = page.getByLabel('Částka');
		const sourceCurrency = page.getByLabel('Zdrojová měna');
		await expect(amountInput).toHaveValue('1000');
		await expect(sourceCurrency).toHaveValue('CZK');
		await expect(page.getByRole('heading', { name: 'Výsledek převodu' })).toBeVisible();

		await amountInput.fill('');
		await expect(page.getByRole('button', { name: 'Převést' })).toBeDisabled();
		await amountInput.fill('2500');
		await sourceCurrency.selectOption('EUR');
		await page.getByRole('button', { name: 'Převést' }).click();
		await expect(page.getByLabel('Částka')).toHaveValue('2500');
		await expect(page.getByLabel('Zdrojová měna')).toHaveValue('EUR');

		const gbpTab = page.getByRole('tab', { name: 'GBP' });
		await gbpTab.click();
		await expect(gbpTab).toHaveAttribute('aria-selected', 'true');
		await expect(page.getByRole('tabpanel', { name: 'GBP' })).toBeVisible();

		await page.setViewportSize({ width: 390, height: 844 });
		await expectNoHorizontalPageOverflow(page);
		await expect(page.getByRole('button', { name: 'Převést' })).toBeVisible();

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

async function expectNoHorizontalPageOverflow(page: Page): Promise<void> {
	await expect.poll(async () => await page.evaluate(() => {
		return document.documentElement.scrollWidth - document.documentElement.clientWidth;
	})).toBeLessThanOrEqual(1);
}
