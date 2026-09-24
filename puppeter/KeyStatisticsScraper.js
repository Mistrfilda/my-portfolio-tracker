import { PuppeteerScraperBase } from './PuppeteerScraperBase.js';

export class KeyStatisticsScraper extends PuppeteerScraperBase {
	async processEntry(page, entry, index) {
		const { id, name, currency } = entry;

		const selectors = [
			'#main-content-wrapper:has([data-testid="qsp-statistics"] table)',
			'::-p-xpath(//section[@data-testid="qsp-statistics"][.//table]/parent::section)',
			"::-p-xpath(/html/body/div[1]/div[4]/main/section/section/section/section)",
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
			const { textContent, html } = await page.evaluate(el => {
				const content = el.cloneNode(true);
				content.querySelectorAll('iframe, script, svg, style').forEach(node => node.remove());
				return { textContent: content.textContent, html: content.innerHTML };
			}, element);

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
