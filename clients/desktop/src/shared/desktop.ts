import type { SessionBridge } from './session';
import type { DashboardBridge } from './dashboard';
import type { StocksBridge } from './stocks';

export const APP_INFO_CHANNEL = 'app:get-info';

export interface AppInfo {
	name: string;
	version: string;
}

export interface DesktopBridge {
	getAppInfo: () => Promise<AppInfo>;
	session: SessionBridge;
	dashboard: DashboardBridge;
	stocks: StocksBridge;
}
