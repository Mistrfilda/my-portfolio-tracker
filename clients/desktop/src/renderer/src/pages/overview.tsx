import {
	ArrowRight,
	ChartNoAxesCombined,
	SlidersHorizontal,
} from 'lucide-react';
import { Link } from 'react-router';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export function OverviewPage() {
	return (
		<div className="mx-auto max-w-4xl">
			<p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-primary">
				Váš osobní přehled
			</p>
			<h1 className="text-3xl font-semibold tracking-tight">
				Vítejte v My Portfolio.
			</h1>
			<p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground">
				Nový prostor pro vaše investice. Přehledně, v samostatné aplikaci pro
				Mac.
			</p>
			<Card className="mt-10 overflow-hidden shadow-none">
				<CardContent className="flex flex-col items-center px-8 py-16 text-center">
					<div className="mb-7 flex size-20 items-center justify-center rounded-3xl bg-primary/10 text-primary ring-8 ring-primary/5">
						<ChartNoAxesCombined
							className="size-9"
							strokeWidth={1.5}
							aria-hidden="true"
						/>
					</div>
					<Badge variant="secondary" className="mb-5">
						Připravujeme připojení účtu
					</Badge>
					<h2 className="text-xl font-semibold tracking-tight">
						Tady bude vaše portfolio.
					</h2>
					<p className="mt-3 max-w-sm text-sm leading-6 text-muted-foreground">
						Přihlášení ke stávajícímu účtu přidáme v další verzi. Zatím si
						můžete přizpůsobit vzhled aplikace.
					</p>
					<Button asChild className="mt-7">
						<Link to="/settings">
							<SlidersHorizontal aria-hidden="true" />
							Upravit vzhled
							<ArrowRight aria-hidden="true" />
						</Link>
					</Button>
				</CardContent>
			</Card>
		</div>
	);
}
