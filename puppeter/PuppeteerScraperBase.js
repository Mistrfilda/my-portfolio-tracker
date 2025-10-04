import puppeteer from 'puppeteer';
import * as path from "node:path";
import fs from 'fs';
import { fileURLToPath } from 'url';

export class PuppeteerScraperBase {
	constructor() {
		const __filename = fileURLToPath(import.meta.url);
		this.__dirname = path.dirname(__filename);

		this.browserConfig = {
			// headless: false,
			// devtools: true,
			headless: true,
			slowMo: 100,
			browser: "firefox",
			executablePath: "/usr/bin/firefox",
			args: [
				'--no-sandbox',
				'--disable-setuid-sandbox',
				'--disable-gpu',
				'--disable-dev-shm-usage',
				'--single-process',
				'--disable-background-timer-throttling',
				'--disable-extensions',
				'--disable-sync',
				'--memory-pressure-off',
				'--max-old-space-size=512',
				'--disable-web-security',
				'--disable-features=site-per-process',
			],
		};
	}

	async loadJsonFile(filePath) {
		return new Promise((resolve, reject) => {
			fs.readFile(filePath, 'utf8', (err, data) => {
				if (err) {
					reject(err);
				} else {
					resolve(JSON.parse(data));
				}
			});
		});
	}

	async saveJsonToFile(filePath, jsonData) {
		try {
			await fs.promises.writeFile(filePath, JSON.stringify(jsonData, null, 2), 'utf8');
			console.log(`Result saved to: ${filePath}`);
		} catch (error) {
			throw new Error(`Error writing JSON to file: ${error.message}`);
		}
	}

	async handleCookieConsent(page, isFirstEntry) {
		if (isFirstEntry) {
			try {
				await page.waitForSelector('.consent-overlay');
				await page.click('.consent-overlay .accept-all');
			} catch (e) {
				console.log('Cookie has been authorized');
			}
		}
	}

	async setupPage(page) {
		await page.setViewport({ width: 1080, height: 1024 });
	}

	async processData(entries) {
		const result = [];

		if (!Array.isArray(entries)) {
			throw new TypeError('The provided input is not an array');
		}

		const RESTART_BROWSER_AFTER = 5;
		const SAVE_PROGRESS_AFTER = 5;
		let browser;

		try {
			for (const [index, entry] of entries.entries()) {
				try {
					if (index % RESTART_BROWSER_AFTER === 0) {
						if (browser) {
							console.log(`Restarting browser after ${RESTART_BROWSER_AFTER} entries...`);
							await browser.close();
							await this.delay(2000);
						}
						browser = await puppeteer.launch(this.browserConfig);
						console.log('Browser launched/restarted');
					}

					const { id, name, currency, url } = entry;
					console.log(`Processing [${index + 1}/${entries.length}]: ${name}`);

					const page = await browser.newPage();

					await page.setDefaultTimeout(30000);
					await page.setDefaultNavigationTimeout(30000);

					try {
						await page.goto(url, { timeout: 30000, waitUntil: 'domcontentloaded' });
						await this.setupPage(page);
						await this.handleCookieConsent(page, index % RESTART_BROWSER_AFTER === 0);

						const processedData = await this.processEntry(page, entry, index);
						if (processedData) {
							result.push(processedData);
						}

						if ((index + 1) % SAVE_PROGRESS_AFTER === 0) {
							await this.saveProgress(result);
						}
					} catch (pageError) {
						console.error(`Error processing page for entry ${name} (ID: ${id}):`, pageError);
					} finally {
						await page.removeAllListeners();
						await page.close();
					}
				} catch (entryError) {
					console.error(`Error processing entry:`, entryError);
				}

				await this.delay(5000);
			}
		} catch (browserError) {
			console.error('Error launching browser or during processing:', browserError);
		} finally {
			if (browser) {
				await browser.close();
			}
		}

		return result;
	}

	async saveProgress(result) {
		if (this.tempOutputPath) {
			try {
				await this.saveJsonToFile(this.tempOutputPath, result);
				console.log(`Progress saved: ${result.length} entries`);
			} catch (e) {
				console.error('Failed to save progress:', e);
			}
		}
	}

	async delay(ms) {
		return new Promise(resolve => setTimeout(resolve, ms));
	}

	async processEntry(page, entry, index) {
		throw new Error('processEntry method must be implemented by subclass');
	}

	async run(inputFileName, outputFileName) {
		const filePath = path.join(this.__dirname, `/files/requests/${inputFileName}`);
		const outputFilePath = path.join(this.__dirname, `/files/results/${outputFileName}`);
		const tempOutputPath = path.join(this.__dirname, `/files/results/temp_${outputFileName}`);
		this.tempOutputPath = path.join(this.__dirname, `/files/results/temp_${outputFileName}`);

		try {
			const inputData = await this.loadJsonFile(filePath);

			let result = [];
			try {
				result = await this.loadJsonFile(tempOutputPath);
				console.log(`Loaded ${result.length} previous results from temp file`);
			} catch (e) {
				console.log('No previous temp results found, starting fresh');
			}

			const processedIds = new Set(result.map(r => r.id));
			const remainingData = inputData.filter(entry => !processedIds.has(entry.id));

			console.log(`Processing ${remainingData.length} remaining entries...`);

			const newResults = await this.processData(remainingData);
			result = [...result, ...newResults];
			await this.saveJsonToFile(outputFilePath, result);

			try {
				await fs.promises.unlink(tempOutputPath);
			} catch (e) {
				// its k
			}

			process.exit(0);
		} catch (error) {
			console.error('Error:', error);
			process.exit(1);
		}
	}
}
