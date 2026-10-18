import {
	useCallback,
	useEffect,
	useMemo,
	useState,
	type ReactNode,
} from 'react';
import {
	applyTheme,
	readTheme,
	ThemeContext,
	THEME_STORAGE_KEY,
	type Theme,
} from '@/lib/theme';

export function ThemeProvider({ children }: { children: ReactNode }) {
	const [theme, updateTheme] = useState<Theme>(readTheme);

	useEffect(() => {
		const media = window.matchMedia('(prefers-color-scheme: dark)');
		const update = () => applyTheme(theme);
		update();
		media.addEventListener('change', update);
		return () => media.removeEventListener('change', update);
	}, [theme]);

	const setTheme = useCallback((value: Theme) => {
		updateTheme(value);
		try {
			localStorage.setItem(THEME_STORAGE_KEY, value);
		} catch {
			// Appearance still works when persistent storage is unavailable.
		}
	}, []);
	const value = useMemo(() => ({ theme, setTheme }), [theme, setTheme]);

	return (
		<ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
	);
}
