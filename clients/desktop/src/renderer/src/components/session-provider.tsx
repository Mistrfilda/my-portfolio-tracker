import {
	useCallback,
	useEffect,
	useMemo,
	useState,
	type ReactNode,
} from 'react';
import type { SessionState } from '../../../shared/session';
import { SessionContext, type SessionAction } from '@/lib/session';

export function SessionProvider({ children }: { children: ReactNode }) {
	const bridge = window.desktop?.session;
	const [state, setState] = useState<SessionState | null>(null);
	const [bridgeError, setBridgeError] = useState(false);
	useEffect(() => {
		if (!bridge) return;
		let active = true;
		const receive = (next: SessionState) => {
			if (active)
				setState((current) =>
					!current || next.revision >= current.revision ? next : current,
				);
		};
		const unsubscribe = bridge.onChange(receive);
		void bridge.getState().then(receive, () => {
			if (active) setBridgeError(true);
		});
		return () => {
			active = false;
			unsubscribe();
		};
	}, [bridge]);
	const run = useCallback(
		(action: SessionAction) => {
			if (!bridge) return;
			setBridgeError(false);
			void bridge[action]().catch(() => setBridgeError(true));
		},
		[bridge],
	);
	const value = useMemo(
		() => ({ state, available: Boolean(bridge), bridgeError, run }),
		[state, bridge, bridgeError, run],
	);
	return (
		<SessionContext.Provider value={value}>{children}</SessionContext.Provider>
	);
}
