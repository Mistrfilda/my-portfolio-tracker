import puppeteer from 'puppeteer';
import * as path from "node:path";
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function loadJsonFile(filePath) {
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

async function processData(entries) {
	const result = [];

	if (!Array.isArray(entries)) {
		throw new TypeError('The provided input is not an array');
	}

	let browser;
	try {
		// Spuštění prohlížeče
		browser = await puppeteer.launch({
			// headless: false,
			// devtools: true,
			headless: true,
			slowMo: 100,
			browser: "firefox",
			executablePath: "/usr/bin/firefox",
			args: [
				'--no-sandbox', // Používá se často na serverech (sandbox nebude aplikován)
				'--disable-setuid-sandbox', // Potřebné hlavně na serverech, náročné na RAM
				'--disable-gpu', // Nepoužívej GPU akceleraci (zbytečné na serveru)
				'--disable-dev-shm-usage', // Vyřeší problémy s /dev/shm na dockeru
				'--single-process', // Spouští prohlížeč jako jeden proces (nižší CPU)
				'--disable-background-timer-throttling', // Pomáhá částečně s výkonem
				'--disable-extensions', // Zakáže všechny Chrome/Firefox rozšíření
				'--disable-sync', // Zakáže synchronizaci (méně systémové zátěže)
			],
		});

		console.log(entries);

		for (const [index, entry] of entries.entries()) {
			try {
				const { id, name, currency, url } = entry;
				console.log('Processing: ' + name + ', ' + url + ', ' + currency + ', ' + id);

				const page = await browser.newPage();

				try {
					await page.goto(url, { timeout: 30000, waitUntil: 'domcontentloaded' });
					await page.setViewport({ width: 1080, height: 1024 });

					if (index === 0) {
						try {
							await page.waitForSelector('.consent-overlay');
							// click the "accept all" button
							await page.click('.consent-overlay .accept-all');
						} catch (e) {
							console.log('Cookie has been authorized');
						}
					}

					const element = await page.waitForSelector("::-p-xpath(/html/body/div[2]/main/section/section/section/article/div[1]/div[3]/table)", { timeout: 5000 });
					const textContent = await page.evaluate(el => el.textContent, element);
					const html = await page.evaluate(el => el.innerHTML, element);

					console.log(`Text content for ${name}:`, textContent);

					result.push({
						id,
						name,
						currency,
						textContent,
						html
					});
				} catch (pageError) {
					console.error(`Error processing page for entry ${name} (ID: ${id}):`, pageError);
				} finally {
					await page.close();
				}
			} catch (entryError) {
				console.error(`Error processing entry:`, entryError);
			}

			await new Promise(resolve => setTimeout(resolve, 5000));
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


async function saveJsonToFile(filePath, jsonData) {
	try {
		await fs.promises.writeFile(filePath, JSON.stringify(jsonData, null, 2), 'utf8');
		console.log(`Result saved to: ${filePath}`);
	} catch (error) {
		throw new Error(`Error writing JSON to file: ${error.message}`);
	}
}

const filePath = path.join(__dirname, '/files/requests/dividends.json');
const outputFilePath = path.join(__dirname, '/files/results/dividends.json');

loadJsonFile(filePath)
	.then(inputData => processData(inputData))
	.then(result => saveJsonToFile(outputFilePath, result))
	.then(() => {
		process.exit(0);
	})
	.catch(error => {
		console.error('Error:', error);
		process.exit(1);
	});

