import puppeteer from 'puppeteer';
import * as path from "node:path";
import fs from 'fs';
import { fileURLToPath } from 'url';

export class PuppeteerScraperBase {
	constructor() {
		const __filename = fileURLToPath(import.meta.url);
		this.__dirname = path.dirname(__filename);
		this.debugHtml = process.argv.includes('--debughtml');
		const dataDirIndex = process.argv.indexOf('--data-dir');
		if (dataDirIndex !== -1 && !process.argv[dataDirIndex + 1]) {
			throw new Error('Missing --data-dir argument');
		}
		this.dataDirectory = dataDirIndex === -1 ? path.join(this.__dirname, 'files') : path.resolve(process.argv[dataDirIndex + 1]);
		this.strict = process.argv.includes('--strict');

		this.browserConfig = {
			// headless: false,
			// devtools: true,
			headless: true,
			slowMo: 100,
			browser: "chrome",
			executablePath: fs.existsSync("/usr/bin/chromium") ? "/usr/bin/chromium" : puppeteer.executablePath(),
			args: [
				'--no-sandbox',
				'--disable-setuid-sandbox',
				'--disable-gpu',
				'--disable-dev-shm-usage',
				'--disable-background-timer-throttling',
				'--disable-extensions',
				'--disable-sync',
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
				await Promise.race([
					page.waitForSelector('.consent-overlay', { hidden: true, timeout: 5000 }).catch(() => {}),
					page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 5000 }).catch(() => {}),
					this.delay(5000)
				]);
			} catch (e) {
				console.log('Cookie has been authorized');
			}
		}
	}

	async setupPage(page) {
		await page.setViewport({ width: 1080, height: 1024 });
	}

	async processData(entries, onProgressCallback = null) {
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
					const downloadedAt = Math.floor(Date.now() / 1000);
					console.log(`Processing [${index + 1}/${entries.length}]: ${name}`);

					const page = await browser.newPage();

					await page.setDefaultTimeout(30000);
					await page.setDefaultNavigationTimeout(30000);

					try {
						await page.goto(url, { timeout: 30000, waitUntil: 'networkidle2' });
						await this.setupPage(page);
						await this.handleCookieConsent(page, index % RESTART_BROWSER_AFTER === 0);

						const processedData = await this.processEntry(page, entry, index);
						if (processedData) {
							result.push({ ...processedData, downloadedAt });
						} else if (this.debugHtml) {
							await this.saveDebugHtml(page, entry);
						}

						if (onProgressCallback && (index + 1) % SAVE_PROGRESS_AFTER === 0) {
							await onProgressCallback(result);
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

			if (onProgressCallback && result.length > 0) {
				await onProgressCallback(result);
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

	async saveDebugHtml(page, entry) {
		try {
			const debugDir = path.join(this.dataDirectory, 'debug');
			await fs.promises.mkdir(debugDir, { recursive: true });

			const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
			const safeName = (entry.name || entry.id || 'unknown').replace(/[^a-zA-Z0-9_-]/g, '_');
			const fileName = `${safeName}_${timestamp}.html`;
			const filePath = path.join(debugDir, fileName);

			const html = await page.content();
			await fs.promises.writeFile(filePath, html, 'utf8');
			console.log(`Debug HTML saved to: ${filePath}`);
		} catch (error) {
			console.error(`Failed to save debug HTML for ${entry.name || entry.id}:`, error.message);
		}
	}

	async processEntry(page, entry, index) {
		throw new Error('processEntry method must be implemented by subclass');
	}

	async run(inputFileName, outputFileName) {
		const filePath = path.join(this.dataDirectory, 'requests', inputFileName);
		const outputFilePath = path.join(this.dataDirectory, 'results', outputFileName);
		const tempOutputPath = path.join(this.dataDirectory, 'results', `temp_${outputFileName}`);
		this.tempOutputPath = tempOutputPath;

		try {
			const inputData = await this.loadJsonFile(filePath);

			let result = [];
			try {
				result = await this.loadJsonFile(tempOutputPath);
				console.log(`Loaded ${result.length} previous results from temp file`);
			} catch (e) {
				console.log('No previous temp results found, starting fresh');
			}

			const requestedIds = new Set(inputData.map(entry => entry.id));
			const requestTime = Math.floor((await fs.promises.stat(filePath)).mtimeMs / 1000);
			result = result.filter(row => requestedIds.has(row.id) && Number.isInteger(row.downloadedAt) && row.downloadedAt >= requestTime);
			const processedIds = new Set(result.map(r => r.id));
			const remainingData = inputData.filter(entry => !processedIds.has(entry.id));

			console.log(`Processing ${remainingData.length} remaining entries...`);

			const newResults = await this.processData(remainingData, async (partialResult) => {
				const currentResults = [...result, ...partialResult];
				await this.saveJsonToFile(tempOutputPath, currentResults);
			});

			result = [...result, ...newResults];
			await this.saveJsonToFile(outputFilePath, result);

			if (newResults.length === remainingData.length) {
				try {
					await fs.promises.unlink(tempOutputPath);
					console.log('All data processed, temp file removed');
				} catch (e) {
					// its k
				}
			} else {
				console.log(`Only ${newResults.length}/${remainingData.length} entries processed, keeping temp file for retry`);
				if (this.strict) {
					throw new Error('Requested stock data could not be downloaded');
				}
			}
		} catch (error) {
			console.error('Processing failed:', error);
			throw error;
		}
	}
}
