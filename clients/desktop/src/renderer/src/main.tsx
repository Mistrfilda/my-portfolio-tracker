import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './app';
import { applyTheme, readTheme } from './lib/theme';
import './styles.css';

applyTheme(readTheme());

const root = document.getElementById('root');
if (!root) {
	throw new Error('The application root is missing.');
}

createRoot(root).render(
	<StrictMode>
		<App />
	</StrictMode>,
);
