import { expect, test, type Page } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

async function openStockAssetEditor(page: Page): Promise<void> {
	await login(page);
	await page.goto('stock-asset/');
	await page.getByRole('link', { name: 'Editovat', exact: true }).first().click();
	await expect(page).toHaveURL(/stock-asset-edit/);
	await expect(page.getByRole('heading', { name: /^Úprava akcie/ })).toBeVisible();
}

test('searchable select filters labels, submits IDs, clears optional values and resets', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);
	try {
		await openStockAssetEditor(page);
		const control = page.locator('select[name="industry"]');
		const input = page.getByRole('combobox', { name: 'Industry', exact: true });
		const initial = await control.evaluate((select: HTMLSelectElement) => ({
			value: select.value,
			label: select.selectedOptions[0].label,
			prompt: select.options[0].label,
		}));
		await expect(input).toHaveValue(initial.label);

		await input.fill('sPeCiAlTy ChEmIcAlS');
		await expect(page.getByRole('option')).toHaveCount(1);
		await expect(page.getByRole('option', { name: 'Specialty Chemicals', exact: true })).toBeVisible();
		await input.press('ArrowDown');
		await input.press('Enter');
		await expect(input).toHaveValue('Specialty Chemicals');
		const selected = await control.evaluate((select: HTMLSelectElement) => ({
			value: select.value,
			label: select.selectedOptions[0].label,
			submitted: new FormData(select.form!).getAll(select.name),
		}));
		expect(selected.label).toBe('Specialty Chemicals');
		expect(selected.value).not.toBe(selected.label);
		expect(selected.submitted).toEqual([selected.value]);

		await input.fill('No matching industry for select regression');
		await expect(page.getByRole('status')).toHaveText('Nebyl nalezen žádný výsledek');
		await input.press('Escape');
		await expect(input).toHaveValue('Specialty Chemicals');
		await expect(control).toHaveValue(selected.value);

		await input.click();
		await page.getByRole('option', { name: initial.prompt, exact: true }).click();
		await expect(control).toHaveValue('');
		await expect(input).toHaveValue(initial.prompt);
		expect(await control.evaluate((select: HTMLSelectElement) => new FormData(select.form!).getAll(select.name))).toEqual(['']);

		await control.evaluate((select: HTMLSelectElement) => select.form!.reset());
		await expect(control).toHaveValue(initial.value);
		await expect(input).toHaveValue(initial.label);
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('searchable select distinguishes identical labels and follows disabled state', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);
	try {
		await openStockAssetEditor(page);
		const control = page.locator('select[name="industry"]');
		const input = page.getByRole('combobox', { name: 'Industry', exact: true });
		await control.evaluate((select: HTMLSelectElement) => {
			select.add(new Option('Duplicate label for select regression', 'first-id'));
			select.add(new Option('Duplicate label for select regression', 'second-id'));
		});
		await input.fill('duplicate label for select regression');
		const options = page.getByRole('option', { name: 'Duplicate label for select regression', exact: true });
		await expect(options).toHaveCount(2);
		await options.nth(1).click();
		await expect(control).toHaveValue('second-id');
		await expect(input).toHaveValue('Duplicate label for select regression');

		await input.click();
		await input.fill('Duplicate label for select regression');
		await input.press('ArrowDown');
		await input.press('Enter');
		await expect(control).toHaveValue('first-id');

		await control.evaluate((select: HTMLSelectElement) => { select.disabled = true; });
		await expect(input).toBeDisabled();
		expect(await control.evaluate((select: HTMLSelectElement) => new FormData(select.form!).has(select.name))).toBe(false);
		await control.evaluate((select: HTMLSelectElement) => { select.disabled = false; });
		await expect(input).toBeEnabled();
		await expect(input).toHaveValue('Duplicate label for select regression');
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('searchable select follows native changes and focuses the visible field on validation errors', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);
	try {
		await openStockAssetEditor(page);
		const control = page.locator('select[name="currency"]');
		const input = page.getByRole('combobox', { name: 'Měna', exact: true });
		await control.selectOption('CZK');
		await expect(input).toHaveValue('CZK');
		expect(await control.evaluate((select: HTMLSelectElement) => {
			select.value = '';
			select.dispatchEvent(new Event('change', { bubbles: true }));
			return select.checkValidity();
		})).toBe(false);
		await expect(input).toBeFocused();
		await expect(input).toHaveAttribute('aria-invalid', 'true');
		await input.fill('USD');
		await page.getByRole('option', { name: 'USD', exact: true }).click();
		await expect(control).toHaveValue('USD');
		await expect(input).toHaveAttribute('aria-invalid', 'false');
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});
