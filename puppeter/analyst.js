import { AnalystInsightsScraper } from './AnalystInsightsScraper.js';

const scraper = new AnalystInsightsScraper();
await scraper.run('analystInsights.json', 'analystInsights.json');
