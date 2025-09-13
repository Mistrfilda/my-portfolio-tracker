//import { FinancialsScraper } from './FinancialsScraper.js';
import { KeyStatisticsScraper } from './KeyStatisticsScraper.js';

// const financialsScraper = new FinancialsScraper();
// await financialsScraper.run('financials.json', 'financials.json');

const keyStatisticsScraper = new KeyStatisticsScraper();
await keyStatisticsScraper.run('keyStatistics.json', 'keyStatistics.json');
