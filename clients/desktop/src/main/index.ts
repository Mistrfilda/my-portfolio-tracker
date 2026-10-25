import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import {
	app,
	BrowserWindow,
	ipcMain,
	Menu,
	net,
	protocol,
	safeStorage,
	session,
	shell,
	powerMonitor,
	type IpcMainInvokeEvent,
} from 'electron';
import { APP_INFO_CHANNEL, type AppInfo } from '../shared/desktop';
import { SESSION_CHANNELS } from '../shared/session';
import { DASHBOARD_CHANNEL } from '../shared/dashboard';
import { DashboardClient, DashboardService } from './dashboard/client';
import { authorize } from './auth/authorize';
import { OAuthClient } from './auth/client';
import { defaultConnection } from './auth/config';
import { SessionService } from './auth/session-service';
import { EncryptedTokenStore } from './auth/token-store';
import {
	APP_URL,
	isTrustedRendererUrl,
	resolveRendererFile,
	validateDevelopmentUrl,
} from './security';

protocol.registerSchemesAsPrivileged([
	{
		scheme: 'app',
		privileges: { standard: true, secure: true, supportFetchAPI: true },
	},
]);

const userDataDirectory = app.commandLine.getSwitchValue('user-data-dir');
if (userDataDirectory) {
	app.setPath('userData', userDataDirectory);
}

const directory = fileURLToPath(new URL('.', import.meta.url));
const developmentUrl =
	!app.isPackaged && process.env.ELECTRON_RENDERER_URL
		? validateDevelopmentUrl(process.env.ELECTRON_RENDERER_URL)
		: undefined;
let mainWindow: BrowserWindow | null = null;
let auth: SessionService | undefined;
const hasInstanceLock = app.requestSingleInstanceLock();
if (!hasInstanceLock) app.quit();

app.on('second-instance', () => {
	if (mainWindow?.isMinimized()) mainWindow.restore();
	mainWindow?.show();
	mainWindow?.focus();
});
app.on('before-quit', () => auth?.dispose());

function assertTrustedSender(event: IpcMainInvokeEvent, args: unknown[]): void {
	if (
		!mainWindow ||
		event.sender !== mainWindow.webContents ||
		event.senderFrame !== mainWindow.webContents.mainFrame ||
		!isTrustedRendererUrl(event.senderFrame.url, developmentUrl) ||
		args.length !== 0
	)
		throw new Error('Untrusted desktop message sender or arguments.');
}

function createWindow(): void {
	mainWindow = new BrowserWindow({
		width: 1280,
		height: 820,
		minWidth: 920,
		minHeight: 640,
		title: 'My Portfolio',
		titleBarStyle: 'hiddenInset',
		backgroundColor: '#f7f8fa',
		show: false,
		webPreferences: {
			preload: join(directory, '../preload/index.cjs'),
			nodeIntegration: false,
			contextIsolation: true,
			sandbox: true,
			webSecurity: true,
			webviewTag: false,
		},
	});
	mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
	mainWindow.webContents.on('will-navigate', (event) => event.preventDefault());
	mainWindow.once('ready-to-show', () => mainWindow?.show());
	mainWindow.on('closed', () => {
		mainWindow = null;
	});
	void mainWindow.loadURL(developmentUrl ?? APP_URL);
}

if (hasInstanceLock)
	void app.whenReady().then(() => {
		const rendererRoot = join(directory, '../renderer');
		protocol.handle('app', (request) => {
			const file = resolveRendererFile(request.url, rendererRoot);
			if (request.method !== 'GET' || file === null) {
				return new Response('Not found', { status: 404 });
			}
			return net.fetch(pathToFileURL(file).toString());
		});

		session.defaultSession.setPermissionRequestHandler(
			(_contents, _permission, callback) => callback(false),
		);
		session.defaultSession.setPermissionCheckHandler(() => false);

		ipcMain.handle(APP_INFO_CHANNEL, (event, ...args: unknown[]): AppInfo => {
			assertTrustedSender(event, args);
			return { name: app.getName(), version: app.getVersion() };
		});

		auth = new SessionService(
			defaultConnection,
			new OAuthClient(defaultConnection),
			new EncryptedTokenStore(
				app.getPath('userData'),
				defaultConnection.storageKey,
				safeStorage,
			),
			(signal) =>
				authorize(defaultConnection, (url) => shell.openExternal(url), signal),
			(state) => {
				if (mainWindow && !mainWindow.isDestroyed()) {
					mainWindow.webContents.send(SESSION_CHANNELS.changed, state);
				}
			},
		);
		const dashboard = new DashboardService(
			auth,
			new DashboardClient(defaultConnection),
		);
		const handlers: Record<string, () => unknown> = {
			[DASHBOARD_CHANNEL]: () => dashboard.get(),
			[SESSION_CHANNELS.get]: () => auth!.getState(),
			[SESSION_CHANNELS.signIn]: async () => {
				await auth!.signIn();
				if (auth!.getState().status === 'signed-in') {
					mainWindow?.show();
					mainWindow?.focus();
				}
			},
			[SESSION_CHANNELS.cancel]: () => auth!.cancelSignIn(),
			[SESSION_CHANNELS.signOut]: () => auth!.signOut(),
			[SESSION_CHANNELS.retry]: () => auth!.retry(),
		};
		for (const [channel, handler] of Object.entries(handlers)) {
			ipcMain.handle(channel, (event, ...args: unknown[]) => {
				assertTrustedSender(event, args);
				return handler();
			});
		}
		powerMonitor.on('resume', () => {
			void auth?.retry();
		});
		void auth.initialize();

		Menu.setApplicationMenu(
			Menu.buildFromTemplate([
				{ role: 'appMenu' },
				{ role: 'editMenu' },
				{ role: 'viewMenu' },
				{ role: 'windowMenu' },
			]),
		);

		createWindow();
		app.on('activate', () => {
			if (BrowserWindow.getAllWindows().length === 0) {
				createWindow();
			}
		});
	});

app.on('window-all-closed', () => {
	if (process.platform !== 'darwin') {
		app.quit();
	}
});
