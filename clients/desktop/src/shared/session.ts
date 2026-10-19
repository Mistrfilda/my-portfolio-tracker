export const SESSION_CHANNELS = {
	get: 'session:get',
	signIn: 'session:sign-in',
	cancel: 'session:cancel',
	signOut: 'session:sign-out',
	retry: 'session:retry',
	changed: 'session:changed',
} as const;

export type SessionIssue =
	| 'network'
	| 'server'
	| 'denied'
	| 'timeout'
	| 'storage'
	| 'expired'
	| 'browser'
	| 'invalid-response'
	| 'permissions'
	| 'logout-incomplete';

export interface SessionUser {
	id: string;
	name: string;
	username: string;
}

export interface SessionState {
	revision: number;
	status:
		| 'restoring'
		| 'signed-out'
		| 'signing-in'
		| 'signed-in'
		| 'unavailable'
		| 'signing-out';
	user: SessionUser | null;
	issue: SessionIssue | null;
	connection: { name: string; serverUrl: string };
}

export interface SessionBridge {
	getState: () => Promise<SessionState>;
	signIn: () => Promise<void>;
	cancelSignIn: () => Promise<void>;
	signOut: () => Promise<void>;
	retry: () => Promise<void>;
	onChange: (listener: (state: SessionState) => void) => () => void;
}
