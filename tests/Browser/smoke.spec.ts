import { expect, test, type Page } from '@playwright/test';

import { watchFrontendErrors } from './support/consoleErrors';
import { login } from './support/login';

type SidebarMenuPage = {
	label: string;
	href: string;
};

test('login page loads', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);

	try {
		await page.goto('login/');

		await expect(page.getByText('My portfolio tracker')).toBeVisible();
		await expect(page.locator('input[name="username"]')).toBeVisible();
		await expect(page.getByLabel('Heslo')).toBeVisible();
		await expect(page.getByRole('button', { name: 'Přihlásit se' })).toBeVisible();
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('user can log in and sees dashboard', async ({ page }) => {
	const frontendErrors = watchFrontendErrors(page);

	try {
		await login(page);
		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

test('all sidebar menu pages render without frontend errors', async ({ page }) => {
	await login(page);
	const frontendErrors = watchFrontendErrors(page);

	try {
		const menuPages = await getSidebarMenuPages(page);

		expect(menuPages).not.toEqual([]);

		for (const menuPage of menuPages) {
			await test.step(`${menuPage.label} page renders`, async () => {
				await page.goto(menuPage.href);
				const main = page.getByRole('main');

				await expect(main).toBeVisible();
				await expect(main.getByRole('heading', { level: 1 }).first()).toBeVisible();
			});
		}

		frontendErrors.assertNoErrors();
	} finally {
		frontendErrors.dispose();
	}
});

async function getSidebarMenuPages(page: Page): Promise<SidebarMenuPage[]> {
	return await page.locator('nav[aria-label="Sidebar"]').last().locator('a').evaluateAll((links) => {
		const menuPages: SidebarMenuPage[] = [];
		const seenHrefs = new Set<string>();

		for (const link of links) {
			if (!(link instanceof HTMLAnchorElement)) {
				continue;
			}

			const href = link.href;
			const label = link.innerText.trim().replace(/\s+/g, ' ');

			if (label === '' || href === '' || seenHrefs.has(href)) {
				continue;
			}

			seenHrefs.add(href);
			menuPages.push({ label, href });
		}

		return menuPages;
	});
}