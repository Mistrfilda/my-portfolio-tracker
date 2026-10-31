import { AccountCard } from '@/components/account-card';
import { Stocks } from '@/features/stocks/stocks';
import { useSession } from '@/lib/session';

export function StocksPage() {
	const { state } = useSession();
	if (state?.status === 'signed-in' && state.user)
		return <Stocks key={state.connection.serverUrl + ':' + state.user.id} />;
	return (
		<div className="mx-auto max-w-4xl space-y-6">
			<div>
				<h1 className="text-3xl font-semibold tracking-tight">
					Akcie a pozice
				</h1>
				<p className="mt-3 text-sm text-muted-foreground">
					Připojte účet pro zobrazení držených akcií a jednotlivých nákupů.
				</p>
			</div>
			<AccountCard />
		</div>
	);
}
