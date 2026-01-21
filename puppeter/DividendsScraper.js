import { PuppeteerScraperBase } from './PuppeteerScraperBase.js';

export class DividendsScraper extends PuppeteerScraperBase {
	async processEntry(page, entry, index) {
		const { id, name, currency } = entry;

		const selectors = [
			"::-p-xpath(/html/body/div[2]/main/section/section/section/section/div[1]/div[3]/table)",
			"::-p-xpath(/html/body/div[2]/main/section/section/section/section/div[2]/div[3]/table)",
			"::-p-xpath(/html/body/div[2]/div[3]/main/section/section/section/section/div[1]/div[3]/table)",
			"::-p-xpath(/html/body/div[1]/div[4]/main/section/section/section/section/div[1]/div[3]/table)"
		];

		let element = null;
		let usedSelector = null;

		// Pokus o najití elementu s různými selektory
		for (const selector of selectors) {
			try {
				console.log(`Trying selector for ${name}: ${selector}`);
				element = await page.waitForSelector(selector, { timeout: 5000 });
				usedSelector = selector;
				console.log(`Success with selector: ${selector}`);
				break;
			} catch (selectorError) {
				console.log(`Selector failed for ${name}: ${selector} - ${selectorError.message}`);
				continue;
			}
		}

		// Pokud se nepodařilo najít element žádným selektorem
		if (!element) {
			console.error(`Failed to find dividend table for ${name} with any selector`);
			return null;
		}

		try {
			const textContent = await page.evaluate(el => el.textContent, element);
			const html = await page.evaluate(el => el.innerHTML, element);

			console.log(`Text content for ${name} (using ${usedSelector}):`, textContent);

			return {
				id,
				name,
				currency,
				textContent,
				html,
				usedSelector // Pro debug účely
			};
		} catch (error) {
			console.error(`Failed to extract content from element for ${name}:`, error);
			return null;
		}
	}
}
