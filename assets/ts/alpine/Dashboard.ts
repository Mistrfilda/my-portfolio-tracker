type DashboardResponse = {
	html?: string;
	redirect?: string;
};

export default function dashboard(contentUrl: string) {
	let controller: AbortController | null = null;

	return {
		loading: false,
		error: false,
		html: '',

		init(): void {
			void this.load();
		},

		destroy(): void {
			controller?.abort();
		},

		async load(): Promise<void> {
			if (this.loading) {
				return;
			}

			controller = new AbortController();
			this.loading = true;
			this.error = false;

			try {
				// Fetch directly to keep Naja's full-page spinner out of background loading.
				const response = await fetch(contentUrl, {
					headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
					cache: 'no-store',
					signal: controller.signal,
				});
				if (!response.ok) {
					throw new Error('Dashboard data could not be loaded.');
				}

				const data: DashboardResponse = await response.json();
				if (typeof data.redirect === 'string') {
					window.location.assign(data.redirect);
					return;
				}

				if (typeof data.html !== 'string') {
					throw new Error('Invalid dashboard response.');
				}

				// Only trusted HTML rendered by the authenticated Latte endpoint reaches x-html.
				this.html = data.html;
			} catch {
				if (!controller.signal.aborted) {
					this.error = true;
				}
			} finally {
				this.loading = false;
			}
		},
	};
}
