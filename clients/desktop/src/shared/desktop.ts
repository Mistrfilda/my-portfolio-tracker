export const APP_INFO_CHANNEL = 'app:get-info';

export interface AppInfo {
	name: string;
	version: string;
}

export interface DesktopBridge {
	getAppInfo: () => Promise<AppInfo>;
}
