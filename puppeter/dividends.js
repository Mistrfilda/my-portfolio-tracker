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
		slowMo: 100,
		executablePath: "/usr/bin/chromium",
		args: ['--no-sandbox'],
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

		var element = await page.waitForSelector("::-p-xpath(/html/body/div[2]/main/section/section/section/article/div[1]/div[3]/table)")
		var textContent = await page.evaluate(element => element.textContent, element);
		var html = await page.evaluate(element => element.innerHTML, element);
		console.log(textContent);

		result.push({
			id,
			name,
			currency,
			textContent,
			html
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
