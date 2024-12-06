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

	const browser = await puppeteer.launch({
		// headless: false,
		// devtools: true,
		headless: true,
		slowMo: 100,
		browser: "firefox",
		executablePath: "/usr/bin/firefox",
		args: ['--no-sandbox', '--in-process-gpu', '--disable-gpu', '--disable-dev-shm-usage', '--disable-setuid-sandbox'],
	});

	console.log(entries);
	for (const [index, entry] of entries.entries()) {
		const { id, name, currency, url } = entry;

		const page = await browser.newPage();

		await page.goto(url);
		await page.setViewport({width: 1080, height: 1024});

		if (index === 0) {
			var accept = ("#consent-page > div > div > div > form > div.wizard-body > div.actions.couple > button");
			await page.click(accept)
		}

		var element = await page.waitForSelector("::-p-xpath(/html/body/div[2]/main/section/section/section/article/section[1]/div[2]/div[1]/section/div/section/div[1]/fin-streamer[1]/span)")
		var price = await page.evaluate(element => element.textContent, element);

		console.log(entry);
		console.log(price);
		result.push({
			id,
			name,
			currency,
			price
		});
	}

	await browser.close();
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

const filePath = path.join(__dirname, '/files/requests/prices.json');
const outputFilePath = path.join(__dirname, '/files/results/prices.json');

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

