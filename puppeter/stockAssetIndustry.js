import { StockAssetIndustryScapper } from './StockAssetIndustryScapper.js';

const scraper = new StockAssetIndustryScapper();
await scraper.run('stockAssetIndustry.json', 'stockAssetIndustry.json');
