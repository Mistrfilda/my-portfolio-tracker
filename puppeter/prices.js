import { PricesScraper } from './PricesScraper.js';

const scraper = new PricesScraper();
await scraper.run('prices.json', 'prices.json');
