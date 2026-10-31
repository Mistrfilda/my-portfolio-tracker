import { describe, expect, it, vi } from 'vitest';
import { defaultConnection, parseConnection } from '../../src/main/auth/config';
import { AuthError } from '../../src/main/auth/errors';
import type { SessionState } from '../../src/shared/session';
import { StocksClient, StocksService } from '../../src/main/stocks/client';
import { parseStockPortfolio } from '../../src/main/stocks/contracts';
import { stocksFixture } from '../fixtures/stocks';

describe('stock portfolio API', () => {
	it('reads the fixed personal endpoint, preserving subdirectories and request restrictions', async () => {
		const data = stocksFixture();
		const request = vi.fn<typeof fetch>(async () => Response.json(data));
		const connection = parseConnection({
			name: 'Test',
			serverUrl: 'https://example.test/app',
			clientId: 'test',
			scopes: ['portfolio:read'],
			allowInsecureHttp: false,
		});
		const result = await new StocksClient(connection, request).load(
			'test-token',
		);
		expect(result).toEqual({ data, issue: null });
		expect(request).toHaveBeenCalledExactlyOnceWith(
			new URL('https://example.test/app/api/v1/portfolio/stocks'),
			expect.objectContaining({
				method: 'GET',
				credentials: 'omit',
				redirect: 'error',
				cache: 'no-store',
				headers: {
					Accept: 'application/json',
					Authorization: 'Bearer test-token',
				},
				signal: expect.any(AbortSignal),
			}),
		);
		expect(JSON.stringify(result)).not.toContain('test-token');
	});
	it.each([
		[403, 'permissions'],
		[503, 'data-unavailable'],
		[404, 'server'],
		[500, 'server'],
	] as const)('sanitizes HTTP %i', async (status, issue) => {
		const client = new StocksClient(
			defaultConnection,
			async () => new Response('private diagnostics', { status }),
		);
		expect(await client.load('test')).toEqual({ data: null, issue });
	});
	it('passes expired access to session refresh and sanitizes transport failures', async () => {
		await expect(
			new StocksClient(
				defaultConnection,
				async () => new Response('', { status: 401 }),
			).load('test'),
		).rejects.toBeInstanceOf(AuthError);
		expect(
			await new StocksClient(defaultConnection, async () => {
				throw new Error('private diagnostics');
			}).load('test'),
		).toEqual({ data: null, issue: 'network' });
	});
	it('allows larger position portfolios while bounding responses at two MiB', async () => {
		const request = vi.fn<typeof fetch>();
		const client = new StocksClient(defaultConnection, request);
		request.mockResolvedValueOnce(
			Response.json({ ...stocksFixture(), extra: 'x'.repeat(300 * 1024) }),
		);
		expect((await client.load('test')).data).toEqual(stocksFixture());
		request.mockResolvedValueOnce(
			Response.json({ ...stocksFixture(), extra: 'x'.repeat(2 * 1024 * 1024) }),
		);
		expect((await client.load('test')).issue).toBe('invalid-response');
		request.mockResolvedValueOnce(
			new Response('<html>Error</html>', {
				headers: { 'Content-Type': 'text/html' },
			}),
		);
		expect((await client.load('test')).issue).toBe('invalid-response');
	});
	it('preserves null yields, broker currencies and numeric precision and strips private fields', () => {
		const data = stocksFixture();
		data.items[0]!.dividends.yieldOnPricePercent = null;
		const parsed = parseStockPortfolio({
			...data,
			internal: 'private',
			items: data.items.map((item) => ({ ...item, owner: 'hidden' })),
		});
		expect(parsed).toEqual(data);
	});
	it.each([
		{ pieces: 1.5 },
		{ averagePurchasePrice: '100 USD' },
		{ currentValueCzk: Infinity },
		{ brokerCurrency: 'BTC' },
		{ dividends: {} },
		{ stock: {} },
	])('rejects invalid holding values: %j', (changes) => {
		const data = stocksFixture();
		expect(() =>
			parseStockPortfolio({
				...data,
				items: [{ ...data.items[0], ...changes }],
			}),
		).toThrow();
	});
	it('rejects invalid purchase dates and missing position amounts', () => {
		const data = stocksFixture();
		data.items[0]!.positions[0]!.orderDate = '2026-02-30';
		expect(() => parseStockPortfolio(data)).toThrow();
		expect(() =>
			parseStockPortfolio({
				...data,
				items: [{ ...data.items[0], positions: [{}] }],
			}),
		).toThrow();
	});
	it('coalesces loads and returns no data after session expiry or logout', async () => {
		const request = vi.fn<typeof fetch>(async () =>
			Response.json(stocksFixture()),
		);
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
				return read('test');
			},
		};
		const service = new StocksService(
			auth,
			new StocksClient(defaultConnection, request),
		);
		const pending = service.get();
		expect(service.get()).toBe(pending);
		expect((await pending).data).toEqual(stocksFixture());
		expect(authorized).toHaveBeenCalledTimes(1);
		authorized.mockRejectedValueOnce(new AuthError('expired'));
		expect(await service.get()).toEqual({ data: null, issue: 'session' });
		auth.getState.mockReturnValue({
			...auth.getState(),
			status: 'signed-out',
			user: null,
		});
		expect(await service.get()).toEqual({ data: null, issue: 'session' });
		expect(authorized).toHaveBeenCalledTimes(2);
	});
});
