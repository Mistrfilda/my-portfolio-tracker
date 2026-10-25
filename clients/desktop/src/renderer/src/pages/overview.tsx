import { SlidersHorizontal } from 'lucide-react';
import { Link } from 'react-router';
import { AccountCard } from '@/components/account-card';
import { Button } from '@/components/ui/button';
import { useSession } from '@/lib/session';
import { Dashboard } from '@/features/dashboard/dashboard';

export function OverviewPage() {
	const { state } = useSession();
	if (state?.status === 'signed-in' && state.user)
		return <Dashboard key={state.connection.serverUrl + ':' + state.user.id} />;
	return (
		<div className="mx-auto max-w-4xl">
			<p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-primary">
				Váš osobní přehled
			</p>
			<h1 className="text-3xl font-semibold tracking-tight">
				Vítejte v My Portfolio.
			</h1>
			<p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground">
				Vaše investice v samostatné aplikaci pro Mac. Začněte připojením účtu.
			</p>
			<div className="mt-10">
				<AccountCard />
			</div>
			<Button asChild variant="ghost" className="mt-6">
				<Link to="/settings">
					<SlidersHorizontal aria-hidden="true" />
					Upravit vzhled
				</Link>
			</Button>
		</div>
	);
}
