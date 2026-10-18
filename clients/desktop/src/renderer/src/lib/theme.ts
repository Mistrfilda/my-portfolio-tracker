import { createContext, useContext } from 'react';

export type Theme = 'system' | 'light' | 'dark';
export const THEME_STORAGE_KEY = 'portfolio:appearance';

export const ThemeContext = createContext<{
	theme: Theme;
	setTheme: (theme: Theme) => void;
} | null>(null);

export function readTheme(): Theme {
	try {
		const value = localStorage.getItem(THEME_STORAGE_KEY);
		return value === 'light' || value === 'dark' ? value : 'system';
	} catch {
		return 'system';
	}
}

export function applyTheme(theme: Theme): void {
	const dark =
		theme === 'dark' ||
		(theme === 'system' &&
			window.matchMedia('(prefers-color-scheme: dark)').matches);
	document.documentElement.classList.toggle('dark', dark);
}

export function useTheme() {
	const context = useContext(ThemeContext);
	if (!context) {
		throw new Error('ThemeProvider is required.');
	}
	return context;
}
