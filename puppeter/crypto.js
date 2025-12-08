import { CryptoScapper } from './CryptoScapper.js';

const cryptoScapper = new CryptoScapper();
await cryptoScapper.run('crypto.json', 'crypto.json');
