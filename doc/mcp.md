# Stock MCP Server (LAN Read-Only)

Aplikace poskytuje bezstavový read-only **Model Context Protocol (MCP)** server na endpointu `/api/mcp` postavený nad oficiálním `mcp/sdk` a stávajícím OAuth 2.1 serverem (`src/OAuth2/`).

Umožňuje lokálním AI agentům v místní síti (Claude Code, Codex CLI, Gemini CLI, Cursor) interaktivně analyzovat tvoje portfolio, watchlisty, valuace, dividendy, cenovou historii, historické AI analýzy, investiční plány a spouštět in-memory what-if simulace nákupu bez jakéhokoli zápisu do databáze.

---

## Bezpečnost a LAN-only provoz

- **Pouze lokální síť (LAN)**: Server je určen pro provoz na lokální produkční instanci bez zveřejnění do internetu. Cloudové konektory volající MCP z externích serverů (např. webový ChatGPT Connector) se do izolované LAN záměrně nedostanou.
- **Autentizace OAuth 2.1 + PKCE**: Každý požadavek na `/api/mcp` vyžaduje platný uživatelský Bearer access token se scopem `mcp:read` (`requireUser: true`). Client-credentials tokeny bez uživatele jsou odmítnuty (`403`).
- **Izolace per `AppAdmin`**: Veškeré pozice, jednoduchý watchlist, `investorInstructions`, AI analýzy, dividendové záznamy a investiční plány jsou striktně filtrovány podle přihlášeného uživatele z tokenu.
- **Striktní read-only režim**: Žádný MCP nástroj nemění databázi, nespouští RabbitMQ joby ani nevolá placená AI API. Surové prompty (`generatedPrompt`) ani `rawResponse` se přes MCP nevrací.
- **OAuth 2.1 Discovery & LAN-safe DCR**:
  - `GET /.well-known/oauth-protected-resource` a `GET /.well-known/oauth-protected-resource/api/mcp` (RFC 9728)
  - `GET /.well-known/oauth-authorization-server` (RFC 8414)
  - `POST /api/oauth/register` (RFC 7591) — přísně omezeno na veřejné PKCE klienty (`token_endpoint_auth_method: "none"`), výhradně scope `mcp:read`, loopback `redirect_uri` (`http://127.0.0.1`, `http://localhost`, `http://[::1]`) a rate limit (10 požadavků/min/IP).

### Konfigurace (`config/config.neon` / `config/config.local.neon`)

```neon
parameters:
	api:
		oauth:
			dynamicClientRegistrationEnabled: true
			publicBaseUrl: null
```

- `dynamicClientRegistrationEnabled`: nastavení na `false` vypne `POST /api/oauth/register` (vrací `403`) a skryje `registration_endpoint` z discovery metadat. V tom případě zaregistruj klienta ručně přes CLI:

```bash
php bin/console oauth:client:create "Claude Code" --public --scope=mcp:read --redirect=http://localhost:54321/callback
```

- `publicBaseUrl`: volitelné přepsání veřejné URL (např. `https://portfolio.home.arpa`), pokud aplikace běží za reverzní proxy bez `X-Forwarded-Proto` / `X-Forwarded-Host`.

---

## Dostupné MCP nástroje (9 read-only tools)

| Nástroj | Popis |
| --- | --- |
| `get_portfolio_overview` | Souhrn portfolia v CZK, otevřené pozice s váhami, sektorová a měnová alokace, `investorInstructions`. |
| `get_watchlist` | Sledované `StockAsset` s konsenzem fair value a margin of safety + jednoduchý watchlist s `recommendedEntryPrice`. |
| `search_stocks` | Vyhledání akcie podle tickeru, názvu nebo sektoru/odvětví s příznaky `inPortfolio`, `inWatchlist`, `hasValuation`. |
| `get_stock_valuation_detail` | 36 fundamentů s `importedAt`, 13 valuačních modelů (volitelně bez scénářů), konsenzus, margin of safety, srovnání s odvětvím, rozdíly oproti předchozímu importu a souhrn vlastní pozice. |
| `get_stock_price_history` | Cenová historie (`1M`, `3M`, `6M`, `1Y`, `ALL`) se vzorkováním na `maxPoints`, změnou za období a vzdáleností od 52W high/low. |
| `get_stock_dividends` | Pro `scope="stock"`: yield, payout ratio, safety score, nadcházející a nedávné dividendy. Pro `scope="portfolio"`: roční/měsíční souhrn a forecast v CZK. |
| `get_ai_analyses` | Poslední AI analýzy (`portfolio`, `watchlist`, `market`, `daily_brief`, `single_stock`), akční checklisty a historie per-stock hodnocení bez surových promptů. |
| `get_investment_plans` | Uložené investiční plány s alokacemi, dopadem na portfolio, alternativami, riziky a odkazem na referenční analýzu. |
| `simulate_stock_purchase` | In-memory what-if simulace nákupu (podle částky v libovolné podporované měně nebo podle počtu kusů, volitelně s `priceOverride`): nové kusy, průměrná nákupní cena, váha pozice i sektoru před/po a odhad roční dividendy. |

## Dostupné MCP prompty (3)

- `evaluate_buy_candidate(ticker, budgetCzk?)` — kompletní vyhodnocení jedné akcie oproti `investorInstructions`, valuacím, dividendám, AI analýzám a volitelné simulaci nákupu.
- `compare_buy_candidates(tickers, budgetCzk?)` — srovnání 2–5 tickerů oddělených čárkou vedle sebe.
- `portfolio_rebalance_check()` — revize koncentrace pozic/sektorů, watchlistu, posledních AI checklistů a investičních plánů.

---

## Napojení lokálních klientů v LAN

Nahraď `https://portfolio.home.arpa` adresou své běžící instance v lokální síti (při prvním připojení se otevře prohlížeč s přihlášením přes `/api/oauth/authorize` a klient si automaticky obnovuje tokeny po dobu 30 dní).

### Claude Code

```bash
claude mcp add --transport http portfolio-tracker https://portfolio.home.arpa/api/mcp
```

### Codex CLI

Přidej do `~/.codex/config.toml`:

```toml
[mcp_servers.portfolio_tracker]
url = "https://portfolio.home.arpa/api/mcp"
```

Poté spusť `codex mcp login portfolio_tracker`.

### Gemini CLI

Přidej do `~/.gemini/settings.json`:

```json
{
  "mcpServers": {
    "portfolio-tracker": {
      "httpUrl": "https://portfolio.home.arpa/api/mcp"
    }
  }
}
```

### Cursor

Přidej do `.cursor/mcp.json` (nebo uživatelské konfigurace Cursoru):

```json
{
  "mcpServers": {
    "portfolio-tracker": {
      "url": "https://portfolio.home.arpa/api/mcp"
    }
  }
}
```
