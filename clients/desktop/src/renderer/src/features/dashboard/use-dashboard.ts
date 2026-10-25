import { useEffect, useState } from 'react';
import type {
	DashboardIssue,
	DashboardResult,
	DashboardSnapshot,
} from '../../../../shared/dashboard';

export interface DashboardSection<T> {
	data: T | null;
	issue: DashboardIssue | null;
}

type View = {
	[K in keyof DashboardSnapshot]: DashboardSection<
		NonNullable<DashboardSnapshot[K]['data']>
	>;
};

function merge<T>(
	previous: DashboardSection<T> | undefined,
	next: DashboardResult<T>,
): DashboardSection<T> {
	return { data: next.data ?? previous?.data ?? null, issue: next.issue };
}

export function useDashboard() {
	const [request, setRequest] = useState(0);
	const [completed, setCompleted] = useState<{
		request: number;
		view: View;
	} | null>(null);
	useEffect(() => {
		let active = true;
		const failed = { data: null, issue: 'network' } as const;
		void window
			.desktop!.dashboard.get()
			.catch((): DashboardSnapshot => ({
				summary: failed,
				dividends: failed,
				rates: failed,
			}))
			.then((next) => {
				if (active)
					setCompleted((previous) => ({
						request,
						view: {
							summary: merge(previous?.view.summary, next.summary),
							dividends: merge(previous?.view.dividends, next.dividends),
							rates: merge(previous?.view.rates, next.rates),
						},
					}));
			});
		return () => {
			active = false;
		};
	}, [request]);
	return {
		view: completed?.view,
		loading: completed?.request !== request,
		refresh: () => setRequest((value) => value + 1),
	};
}
