import { PuppeteerScraperBase } from './PuppeteerScraperBase.js';

export class KeyStatisticsScraper extends PuppeteerScraperBase {
	async processEntry(page, entry, index) {
		const { id, name, currency } = entry;

		const selectors = [
			"::-p-xpath(/html/body/div[2]/div[3]/main/section/section/section/section)",
			"::-p-xpath(/html/body/div[2]/main/section/section/section/section)"
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
			console.error(`Failed to find key statistics table for ${name} with any selector`);
			return null;
		}

		try {
			await page.evaluate((selector) => {
				const mainElement = document.evaluate(
					selector.replace('::-p-xpath(', '').replace(')', ''),
					document,
					null,
					XPathResult.FIRST_ORDERED_NODE_TYPE,
					null
				).singleNodeValue;

				if (mainElement) {
					const unwantedElements = mainElement.querySelectorAll('iframe, script, svg, style');
					unwantedElements.forEach(el => el.remove());
				}
			}, usedSelector);

			const textContent = await page.evaluate(el => el.textContent, element);
			const html = await page.evaluate(el => el.innerHTML, element);

			console.log(entry);
			console.log(`Key statistics for ${name} (using ${usedSelector}):`, textContent.substring(0, 200) + '...');

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
