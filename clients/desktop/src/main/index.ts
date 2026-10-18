import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import {
	app,
	BrowserWindow,
	ipcMain,
	Menu,
	net,
	protocol,
	session,
} from 'electron';
import { APP_INFO_CHANNEL, type AppInfo } from '../shared/desktop';
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

	ipcMain.handle(APP_INFO_CHANNEL, (event): AppInfo => {
		if (
			!mainWindow ||
			event.sender !== mainWindow.webContents ||
			event.senderFrame !== mainWindow.webContents.mainFrame ||
			!isTrustedRendererUrl(event.senderFrame.url, developmentUrl)
		) {
			throw new Error('Untrusted desktop message sender.');
		}
		return { name: app.getName(), version: app.getVersion() };
	});

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
