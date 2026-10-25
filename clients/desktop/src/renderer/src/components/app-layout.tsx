import {
	ChartNoAxesCombined,
	LayoutDashboard,
	Settings2,
	UserRound,
} from 'lucide-react';
import { NavLink, Outlet, useLocation } from 'react-router';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useSession } from '@/lib/session';

const navigation = [
	{ to: '/', label: 'Přehled', icon: LayoutDashboard },
	{ to: '/settings', label: 'Nastavení', icon: Settings2 },
];

export function AppLayout() {
	const { state } = useSession();
	const location = useLocation();
	const title =
		navigation.find((item) => item.to === location.pathname)?.label ??
		'My Portfolio';

	return (
		<div className="flex h-screen min-h-0 overflow-hidden">
			<aside className="flex w-52 shrink-0 flex-col border-r bg-card xl:w-56">
				<div className="window-drag h-12 shrink-0" aria-hidden="true" />
				<div className="flex items-center gap-3 px-6 pb-9 pt-4">
					<div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
						<ChartNoAxesCombined className="size-5" aria-hidden="true" />
					</div>
					<div>
						<p className="text-sm font-semibold tracking-tight">My Portfolio</p>
						<p className="mt-0.5 text-xs text-muted-foreground">
							Prostor pro vaše investice
						</p>
					</div>
				</div>
				<nav aria-label="Hlavní navigace" className="space-y-1 px-3">
					{navigation.map(({ to, label, icon: Icon }) => (
						<NavLink
							key={to}
							to={to}
							end
							className={({ isActive }) =>
								cn(
									buttonVariants({ variant: 'ghost' }),
									'h-11 w-full justify-start gap-3 px-3 text-muted-foreground',
									isActive &&
										'bg-accent text-accent-foreground hover:bg-accent hover:text-accent-foreground',
								)
							}
						>
							<Icon className="size-4" aria-hidden="true" />
							{label}
						</NavLink>
					))}
				</nav>
				<div className="mt-auto border-t p-5">
					<div className="flex items-center gap-3">
						<div className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
							<UserRound className="size-4" aria-hidden="true" />
						</div>
						<div className="min-w-0">
							<p className="truncate text-xs font-medium">
								{state?.user?.name ||
									state?.user?.username ||
									'Účet není připojený'}
							</p>
							<p className="mt-1 text-xs text-muted-foreground">
								{state?.user
									? 'Přihlášeno k vašemu účtu'
									: 'Vaše data zůstávají na serveru'}
							</p>
						</div>
					</div>
				</div>
			</aside>
			<div className="flex min-w-0 flex-1 flex-col">
				<header className="window-drag flex h-16 shrink-0 items-center justify-between border-b px-8">
					<p className="text-sm font-medium">{title}</p>
					<p className="text-xs text-muted-foreground">My Portfolio pro Mac</p>
				</header>
				<main className="flex-1 overflow-y-auto px-6 py-8 lg:px-8">
					<Outlet />
				</main>
			</div>
		</div>
	);
}
