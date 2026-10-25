import { describe, expect, it, vi } from 'vitest';
import { defaultConnection, parseConnection } from '../../src/main/auth/config';
import { AuthError } from '../../src/main/auth/errors';
import type { SessionState } from '../../src/shared/session';
import {
	DashboardClient,
	DashboardService,
} from '../../src/main/dashboard/client';
import {
	parseDividends,
	parseRates,
	parseSummary,
} from '../../src/main/dashboard/contracts';
import { dividendResponse, rates, summary } from '../fixtures/dashboard';

function fixture() {
	const request = vi.fn<typeof fetch>(async (input) => {
		const path = new URL(String(input)).pathname;
		return Response.json(
			path.endsWith('/summary')
				? summary
				: path.endsWith('/dividends')
					? dividendResponse
					: { items: rates },
		);
	});
	return { request, client: new DashboardClient(defaultConnection, request) };
}

describe('dashboard contracts and requests', () => {
	it('loads only the three fixed endpoints with bearer auth, no cookies, redirects or cache', async () => {
		const f = fixture();
		const result = await f.client.load('test-access');
		expect(result.summary.data).toEqual(summary);
		expect(result.dividends.data?.[0]?.netAmount).toBe(106.25);
		expect(result.rates.data).toEqual(rates);
		expect(
			f.request.mock.calls.map(
				([url]) => new URL(String(url)).pathname + new URL(String(url)).search,
			),
		).toEqual([
			'/api/v1/dashboard/summary',
			'/api/v1/portfolio/dividends?limit=8',
			'/api/v1/exchange-rates',
		]);
		for (const [, options] of f.request.mock.calls)
			expect(options).toMatchObject({
				method: 'GET',
				credentials: 'omit',
				redirect: 'error',
				cache: 'no-store',
				headers: { Authorization: 'Bearer test-access' },
				signal: expect.any(AbortSignal),
			});
		expect(JSON.stringify(result)).not.toContain('test-access');
	});
	it('keeps a deployment subdirectory in each endpoint', async () => {
		const f = fixture();
		const connection = parseConnection({
			name: 'Test',
			serverUrl: 'https://portfolio.example/app',
			clientId: 'test',
			scopes: ['portfolio:read'],
			allowInsecureHttp: false,
		});
		await new DashboardClient(connection, f.request).load('test-access');
		expect(
			f.request.mock.calls.every(([url]) =>
				new URL(String(url)).pathname.startsWith('/app/api/v1/'),
			),
		).toBe(true);
	});
	it('reports partial failures without substituting zero or throwing away working sections', async () => {
		const f = fixture();
		f.request.mockImplementation(async (input) =>
			new URL(String(input)).pathname.endsWith('/summary')
				? new Response('', { status: 503 })
				: Response.json({ items: [] }),
		);
		const result = await f.client.load('test-access');
		expect(result.summary).toEqual({ data: null, issue: 'data-unavailable' });
		expect(result.dividends).toEqual({ data: [], issue: null });
		expect(result.rates).toEqual({ data: null, issue: 'invalid-response' });
	});
	it.each([
		[403, 'permissions'],
		[500, 'server'],
	] as const)('sanitizes HTTP %i errors', async (status, issue) => {
		const f = fixture();
		f.request.mockResolvedValue(
			new Response('private server diagnostics', { status }),
		);
		expect((await f.client.load('test-access')).summary).toEqual({
			data: null,
			issue,
		});
	});
	it('passes 401 to the serialized session refresh mechanism', async () => {
		const f = fixture();
		f.request.mockImplementation(async () => new Response('', { status: 401 }));
		await expect(f.client.load('test-access')).rejects.toMatchObject({
			issue: 'expired',
		});
	});
	it('sanitizes transport errors and malformed or oversized response bodies', async () => {
		const f = fixture();
		f.request.mockRejectedValueOnce(new Error('secret diagnostics'));
		f.request.mockResolvedValueOnce(
			new Response('<html></html>', {
				headers: { 'Content-Type': 'text/html' },
			}),
		);
		f.request.mockResolvedValueOnce(
			Response.json({ items: [], extra: 'x'.repeat(256 * 1024) }),
		);
		const result = await f.client.load('test-access');
		expect(result.summary.issue).toBe('network');
		expect(result.dividends.issue).toBe('invalid-response');
		expect(result.rates.issue).toBe('invalid-response');
	});
	it('preserves null statistics and strips fields outside the renderer contract', () => {
		const parsed = parseSummary({
			...summary,
			performance: null,
			serverInternal: 'hidden',
		});
		expect(parsed.performance).toBeNull();
		expect(parsed).not.toHaveProperty('serverInternal');
		expect(parseDividends(dividendResponse)[0]).not.toHaveProperty('dividend');
	});
	it('rejects non-numeric money, missing fields, invalid dates and duplicate rates', () => {
		expect(() =>
			parseSummary({
				...summary,
				total: { ...summary.total, currentValue: '123 Kč' },
			}),
		).toThrow();
		expect(() =>
			parseSummary({
				...summary,
				total: { ...summary.total, profit: Infinity },
			}),
		).toThrow();
		expect(() => parseSummary({ ...summary, performance: {} })).toThrow();
		const dividends = structuredClone(dividendResponse);
		dividends.items[0]!.dividend.exDate = '2026-02-30';
		expect(() => parseDividends(dividends)).toThrow();
		expect(() =>
			parseRates({ items: Array.from({ length: 12 }, () => rates[0]) }),
		).toThrow();
	});
	it('coalesces renderer loads and returns no data without an authenticated session', async () => {
		const f = fixture();
		const authorized = vi.fn(async () => undefined);
		const auth = {
			getState: vi.fn((): SessionState => ({
				revision: 1,
				status: 'signed-in',
				user: { id: 'test', name: 'Test', username: 'test' },
				issue: null,
				connection: { name: 'Test', serverUrl: 'https://example.test' },
			})),
			readAuthenticated: async <T>(read: (token: string) => Promise<T>) => {
				await authorized();
				return read('test-access');
			},
		};
		const service = new DashboardService(auth, f.client);
		const first = service.get();
		expect(service.get()).toBe(first);
		await first;
		expect(authorized).toHaveBeenCalledTimes(1);
		authorized.mockRejectedValueOnce(new AuthError('expired'));
		expect((await service.get()).summary.issue).toBe('session');
		auth.getState.mockReturnValue({
			...auth.getState(),
			status: 'signed-out',
			user: null,
		});
		expect((await service.get()).summary).toEqual({
			data: null,
			issue: 'session',
		});
		expect(authorized).toHaveBeenCalledTimes(2);
	});
});
