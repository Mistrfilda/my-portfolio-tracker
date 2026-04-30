export type BrowserTestEnv = {
	baseUrl: string;
	username: string;
	password: string;
};

export function getBrowserTestEnv(): BrowserTestEnv {
	return {
		baseUrl: getRequiredEnv('PLAYWRIGHT_BASE_URL'),
		username: getRequiredEnv('PLAYWRIGHT_LOGIN_USERNAME'),
		password: getRequiredEnv('PLAYWRIGHT_LOGIN_PASSWORD'),
	};
}

function getRequiredEnv(name: string): string {
	const value = process.env[name];

	if (value === undefined || value.trim() === '') {
		throw new Error(`${name} is required. Create .env.browser-tests from .env.browser-tests.example and fill it in.`);
	}

	return value;
}