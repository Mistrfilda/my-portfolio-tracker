import { expect, test, type Page } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

async function openExpenseModal(page: Page) {
	await login(page);
	await page.goto('expense-overview/');
	await expect(page.getByRole('heading', { name: 'Přehled výdajů', exact: true })).toBeVisible();
	// Keep the development toolbar from covering controls at the bottom of the modal.
	await page.addStyleTag({ content: '#tracy-debug { display: none !important; }' });
	const link = page.locator('main a[href*="do=showModal"]').first();
	const targetUrl = new URL((await link.getAttribute('href'))!, page.url()).href;
	await link.locator('xpath=ancestor::*[@x-data][1]').getByRole('button').click();
	const [response] = await Promise.all([
		page.waitForResponse((response) => new URL(response.url()).searchParams.get('do') === 'showModal'),
		link.click(),
	]);
	const payload = await response.json() as { snippets: Record<string, string> };
	const snippet = Object.entries(payload.snippets).find(([, html]) => html.includes('x-data="modal()"'))!;
	const dialog = page.getByRole('dialog', { name: 'Výdaj', exact: true });
	await expect(dialog).toBeVisible();
	await expect(page).toHaveURL(targetUrl);
	return { dialog, snippet, expenseId: new URL(targetUrl).pathname.split('/').pop()! };
}

test('expense modal searches tags and sends the current ID on repeated saves', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);
	try {
		const { dialog, expenseId } = await openExpenseModal(page);
		const requests: URL[] = [];
		await page.route((url) => ['changeMainTag', 'addOtherTag'].includes(url.searchParams.get('do') ?? ''), async (route) => {
			requests.push(new URL(route.request().url()));
			await route.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
		});

		for (const action of [
			{ name: 'Změnit hlavní tag', component: 'expenseMainTag', control: '#expense-main-tag', signal: 'changeMainTag' },
			{ name: 'Přidat ostatní tag', component: 'addExpenseOtherTag', control: '#expense-other-tag', signal: 'addOtherTag' },
		]) {
			const input = dialog.getByRole('combobox', { name: action.name, exact: true });
			const save = dialog.locator(`[x-data^="${action.component}"]`).getByRole('button', { name: 'Uložit', exact: true });
			await expect(save).toBeDisabled();
			const options = await dialog.locator(action.control).evaluate((select: HTMLSelectElement) =>
				Array.from(select.options).filter((option) => !option.disabled).slice(0, 2)
					.map((option) => ({ value: option.value, label: option.label })),
			);
			expect(options).toHaveLength(2);
			for (const option of options) {
				await input.fill(option.label.toUpperCase());
				await dialog.getByRole('option', { name: option.label, exact: true }).click();
				await expect(input).toHaveValue(option.label);
				const expectedCount = requests.length + 1;
				await save.click();
				await expect.poll(() => requests.length).toBe(expectedCount);
				await expect(save).toBeEnabled();
				const request = requests[expectedCount - 1];
				expect(request.searchParams.get('do')).toBe(action.signal);
				expect(request.searchParams.get('tagId')).toBe(option.value);
				expect(request.searchParams.get('expenseId')).toBe(expenseId);
			}
		}
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('expense modal initializes searchable tags again after an AJAX snippet redraw', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);
	try {
		const { dialog, snippet } = await openExpenseModal(page);
		const options = await dialog.locator('#expense-other-tag').evaluate((select: HTMLSelectElement) =>
			Array.from(select.options).filter((option) => !option.disabled).slice(0, 2)
				.map((option) => ({ value: option.value, label: option.label })),
		);
		const requests: string[] = [];
		await page.route((url) => url.searchParams.get('do') === 'addOtherTag', async (route) => {
			requests.push(new URL(route.request().url()).searchParams.get('tagId')!);
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ snippets: { [snippet[0]]: snippet[1] } }),
			});
		});

		for (const [index, option] of options.entries()) {
			const input = dialog.getByRole('combobox', { name: 'Přidat ostatní tag', exact: true });
			const save = dialog.locator('[x-data^="addExpenseOtherTag"]').getByRole('button', { name: 'Uložit', exact: true });
			await input.fill(option.label);
			await input.press('ArrowDown');
			await input.press('Enter');
			await save.click();
			await expect.poll(() => requests.length).toBe(index + 1);
			await expect(input).toHaveValue('Vyberte tag');
			await expect(save).toBeDisabled();
			expect(requests[index]).toBe(option.value);
		}
		await dialog.getByRole('button', { name: 'Zavřít', exact: true }).click();
		await expect(dialog).toBeHidden();
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});
