import { expect, type Page } from '@playwright/test';

import { getBrowserTestEnv } from './env';

export async function login(page: Page): Promise<void> {
	const env = getBrowserTestEnv();

	await page.goto('login/');
	await page.locator('input[name="username"]').fill(env.username);
	await page.getByLabel('Heslo').fill(env.password);
	await page.getByRole('button', { name: 'Přihlásit se' }).click();

	await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}