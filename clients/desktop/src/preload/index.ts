import { contextBridge, ipcRenderer } from 'electron';
import {
	APP_INFO_CHANNEL,
	type AppInfo,
	type DesktopBridge,
} from '../shared/desktop';

const desktop: DesktopBridge = {
	getAppInfo: (): Promise<AppInfo> =>
		ipcRenderer.invoke(APP_INFO_CHANNEL) as Promise<AppInfo>,
};

contextBridge.exposeInMainWorld('desktop', desktop);
