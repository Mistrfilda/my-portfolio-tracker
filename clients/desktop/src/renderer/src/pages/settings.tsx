import { useEffect, useState } from 'react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from '@/components/ui/card';
import { useTheme, type Theme } from '@/lib/theme';
import { AccountCard } from '@/components/account-card';

const themes = [
	{ value: 'light', label: 'Světlý', icon: Sun },
	{ value: 'dark', label: 'Tmavý', icon: Moon },
	{ value: 'system', label: 'Podle systému', icon: Monitor },
] satisfies { value: Theme; label: string; icon: typeof Sun }[];

export function SettingsPage() {
	const { theme, setTheme } = useTheme();
	const [version, setVersion] = useState<string | null>(null);

	useEffect(() => {
		let active = true;
		void window.desktop?.getAppInfo().then(
			(info) => {
				if (active) setVersion(info.version);
			},
			() => {
				if (active) setVersion(null);
			},
		);
		return () => {
			active = false;
		};
	}, []);

	return (
		<div className="mx-auto max-w-4xl">
			<p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-primary">
				Podle vás
			</p>
			<h1 className="text-3xl font-semibold tracking-tight">Nastavení</h1>
			<p className="mt-3 text-sm leading-6 text-muted-foreground">
				Malé detaily pro pohodlnější práci.
			</p>

			<div className="mt-10">
				<AccountCard />
			</div>
			<Card className="mt-6 shadow-none">
				<CardHeader>
					<CardTitle>Vzhled aplikace</CardTitle>
					<CardDescription>
						Vyberte si barevný režim, nebo nechte rozhodnout nastavení Macu.
					</CardDescription>
				</CardHeader>
				<CardContent>
					<div
						className="grid grid-cols-3 gap-3"
						role="group"
						aria-label="Barevný režim"
					>
						{themes.map(({ value, label, icon: Icon }) => (
							<Button
								key={value}
								variant={theme === value ? 'default' : 'outline'}
								className="h-24 flex-col gap-3"
								aria-pressed={theme === value}
								onClick={() => setTheme(value)}
							>
								<Icon className="size-5" aria-hidden="true" />
								{label}
							</Button>
						))}
					</div>
				</CardContent>
			</Card>
			<div className="mt-8 flex items-center justify-between px-1 text-xs text-muted-foreground">
				<p>My Portfolio pro macOS</p>
				{version && <p>Verze {version}</p>}
			</div>
		</div>
	);
}
