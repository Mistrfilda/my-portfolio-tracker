import { expect, type ConsoleMessage, type Page } from '@playwright/test';

type ErrorRecorder = {
	assertNoErrors: () => void;
	dispose: () => void;
};

export function watchFrontendErrors(page: Page): ErrorRecorder {
	const errors: string[] = [];

	const consoleErrorListener = (message: ConsoleMessage): void => {
		if (message.type() === 'error') {
			errors.push(`console.error: ${message.text()}`);
		}
	};

	const pageErrorListener = (error: Error): void => {
		errors.push(`pageerror: ${error.message}`);
	};

	page.on('console', consoleErrorListener);
	page.on('pageerror', pageErrorListener);

	return {
		assertNoErrors: (): void => {
			expect(errors).toEqual([]);
		},
		dispose: (): void => {
			page.off('console', consoleErrorListener);
			page.off('pageerror', pageErrorListener);
		},
	};
}