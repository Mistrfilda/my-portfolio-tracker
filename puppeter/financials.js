//import { FinancialsScraper } from './FinancialsScraper.js';
import { KeyStatisticsScraper } from './KeyStatisticsScraper.js';
import { AnalystInsightsScraper } from './AnalystInsightsScraper.js';

// const financialsScraper = new FinancialsScraper();
// await financialsScraper.run('financials.json', 'financials.json');

const keyStatisticsScraper = new KeyStatisticsScraper();
await keyStatisticsScraper.run('keyStatistics.json', 'keyStatistics.json');

const scraper = new AnalystInsightsScraper();
await scraper.run('analystInsights.json', 'analystInsights.json');
