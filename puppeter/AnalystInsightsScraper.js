import { PuppeteerScraperBase } from './PuppeteerScraperBase.js';

export class AnalystInsightsScraper extends PuppeteerScraperBase {
	async processEntry(page, entry, index) {
		const { id, name, ticker } = entry;

		const selectors = [
			"::-p-xpath(/html/body/div[2]/div[3]/main/section/section/section/section/section[2]/div/section[1])",
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
			}
		}

		if (!element) {
			console.error(`Failed to find Analyst Price Targets for ${name} with any selector`);
			return null;
		}

		try {
			const html = await page.evaluate(el => el.innerHTML, element);
			const textContent = await page.evaluate(el => el.textContent, element);

			console.log(`Successfully extracted Analyst Price Targets for ${name}`);

			return {
				id,
				name,
				ticker,
				html,
				textContent,
				usedSelector
			};
		} catch (error) {
			console.error(`Failed to extract content from element for ${name}:`, error);
			return null;
		}
	}
}
