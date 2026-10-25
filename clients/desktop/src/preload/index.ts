import { contextBridge, ipcRenderer } from 'electron';
import { SESSION_CHANNELS, type SessionState } from '../shared/session';
import { DASHBOARD_CHANNEL, type DashboardSnapshot } from '../shared/dashboard';
import {
	APP_INFO_CHANNEL,
	type AppInfo,
	type DesktopBridge,
} from '../shared/desktop';

const desktop: DesktopBridge = {
	getAppInfo: (): Promise<AppInfo> =>
		ipcRenderer.invoke(APP_INFO_CHANNEL) as Promise<AppInfo>,
	dashboard: {
		get: () =>
			ipcRenderer.invoke(DASHBOARD_CHANNEL) as Promise<DashboardSnapshot>,
	},
	session: {
		getState: () =>
			ipcRenderer.invoke(SESSION_CHANNELS.get) as Promise<SessionState>,
		signIn: () => ipcRenderer.invoke(SESSION_CHANNELS.signIn) as Promise<void>,
		cancelSignIn: () =>
			ipcRenderer.invoke(SESSION_CHANNELS.cancel) as Promise<void>,
		signOut: () =>
			ipcRenderer.invoke(SESSION_CHANNELS.signOut) as Promise<void>,
		retry: () => ipcRenderer.invoke(SESSION_CHANNELS.retry) as Promise<void>,
		onChange: (listener) => {
			const handle = (_event: Electron.IpcRendererEvent, state: SessionState) =>
				listener(state);
			ipcRenderer.on(SESSION_CHANNELS.changed, handle);
			return () => {
				ipcRenderer.removeListener(SESSION_CHANNELS.changed, handle);
			};
		},
	},
};

contextBridge.exposeInMainWorld('desktop', desktop);
