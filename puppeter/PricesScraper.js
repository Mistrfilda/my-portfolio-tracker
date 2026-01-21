import { PuppeteerScraperBase } from './PuppeteerScraperBase.js';

export class PricesScraper extends PuppeteerScraperBase {
	async processEntry(page, entry, index) {
		const { id, name, currency } = entry;

		const selectors = [
			"::-p-xpath(/html/body/div[2]/main/section/section/section/section/section[1]/div[2]/div[1]/section/div/section/div[1]/div[1]/span)",
			"::-p-xpath(/html/body/div[2]/div[3]/main/section/section/section/section/section[1]/div[2]/div[1]/section/div/section/div[1]/div[1]/span)",
			"::-p-xpath(/html/body/div[2]/div[3]/main/section/section/section/section/section[1]/div[2]/div[1]/section/div/section/div[1]/span[1])"
		];

		let element = null;
		let usedSelector = null;

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

		if (!element) {
			console.error(`Failed to find price element for ${name} with any selector`);
			return null;
		}

		try {
			const price = await page.evaluate(element => element.textContent, element);

			console.log(entry);
			console.log(`Price for ${name} (using ${usedSelector}):`, price);

			return {
				id,
				name,
				currency,
				price,
				usedSelector // Pro debug účely
			};
		} catch (error) {
			console.error(`Failed to extract price from element for ${name}:`, error);
			return null;
		}
	}
}
