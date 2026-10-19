import {
	Check,
	ExternalLink,
	LoaderCircle,
	LogOut,
	UserRound,
} from 'lucide-react';
import type { SessionIssue } from '../../../shared/session';
import { useSession } from '@/lib/session';
import { Button } from '@/components/ui/button';
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from '@/components/ui/card';

const messages: Record<SessionIssue, string> = {
	network:
		'Server není dostupný. Zkontrolujte připojení k místní síti a zkuste to znovu.',
	server: 'Server nemohl požadavek dokončit. Zkuste to prosím znovu.',
	denied: 'Přístup k účtu nebyl povolen. Přihlášení můžete zopakovat.',
	timeout: 'Čas pro přihlášení vypršel. Zkuste se přihlásit znovu.',
	storage:
		'Uložené přihlášení nelze bezpečně přečíst nebo uložit. Zkontrolujte dostupnost Klíčenky a zkuste to znovu.',
	expired:
		'Přihlášení vypršelo nebo se nepodařilo bezpečně obnovit. Přihlaste se znovu.',
	browser: 'Nepodařilo se otevřít přihlašování v prohlížeči. Zkuste to znovu.',
	'invalid-response': 'Server vrátil neočekávanou odpověď. Zkuste to znovu.',
	permissions: 'Účet nemá potřebné oprávnění pro tuto aplikaci.',
	'logout-incomplete':
		'Z tohoto Macu jste odhlášeni. Ukončení relace na serveru se nepodařilo potvrdit.',
};

export function AccountCard() {
	const { state, available, bridgeError, run } = useSession();
	const status = state?.status;
	const busy = !state || status === 'restoring' || status === 'signing-out';
	return (
		<Card className="shadow-none">
			<CardHeader>
				<CardTitle className="flex items-center gap-2">
					<UserRound className="size-4" aria-hidden="true" />
					{status === 'signed-in' ? 'Váš účet' : 'Připojení účtu'}
				</CardTitle>
				<CardDescription>
					{state
						? state.connection.name + ' · ' + state.connection.serverUrl
						: 'My Portfolio'}
				</CardDescription>
			</CardHeader>
			<CardContent className="space-y-5">
				{!available ? (
					<p className="text-sm text-muted-foreground">
						Přihlášení je dostupné v desktopové aplikaci pro Mac. Toto je náhled
						v prohlížeči.
					</p>
				) : busy ? (
					<p
						role="status"
						className="flex items-center gap-2 text-sm text-muted-foreground"
					>
						<LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
						{status === 'signing-out'
							? 'Odhlašování…'
							: 'Ověřování přihlášení…'}
					</p>
				) : status === 'signed-in' && state.user ? (
					<div className="flex flex-wrap items-center justify-between gap-4">
						<div className="flex min-w-0 items-center gap-3">
							<div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
								<Check className="size-5" aria-hidden="true" />
							</div>
							<div className="min-w-0">
								<p className="truncate text-sm font-medium">
									{state.user.name || state.user.username}
								</p>
								<p className="truncate text-xs text-muted-foreground">
									{state.user.username}
								</p>
								<p role="status" className="mt-1 text-xs text-primary">
									Účet je připojený
								</p>
							</div>
						</div>
						<Button variant="outline" onClick={() => run('signOut')}>
							<LogOut aria-hidden="true" />
							Odhlásit se
						</Button>
					</div>
				) : status === 'signing-in' ? (
					<>
						<p role="status" className="flex items-center gap-2 text-sm">
							<LoaderCircle
								className="size-4 animate-spin"
								aria-hidden="true"
							/>
							Dokončete přihlášení v prohlížeči.
						</p>
						<p className="text-sm text-muted-foreground">
							Po povolení přístupu se vrátíte do aplikace.
						</p>
						<Button variant="outline" onClick={() => run('cancelSignIn')}>
							Zrušit přihlášení
						</Button>
					</>
				) : status === 'unavailable' ? (
					<div className="flex flex-wrap gap-3">
						<Button onClick={() => run('retry')}>Zkusit znovu</Button>
						<Button variant="outline" onClick={() => run('signOut')}>
							Odhlásit z tohoto Macu
						</Button>
					</div>
				) : (
					<>
						<p className="max-w-xl text-sm leading-6 text-muted-foreground">
							Připojte svůj stávající účet. Přihlášení a povolení přístupu
							dokončíte na serveru v prohlížeči.
						</p>
						<Button onClick={() => run('signIn')}>
							<ExternalLink aria-hidden="true" />
							Přihlásit se
						</Button>
					</>
				)}
				{state?.issue && (
					<p
						role="alert"
						className="rounded-lg border bg-muted/50 px-4 py-3 text-sm leading-6"
					>
						{messages[state.issue]}
					</p>
				)}
				{bridgeError && (
					<p role="alert" className="text-sm text-destructive">
						Spojení s aplikací se přerušilo. Zkuste aplikaci znovu otevřít.
					</p>
				)}
			</CardContent>
		</Card>
	);
}
