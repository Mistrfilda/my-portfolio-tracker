import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import type { DashboardIssue } from '../../../../shared/dashboard';
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
} from '@/components/ui/card';

const messages: Record<DashboardIssue, string> = {
	network: 'Server není dostupný. Zkontrolujte připojení k místní síti.',
	server: 'Server nemohl data načíst. Zkuste je obnovit za chvíli.',
	permissions: 'Pro tuto část nemá aplikace potřebné oprávnění.',
	'data-unavailable':
		'Na serveru chybí data potřebná pro výpočet. Zkuste je obnovit později.',
	'invalid-response': 'Server vrátil data v neočekávaném formátu.',
	session: 'Přihlášení není dostupné. Zkontrolujte účet v Nastavení.',
};

export function DataIssue({
	issue,
	stale = false,
}: {
	issue: DashboardIssue;
	stale?: boolean;
}) {
	return (
		<div
			role="alert"
			className="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3 text-sm leading-6"
		>
			<CircleAlert
				className="mt-1 size-4 shrink-0 text-warning"
				aria-hidden="true"
			/>
			<p>
				{messages[issue]}
				{stale && ' Zobrazená data pocházejí z posledního úspěšného načtení.'}
			</p>
		</div>
	);
}

export function Panel({
	title,
	description,
	children,
	className = '',
}: {
	title: string;
	description?: string;
	children: ReactNode;
	className?: string;
}) {
	return (
		<Card className={'gap-5 shadow-none ' + className}>
			<CardHeader>
				<h2 className="text-sm font-semibold">{title}</h2>
				{description && (
					<CardDescription className="text-xs leading-5">
						{description}
					</CardDescription>
				)}
			</CardHeader>
			<CardContent>{children}</CardContent>
		</Card>
	);
}

export function Empty({ children }: { children: ReactNode }) {
	return (
		<p className="rounded-lg bg-muted/50 px-4 py-6 text-sm leading-6 text-muted-foreground">
			{children}
		</p>
	);
}
