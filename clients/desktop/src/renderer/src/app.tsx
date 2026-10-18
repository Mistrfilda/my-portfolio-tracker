import { MemoryRouter, Route, Routes } from 'react-router';
import { AppLayout } from '@/components/app-layout';
import { ThemeProvider } from '@/components/theme-provider';
import { OverviewPage } from '@/pages/overview';
import { SettingsPage } from '@/pages/settings';

export function App() {
	return (
		<ThemeProvider>
			<MemoryRouter>
				<Routes>
					<Route element={<AppLayout />}>
						<Route index element={<OverviewPage />} />
						<Route path="settings" element={<SettingsPage />} />
					</Route>
				</Routes>
			</MemoryRouter>
		</ThemeProvider>
	);
}
