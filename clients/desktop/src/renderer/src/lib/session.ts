import { createContext, useContext } from 'react';
import type { SessionState } from '../../../shared/session';

export type SessionAction = 'signIn' | 'cancelSignIn' | 'signOut' | 'retry';
export const SessionContext = createContext<{
	state: SessionState | null;
	available: boolean;
	bridgeError: boolean;
	run: (action: SessionAction) => void;
} | null>(null);

export function useSession() {
	const context = useContext(SessionContext);
	if (!context) throw new Error('SessionProvider is required.');
	return context;
}
