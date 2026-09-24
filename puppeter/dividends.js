import { DividendsScraper } from './DividendsScraper.js';

const scraper = new DividendsScraper();
await scraper.run('dividends.json', 'dividends.json');
