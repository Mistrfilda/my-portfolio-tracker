import type { DashboardResult } from '../../shared/dashboard';
import { readJson } from '../auth/client';
import type { Connection } from '../auth/config';
import { AuthError } from '../auth/errors';

export class ApiClient {
	constructor(
		private readonly connection: Connection,
		private readonly request: typeof fetch = fetch,
	) {}

	protected async get<T>(
		path: string,
		token: string,
		parse: (value: unknown) => T,
		maxBytes = 256 * 1024,
	): Promise<DashboardResult<T>> {
		let response: Response;
		try {
			response = await this.request(
				new URL('api/v1/' + path, this.connection.serverUrl + '/'),
				{
					method: 'GET',
					headers: {
						Accept: 'application/json',
						Authorization: 'Bearer ' + token,
					},
					redirect: 'error',
					credentials: 'omit',
					cache: 'no-store',
					signal: AbortSignal.timeout(15_000),
				},
			);
		} catch {
			return { data: null, issue: 'network' };
		}
		if (!response.ok) {
			await response.body?.cancel().catch(() => undefined);
			if (response.status === 401) throw new AuthError('expired');
			return {
				data: null,
				issue:
					response.status === 403
						? 'permissions'
						: response.status === 503
							? 'data-unavailable'
							: 'server',
			};
		}
		try {
			return { data: parse(await readJson(response, maxBytes)), issue: null };
		} catch {
			return { data: null, issue: 'invalid-response' };
		}
	}
}
