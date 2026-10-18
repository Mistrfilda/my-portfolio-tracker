# Oddělení uživatelských dat

Plán oddělení osobních dat podle účtu `AppAdmin`. Číslování a rozsah vycházejí z dohodnuté tabulky níže. Menší změny jsou podkroky, nikoli náhrada celého kroku.

Aktualizováno 18. 10. 2026. **Kroky 1–6 jsou hotové v popsaném rozsahu.** Krok 3a a Portu portfolia jsou v commitu `e8aad85`; podkroky 3b–3d jsou v commitu `ed1d260`, včetně automatických kontrol, migrace webové databáze a ověření webu. Krok 4 je v commitu `8aae230`. Krok 5 je v commitu `d02e1d1`, včetně Filipem potvrzené migrace webové databáze. Krok 6 je dokončený a ověřený v pracovní větvi, včetně Filipem potvrzené migrace a průchodu webem; commit doplnit po vytvoření Filipem. Následuje krok 7a (cíle).

## Dohodnutý rozsah

- Nejprve aplikace a web; samostatný průchod API přijde později.
- Existující osobní data při migracích přiřadit účtu `filip`. `filip2` je testovací účet.
- Společný katalog akcií, tržní data a nastavení akcií včetně dividendové daně zůstávají společné. Osobní jsou pozice a další uživatelské záznamy.
- Web získává vlastníka z přihlášení. Úlohy na pozadí ho dostávají výslovně nebo ho odvozují ze zpracovávaného osobního záznamu.
- Vlastníka potomka odvozovat z rodiče tam, kde to stačí. Kontrolovat také kombinace více ID a výběry ve formulářích.

## Původní plán a hlavní ověření

| Krok | Co změnit | Hlavní ověření |
|---|---|---|
| 1. Základ práce s vlastníkem | Využít existující základ v BaseRepository, sjednotit filtrování podle účtu a přístup přes ID. Web bere účet z přihlášení, úlohy na pozadí dostávají vlastníka výslovně. Ověřit nejprve na jednom repository. | Dva uživatelé: každý načte pouze své záznamy, cizí ID je nedostupné. Chybějící uživatel nesmí znamenat „vrať všechno“. |
| 2. Pozice a jejich formuláře | Postupně Stock → Crypto → Portu, včetně uzavřených pozic a navázané historie. Vlastník při založení, kontrola při editaci, uzavření a smazání. Opravit také přepis vlastníka při úpravě Portu pozice. | Uživatel nemůže změnit ani uzavřít cizí pozici. Běžná editace nikdy nepřevádí vlastnictví. |
| 3. Samostatné osobní moduly | Po menších změnách: watchlist; bankovní účty, pohyby a štítky; pracovní příjmy; domácnosti a zařízení. U potomků odvozovat vlastníka z rodiče, kde to stačí. Upravit i výběry ve formulářích a potřebná unikátní omezení. | Nelze přiřadit cizí účet, štítek nebo zařízení. Stejné období pracovního příjmu může existovat pro dva různé uživatele. |
| 4. Souhrny portfolia a web | Projít detail aktiva, dashboard, grafy, uzavřené pozice a všechny výpočty přes kolekce pozic společných aktiv. Osobní stránky zpřístupnit běžnému účtu tam, kde dnes vyžadují sysadmina; správu společného katalogu zachovat podle současných oprávnění. | Oba vlastní stejnou akcii, jeden 10 kusů a druhý 20. Každý vidí správný vlastní počet, hodnotu i zisk. Prázdný účet má prázdné portfolio. |
| 5. Směrování notifikací | Doplnit příjemce osobních zpráv a vazbu na konfiguraci Discordu. Oddělit společné tržní zprávy od zpráv obsahujících osobní portfolio. Zachovat příjemce při zpracování fronty a opakování doručení. | Zpráva A použije konfiguraci A. Účet bez konfigurace nic osobního neodešle. Vše s náhradou odesílače, bez skutečného Discordu. |
| 6. Dividendy a prognózy | Společná dividendová událost a daň zůstanou. Osobní dividendový záznam bude pro kombinaci uživatel + událost; prognózy a jejich výpočty dostanou vlastníka. | Jedna dividenda, dva vlastníci akcie, dvě správné částky. Opakovaný výpočet nevytvoří duplicity ani nepřepíše druhého uživatele. |
| 7. Cíle a AI analýzy | Dvě samostatné změny. Cíle počítají z dat vlastníka a zachovají ho při opakování. AI má osobní zadání, snímek portfolia, výsledky, plány a exporty; také výběr poslední analýzy akcie respektuje účet. Úloha pozná vlastníka ze zpracovávaného záznamu. | Cíl A nereaguje na dividendy B. AI vstup A neobsahuje pozice B. Cizí výsledek ani export nelze načíst přes ID. |
| 8. Uložení a čtení statistik | Přiřadit historii Filipovi, doplnit vlastníka snímků, měsíčních výsledků a reportů. Upravit dotazy, unikátní klíče, cache i hodnoty zapamatované uvnitř služeb. Nejprve ověřit výpočty pro jednoho výslovně předaného uživatele. | Dva účty mohou mít záznam ve stejný čas i měsíční výsledek pro stejný měsíc. Přepočet A nezmění historii ani výsledky B. |
| 9. Crony a dokončení úloh na pozadí | Statistický cron projde účty postupně. Pro každý zachová dnešní pořadí: snímek → přepočet výkonnosti → doplnění čerstvých hodnot. Prověřit také ostatní osobní úlohy na pozadí. | Zpracovat A a B v jednom procesu, potom v opačném pořadí. Výsledky musí být stejné. Ověřit prázdný účet a chování při chybě jednoho uživatele. |

## Stav jednotlivých kroků

- [x] **1. Základ práce s vlastníkem** — commit `1a8f237`; společný filtr v `BaseRepository`, přístup přes ID a explicitního vlastníka ověřen na `StockPositionRepository`.
- [x] **2. Pozice a jejich formuláře** — commit `0fdb398`; přístup k vlastním pozicím, uzavřeným pozicím a historii, ochrana editace a uzavření, zachování vlastníka při úpravě Portu pozice.
- [x] **3. Samostatné osobní moduly** — dokončeny všechny podkroky 3a–3d, automatické kontroly, migrace a ověření webu.
	- [x] **3a. Jednoduchý watchlist** — commit `e8aad85`; vlastník, seznamy, formuláře, přístup přes ID, mazání a unikátnost tickeru v rámci účtu.
	- [x] **Doplnění k pozicím: Portu portfolia** — stejný commit; vlastník portfolia a ochrana proti založení či přesunu pozice do cizího portfolia. Toto doplnění nenahrazuje žádný ze zbývajících osobních modulů.
	- [x] **3b. Bankovní účty, pohyby a štítky** — vlastnictví, přístupy a formuláře, importy a štítkování podle účtu.
	- [x] **3c. Pracovní příjmy** — osobní přehled a období, import podle `workMonthlyIncome.ownerUsername` s výchozím `filip`.
	- [x] **3d. Domácnosti, zařízení a jejich záznamy** — vlastník domácnosti, navázané přístupy a kontrola vztahů ve formulářích.
	- [x] **Předání 3b–3d** — celý build prošel; Filip potvrdil migraci webové databáze, následně ověřena data a web v izolovaném Playwrightu.
- [x] **4. Souhrny portfolia a web** — výpočty a web podle vlastníka, přechodná ochrana nepřevedených widgetů, automatické kontroly a ověření webu dokončeny. Bez nové migrace.
- [x] **5. Směrování notifikací** — uložený příjemce, osobní konfigurace Discordu, oddělené společné a osobní trendy, ověřený retry, automatické kontroly a potvrzená migrace s ověřením webové databáze a webu.
- [x] **6. Dividendy a prognózy** — osobní záznamy, prognózy, výpočty a web podle vlastníka, ověřené opakování a přístupy, celý build a potvrzená migrace s kontrolou databáze a webu.
- [ ] **7. Cíle a AI analýzy.**
	- [ ] **7a. Cíle a jejich opakování.**
	- [ ] **7b. AI zadání, snímky, výsledky, plány a exporty.**
- [ ] **8. Uložení a čtení statistik.**
- [ ] **9. Crony a dokončení úloh na pozadí.**

Krok 2 pokrývá existující operace nad jednotlivými pozicemi. Jejich fasády a webové formuláře nyní samostatné mazání pozic nenabízejí; nevznikala nová funkce mazání. Souhrnné výpočty nad pozicemi patří do kroku 4. Krok 1 připravil rozhraní pro explicitního vlastníka, přechod všech úloh na něj dokončí příslušné doménové kroky a krok 9.

## Podkroky kroku 3

### 3b Bankovní účty, pohyby a štítky

- [x] Upravit vlastnictví a přístupy k účtům v `src/Cash/Bank/Account/`, včetně seznamů a výběrů ve formulářích.
- [x] Oddělit příjmy a výdaje v `src/Cash/Income/Bank/` a `src/Cash/Expense/Bank/`; vlastnictví pohybu odvozovat z účtu, kde to stačí. Zahrnout související webové přehledy a importy.
- [x] Oddělit štítky v `src/Cash/Expense/Tag/`, včetně rodičovských štítků a pravidel přiřazování. Zabránit vazbám mezi různými vlastníky.
- [x] Připravit a otestovat migraci osobních dat pod `filip`; unikátnost bankovních pohybů nyní platí pro účet + identifikátor.
- [x] Ověřit dva účty: cizí ID není dostupné, nelze přiřadit cizí bankovní účet ani štítek, import a úprava A nemění B.

### 3c Pracovní příjmy

- [x] Doplnit vlastníka v `src/Cash/Income/WorkMonthlyIncome/` a upravit načítání, zápis a webový přehled.
- [x] Upravit výběr období a unikátní omezení pro kombinaci vlastník + rok + měsíc.
- [x] Připravit načítání a zápis pro výslovně předaného vlastníka i pro import; dokončení spouštění úloh pro jednotlivé účty navazuje v kroku 9.
- [x] Připravit a otestovat převod historie pod `filip` a ověřit stejné období u dvou uživatelů; opakovaný zápis A nesmí přepsat B.

### 3d Domácnosti a zařízení

- [x] Oddělit domácnosti v `src/Home/`, zařízení a záznamy zpřístupňovat přes vlastníka domácnosti.
- [x] Zahrnout seznamy, formuláře, grafy a přístup přes ID; při více parametrech ověřit také vztah zařízení k domácnosti a záznamu k zařízení.
- [x] Prověřit unikátní omezení a připravit potřebnou migraci pod `filip`. Rozlišit osobní unikátnost od identifikátoru fyzického zařízení.
- [x] Ověřit, že nelze přiřadit cizí domácnost nebo zařízení ani načíst cizí záznam. Samostatná kontrola API zůstává v pozdější fázi.

### Rozhodnutí a návaznosti 3b–3d

- Jeden Harvest zůstává v konfiguraci. Nový parametr `workMonthlyIncome.ownerUsername` má veřejný výchozí `filip`; dosavadní lokální nastavení se nemusí měnit. Chybějící účet zastaví import před HTTP požadavkem a zápisem, bez náhrady přihlášeným účtem.
- Dosavadní příjmové cíle zatím nemají vlastníka. Jejich dva resolvery používají výslovně nakonfigurovaného vlastníka příjmů a karta cíle na stránce pracovních příjmů se zobrazuje pouze tomuto účtu. V kroku 7 nahradit tuto přechodnou vazbu vlastnictvím konkrétního cíle.
- Nový požadavek na automatické štítkování nese `appAdminId`. Staré zprávy bez vlastníka a současný CLI příkaz procházejí uživatele odděleně; žádné pravidlo účtu A se nepoužije na výdaje B. Globální čas posledního zpracování zůstává provozním údajem.
- Domácnost je vlastníkem zařízení a měření; `createdBy` u měření zůstává informací o autorovi zápisu. Unikátní interní identifikátor fyzického zařízení zůstává globální kvůli současnému API senzorů.
- Dosavadní výběr zařízení přes interní ID a seznam zařízení pro API se v této fázi nemění. Web načítá zařízení přes vlastní domácnost. Samostatný průchod API a omezení servisních klientů zůstávají mimo tuto fázi.
- Bankovní a pracovní část přehledů používá data vlastníka. Dividendová část přehledu navazuje na krok 6; obecné portfolio souhrny na krok 4.

## Krok 4: souhrny portfolia a web

- [x] Stock, Crypto a Portu souhrny načítají pozice přes vlastníka. Výslovný `AppAdmin` slouží úlohám na pozadí; vynechaný parametr znamená přihlášeného uživatele a bez přihlášení skončí chybou.
- [x] Detaily a seznamy společných akcií a kryptoměn počítají počet kusů, hodnotu, průměrnou nákupní cenu a zisk pouze z vybraných pozic vlastníka. Otevřené a uzavřené pozice se zpracují samostatně, i když stejné aktivum obsahuje oba typy. Zachované je řazení podle data nákupu.
- [x] Dashboard a jeho denní akciové tabulky respektují vlastníka. Prázdné portfolio má nulové hodnoty i procentní zisk.
- [x] Detaily akcií a kryptoměn jsou dostupné běžnému přihlášenému účtu; správa společného katalogu zůstává pro sysadmina a odpovídá tomu menu.
- [x] Webové grafy a widgety nečtou nepřevedenou osobní historii pro jiný účet. Finanční nezávislost používá vlastní aktuální portfolio, příjmy a výdaje; historické výnosy a příspěvky jsou do kroku 8 pouze pro původního vlastníka.
- [x] Opravené dvě chyby odhalené naplněným kryptoměnovým scénářem: inicializace neuzavřené pozice a neexistující dividendové metody v šabloně uzavřených kryptopozic.
- [x] Závěrečný build, kontrola PhpStormu a průchod webovým dumpem.

### Přechodné vazby a návaznosti kroku 4

- `LegacyPortfolioOwnerProvider` výslovně určuje původního vlastníka `filip`, bez změny lokální konfigurace. Chybějící účet není nahrazen přihlášeným uživatelem. Jde o přechodnou pomůcku pro data čekající na kroky 6–9.
- Na dashboardu jsou nepřevedené dividendové hodnoty, cíle a historická výkonnost pouze pro Filipa. V detailu akcie totéž platí pro osobní dividendy, AI analýzu a AI srovnání valuace; zablokované jsou i přímé požadavky na tyto komponenty. Běžnému účtu zůstávají společné tržní ceny a valuace. Seznam pozic používá pouze společnou dividendu na akcii a vlastní nákupní cenu.
- Historické a dividendové grafy vracejí ostatním účtům prázdná data i při samostatném AJAX požadavku. Koláč investovaných peněz používá vlastní investice; nepřevedené dividendové záznamy připojuje jen Filipovi.
- Ukládání dosavadního statistického snímku, resolver investičního cíle a sestavení dosavadního AI portfolia předávají Filipa výslovně, aby fungovaly i bez webového přihlášení. Plné vlastnictví cílů, AI, dividend a historických statistik tím dokončeno není; další samostatné stránky těchto modulů i jejich výpočty patří do kroků 6–9.
- API dashboardu sdílí změněný builder. Do samostatného průchodu API předává původního vlastníka výslovně pro uživatelské i klientské OAuth tokeny, aby dosavadní klientský token bez účtu nepřestal fungovat. Tento přechod je popsaný v OpenAPI. API zatím **nelze používat jako oddělené portfolio jiného uživatele**; ostatní API serializéry čekají na vlastní průchod.
- V krocích 6–9 postupně nahradit přechodné podmínky skutečnými dotazy podle vlastníka konkrétního záznamu. Společné kolekce pozic na entitách se nemění; zbývající použití v dividendových výpočtech, `PortfolioPeriodStatisticBuilder` a API serializéru vyžadují navazující úpravy.
- Krok 4 nepřidává databázovou migraci: mapování ani schéma se nemění. Nepouštěl se přepočet statistik, AI úloha ani odesílání notifikací na webové databázi.

## Krok 5: směrování notifikací

- [x] Osobní notifikace ukládá neměnného příjemce `AppAdmin`; `null` označuje společnou tržní zprávu. Vazba neumožňuje smazáním účtu převést osobní zprávu na společnou.
- [x] `NotificationFacade::createForRecipient()` vyžaduje vlastníka výslovně; `create()` slouží společným tržním zprávám. Milník cíle bez příjemce nelze vytvořit.
- [x] Nové dividendové události obsahují jen společnou částku na akcii. Report trendů akcií a krypta obsahuje jen tržní ceny; osobní hodnoty Portu jsou v samostatných zprávách seskupených podle vlastníka portfolia.
- [x] Discord vybírá konfiguraci uloženého příjemce, bez závislosti na přihlášení. Účet bez konfigurace nic osobního neodesílá, negeneruje obrázek ani nevolá Gotenberg.
- [x] Formát zprávy RabbitMQ zůstává stejný: ID odkazuje na uloženou notifikaci včetně příjemce a snímku dat. Opakované zpracování znovu načte stejný záznam; stav `SENT` už nic neposílá.
- [x] Ověřit migraci historie pod `filip`, chybějící účet, ochranu návratu a načtení příjemce po vyprázdnění EntityManageru.
- [x] Dokončit celý build a kontroly PhpStormu.
- [x] Filip potvrdil migraci webové databáze; převod historie i start aplikace ověřeny přes PhpStorm a izolovaný Playwright.

### Konfigurace a návaznosti kroku 5

- Dosavadní `notifications.discord.webhooks` zůstává konfigurací společných zpráv. Pro osobní zprávy ji smí použít jen účet určený `notifications.discord.legacyRecipientUsername`, výchozí `filip`. Stávající lokální konfigurace se nemusí měnit.
- `notifications.discord.userWebhooks` je mapa podle uživatelského jména. Například `userWebhooks: {filip2: {goals_updates: null, trend_alert_default: null}}` připraví samostatnou konfiguraci pro testovací účet bez odesílání. Skutečné webhooky se doplňují pouze v soukromé konfiguraci.
- Uživatelská mapa se používá celá, nedoplňuje se ze společné konfigurace. Chybějící typ nemá cizí náhradu; prázdná mapa vypne všechny osobní zprávy daného účtu i pro Filipa. `null` u konkrétního typu nebo období výslovně vypíná danou zprávu. Trend bez klíče pro konkrétní období může použít `trend_alert_default` téhož účtu.
- Cíle podporují původní konfigurační klíč `goals_update` i kanonický `goals_updates`; kanonický klíč má přednost včetně hodnoty `null`. Obecný historický klíč `default` se nadále nepoužívá jako náhradní příjemce.
- Dosavadní cíle nemají vlastníka do kroku 7. Jejich zprávy proto nyní výslovně používají `LegacyPortfolioOwnerProvider` a patří Filipovi; v kroku 7 nahradit příjemcem konkrétního cíle.
- Migrace `Version20261018112629` přiřadí všechny existující notifikace Filipovi, včetně starých smíšených trendů. Obsah, ID a stav zůstanou zachované; migrace nic nepublikuje do fronty ani neodesílá. Nové společné zprávy mají příjemce `null`. Chybějící Filip při existující historii zastaví migraci před DDL; návrat je zakázán, pokud má notifikace jiný uživatel.
- Stav `SENT` zůstává dle stávajícího chování dokončením zpracování i při vypnutém webhooku. Pozdější doplnění konfigurace tak samo neodešle přeskočenou historii. Kontrola `SENT` chrání před opětovným zpracováním dokončené zprávy, nezaručuje přesně jedno doručení při pádu mezi HTTP požadavkem a zápisem stavu nebo při souběžných consumerech.
- Změna nemění Discord logger, transport RabbitMQ ani plánování cronů. Při nasazení použít migrované schéma a restartovat běžící notification consumer, aby načetl nový kód i konfiguraci.

## Krok 6: dividendy a prognózy

- [x] Osobní dividendový záznam má neměnného vlastníka; unikátní klíč je uživatel + dividendová událost. Událost, daň a nastavení akcie zůstávají společné.
- [x] Výpočet vybírá vlastní otevřené i historicky způsobilé uzavřené pozice. CLI prochází účty výslovně; opakované zpracování aktualizuje jen záznamy daného vlastníka.
- [x] Prognózy mají vlastníka; položky ho odvozují z prognózy a mají unikátní klíč prognóza + akcie. Výchozí prognóza se vybírá v rámci účtu a roku.
- [x] Přístup přes ID, editace, nastavení výchozí prognózy, vlastní hodnoty položky i vnořené formuláře kontrolují vlastníka. Signál přepočtu nemůže podstrčit ID jiné prognózy.
- [x] Přijaté částky, výhled, cashflow, dashboard, detail akcie, dividendové grafy, souhrn uzavřených pozic a dividendová část přehledu výdajů používají vlastní data.
- [x] Nové zprávy pro přepočet všech prognóz nesou `appAdminId`. Staré zprávy bez něj procházejí účty odděleně; přepočet jedné prognózy odvozuje vlastníka z uloženého záznamu.
- [x] Připravit a otestovat migraci historie, chybějícího Filipa, duplicit, unikátních klíčů a ochrany návratu.
- [x] Dokončit závěrečný build a kontroly PhpStormu.
- [x] Filip potvrdil migraci webové databáze; převod dat a web ověřeny přes PhpStorm a izolovaný Playwright.

### Migrace a návaznosti kroku 6

- `Version20261018113749` přiřadí existující osobní záznamy a prognózy Filipovi. Položky prognóz patří stejnému účtu přes rodiče. ID, částky, reinvestování, stavy, výchozí prognózy a vlastní hodnoty se zachovají; migrace nic nepřepočítává a nepublikuje úlohy.
- Kontrola před migrací webové databáze našla 217 osobních dividendových záznamů, 4 prognózy a 94 položek. Duplicitní události ani dvojice prognóza + akcie nenalezeny.
- Chybějící Filip při existující historii nebo duplicity zastaví migraci před DDL. Návrat je zakázán, pokud má osobní záznam či prognózu jiný uživatel.
- Přechodné omezení dividend na Filipa z kroku 4 je odstraněné. Statistické výpočty a dividendový resolver dosavadních cílů zatím předávají Filipa výslovně; jejich plné vlastnictví patří do kroků 7–9. AI a nepřevedená statistická historie zůstávají chráněné dosavadní podmínkou.
- API serializéry zůstávají pro samostatný průchod API; tento krok neznamená jeho úplné oddělení podle účtů.
- Při nasazení použít migrované schéma a restartovat job consumer. Existující zprávy zůstávají zpracovatelné; žádný skutečný consumer nebo Discord se při testech nespouští.

## Doklady dokončených částí

| Část | Implementace | Hlavní automatické ověření |
|---|---|---|
| 1 | `1a8f237` | [StockPositionRepositoryOwnershipTest](../tests/Integration/Stock/StockPositionRepositoryOwnershipTest.php): dva vlastníci, cizí ID, nepřihlášený uživatel, explicitní vlastník a změna účtu v jednom procesu. |
| 2 | `0fdb398` | [PositionOwnershipTest](../tests/Integration/Asset/PositionOwnershipTest.php) a [PositionWebAccessTest](../tests/Integration/Asset/PositionWebAccessTest.php): seznamy, ID, editace, uzavření, historie, zachování vlastníka a odmítnutí cizích URL. |
| 3a a Portu portfolia | `e8aad85` | [PersonalAssetOwnershipTest](../tests/Integration/Asset/PersonalAssetOwnershipTest.php): přístupy a vazby podle vlastníka, unikátnost tickeru, mazání watchlistu a přístup běžného účtu. [PersonalAssetOwnershipMigrationTest](../tests/Integration/Migrations/PersonalAssetOwnershipMigrationTest.php): převod historie a ochrany migrace. |
| 3b–3d | `ed1d260` | [PersonalDataOwnershipTest](../tests/Integration/PersonalData/PersonalDataOwnershipTest.php), [PersonalIncomeImportOwnershipTest](../tests/Integration/PersonalData/PersonalIncomeImportOwnershipTest.php), [PersonalDataOwnershipMigrationTest](../tests/Integration/Migrations/PersonalDataOwnershipMigrationTest.php). |
| 4 | `8aae230` | [PortfolioSummaryOwnershipTest](../tests/Integration/Asset/PortfolioSummaryOwnershipTest.php): společná akcie s 10 a 20 kusy, Stock/Crypto/Portu souhrny, otevřené a uzavřené pozice, řazení, A → B → A v jednom procesu, explicitní vlastník bez přihlášení, prázdný účet, vykreslené osobní stránky běžného účtu, ochrana katalogu a nepřevedených widgetů. |
| 5 | `d02e1d1` | [NotificationDeliveryOwnershipTest](../tests/Integration/Notification/NotificationDeliveryOwnershipTest.php), [NotificationRecipientMigrationTest](../tests/Integration/Migrations/NotificationRecipientMigrationTest.php), unit testy Discord směrování a rozdělení trendů. |
| 6 | Pracovní větev; commit doplnit po vytvoření Filipem | [DividendOwnershipTest](../tests/Integration/Stock/DividendOwnershipTest.php), [DividendOwnershipMigrationTest](../tests/Integration/Migrations/DividendOwnershipMigrationTest.php), unit testy dividendových výpočtů, cashflow a přenosu vlastníka úlohou. |

Po implementaci 3a a Portu portfolií prošlo `composer cs-fix && composer build-all`: 793 unit testů a 176 integračních testů, včetně statické analýzy a validace schématu. Kontroly změněných PHP souborů v PhpStormu prošly. V izolovaném Playwrightu byly pod účtem `filip` ověřeny přehledy Portu a watchlistu, jejich editace a formuláře Portu pozic. Oddělení dvou uživatelů pokrývají integrační testy.

Podkroky 3b–3d pokrývají [PersonalDataOwnershipTest](../tests/Integration/PersonalData/PersonalDataOwnershipTest.php), [PersonalIncomeImportOwnershipTest](../tests/Integration/PersonalData/PersonalIncomeImportOwnershipTest.php), [PersonalDataOwnershipMigrationTest](../tests/Integration/Migrations/PersonalDataOwnershipMigrationTest.php) a [WorkMonthlyIncomeOwnerProviderTest](../tests/Unit/Cash/Income/WorkMonthlyIncomeOwnerProviderTest.php). Ověřují cizí ID a vazby, prázdný účet, běžný účet, změnu vlastníka v jednom procesu, import do shodných období a identifikátorů, opakované zpracování, konfigurovaného vlastníka a ochrany migrace.

Po implementaci 3b–3d prošlo `composer cs-fix && composer build-all`: 796 unit testů (3592 assertions), 210 integračních testů (973 assertions), statická analýza, validace mapování a schématu, Latte a kontrola závislostí. PhpStorm nenašel chyby v 59 změněných či nových PHP souborech.

Po potvrzené migraci byly v izolovaném Playwrightu pod účtem `filip` ověřeny seznamy bankovních účtů, výdajů, štítků a bankovních příjmů, pracovní příjmy a přehled výdajů, formuláře bankovního účtu, úpravy a duplikace výdaje, importu včetně výběru vlastních účtů a úpravy štítku. Dále seznam domácností, úprava domácnosti, seznam zařízení s grafy, vytvoření a úprava zařízení a přehled jeho záznamů. Stránky vracely HTTP 200 se správným nadpisem; nebyly zaznamenány chyby JavaScriptu. Webový dump neobsahuje žádná měření zařízení, proto jejich naplněnou historii a oddělení dvou uživatelů ověřují integrační testy. Formuláře ani import nebyly na webu odesílány.

Při průchodu webem nalezen starší nesouvisející problém: `BankAccountGridFactory` vede akcí „Editovat“ na `PortfolioGoal:edit` místo `BankAccount:edit`. Odkaz byl chybný už před touto změnou a zůstává beze změny; samotný formulář bankovního účtu byl ověřen přes správnou adresu. Opravu navigace řešit samostatně.

Samotné dokončení těchto částí ještě neznamená oddělení všech dat aplikace. Například globální metody `StockPositionRepository::findAll()` a `PortuAssetRepository::findAll()` ještě používají další výpočty; jejich spotřebitele je potřeba projít v dalších krocích. Filtrování watchlistu v novém AI snímku rovněž neznamená dokončené oddělení AI analýz z kroku 7.

Po kroku 4 prošlo `composer cs-fix && composer build-all`: **800 unit testů (3607 assertions), 230 integračních testů (1110 assertions)**, statická analýza, styl, PHP/Latte kontrola, validace mapování a schématu a kontrola závislostí. Nový integrační soubor obsahuje 20 scénářů; unit testy doplňují nulové procentní zhodnocení, přechodného vlastníka a nepoužití cizí historie ve finanční nezávislosti. Regresní test API potvrzuje funkčnost dosavadního klientského OAuth tokenu bez uživatelské identity. PhpStorm nenašel chyby v 55 změněných či nových PHP souborech.

V izolovaném Playwrightu byly po kroku 4 pod existujícím přihlášením Filipa ověřeny dashboard, přehledy otevřených a uzavřených pozic Stock i Crypto, detail 3M a Bitcoinu včetně přepnutí na pozice, Portu portfolio a jeho pozice, finanční nezávislost a stránka grafů. Grafy dokončily všech 11 datových AJAX požadavků s HTTP 200; nebyly zaznamenány chyby konzole. Webový dump nemá uzavřené kryptopozice, jejich naplněný přehled pokrývá integrační test. Oddělení dvou vlastníků a prázdného účtu i přístup běžného uživatele ověřují automatické testy; na webu se nezakládala nová data ani druhé přihlášení.

Krok 5 pokrývají [NotificationDeliveryOwnershipTest](../tests/Integration/Notification/NotificationDeliveryOwnershipTest.php) a [NotificationRecipientMigrationTest](../tests/Integration/Migrations/NotificationRecipientMigrationTest.php): uložení příjemce, zachovaný formát fronty, A → B → retry A se změněnou identitou, opětovné načtení z databáze, přeskočení `SENT` a převod historie bez změny obsahu nebo stavu. Unit testy ověřují oddělené Portu reporty, oba účty v jednom resolveru, účet bez konfigurace bez HTTP i Gotenbergu, zpětnou kompatibilitu Filipa, prázdné mapy, explicitní `null`, přednost kanonického klíče, uložení před publikováním a odmítnutí cílové zprávy bez příjemce. Všechny externí služby i publisher jsou nahrazené testovacími náhradami.

Po implementaci kroku 5 prošlo `composer cs-fix && composer build-all`: **812 unit testů (3667 assertions), 232 integračních testů (1140 assertions)**, statická analýza, styl, PHP/Latte kontrola, validace mapování a schématu, kontrola závislostí i agent dokumentace. PhpStorm nenašel chyby v 15 změněných nebo nových PHP souborech; cílená kontrola NEON a syntaxe migrace také prošla. Žádná skutečná zpráva ani úloha RabbitMQ nebyla odeslána.

Po Filipově potvrzení migrace byl na webové databázi ověřen záznam `Migrations\Version20261018112629` z 18. 10. 2026 13:30:59. Všech **9 979** notifikací patří `filip`; počet chybějících, osiřelých nebo cizích příjemců je 0. Počty podle typu a stavu odpovídají stavu před migrací. PhpStorm vracel starý popis tabulky i po introspekci, proto byl nový sloupec ověřen přímo přes `SHOW COLUMNS` a výsledek přes čtecí SQL dotazy. V izolovaném Playwrightu prošel dashboard a přehledy Portu a cílů pod stávajícím přihlášením; adresy i nadpisy odpovídaly a přehledy vracely HTTP 200 bez nových chyb JavaScriptu. Historické chyby konzole z dřívějšího testu neexistujícího ID a faviconu se na kontrolovaných stránkách neopakovaly. Žádný formulář, cron ani skutečný notification consumer nebyl spuštěn.

Krok 6 pokrývají [DividendOwnershipTest](../tests/Integration/Stock/DividendOwnershipTest.php) a [DividendOwnershipMigrationTest](../tests/Integration/Migrations/DividendOwnershipMigrationTest.php). Ověřují dva účty nad stejnou akcií (10 a 20 kusů), vlastní hrubé a čisté částky, prognózy a cashflow, pořadí A → B → B → A v jednom procesu, stabilní ID při opakování, změnu pouze vlastní částky, prázdný účet, historii uzavřené pozice, souhrny, defaulty pro stejný rok, cizí ID a formuláře, vnořený cizí rodič, podstrčené ID přepočtu a chybějící přihlášení. Migrační test kontroluje zachování všech hodnot, ochrany před DDL, vlastnictví přes rodiče a oba unikátní klíče. Unit testy doplňují přenos vlastníka frontou a zpracování starého payloadu.

Po implementaci kroku 6 prošlo `composer cs-fix && composer build-all`: **814 unit testů (3671 assertions), 249 integračních testů (1425 assertions)**, statická analýza, styl, PHP/Latte kontrola, validace mapování a schématu, kontrola závislostí i agent dokumentace. PhpStorm nenašel chyby ve 46 změněných nebo nových PHP souborech a změněné Latte šabloně. Syntaxe migrace i `git diff --check` jsou v pořádku. Odesílač úloh je v nových testech nahrazený; žádný skutečný consumer, HTTP služba ani Discord nebyl spuštěn.

Přepočet v jednom procesu odhalil neaktualizovanou inverzní kolekci záznamů společné dividendy a neinicializované nullable hodnoty nově vytvořené prognózy. Obě chyby jsou opravené a pokryté opakovaným scénářem. Načtení přes pole UUID nyní používá parametrizovaný dotaz. Migrační změny byly vygenerované pomocí `migrations:diff` a doplněné o převod historie a ochrany; webová migrace byla následně potvrzena Filipem a ověřena.

Po Filipově potvrzení byl na webové databázi ověřen záznam `Migrations\Version20261018113749` z 18. 10. 2026 13:53:12. Všech **217 dividendových záznamů, 4 prognózy a 94 položek prognóz** patří `filip`; chybějící nebo cizí vlastník nebyl nalezen. Počty odpovídají kontrole před migrací. Oba vlastnické sloupce jsou `NOT NULL` a unikátní indexy odpovídají mapování. PhpStorm nadále vracel starý popis tabulek, proto byly změny ověřeny přímo přes `SHOW COLUMNS`, `SHOW INDEX` a čtecí SQL agregace.

V izolovaném Playwrightu pod existujícím přihlášením Filipa prošly dashboard, seznam prognóz, naplněné detaily prognóz 2025 a 2026 včetně 19 a 25 formulářů položek, formulář úpravy i vytvoření prognózy, přehled vyplacených dividend, obecný dividendový přehled, dividendová záložka AT&T a přehled uzavřených akciových pozic. Adresy a nadpisy odpovídaly; navigace vracely HTTP 200. Stránka grafů dokončila všech 11 datových požadavků s HTTP 200 a validními datasety, včetně pěti dividendových grafů. Nebyly zaznamenány chyby JavaScriptu. Formuláře se neodesílaly a přepočet ani skutečná fronta se nespouštěly. Dva vlastníky a prázdný účet ověřují integrační testy; webový dump byl kontrolován pouze pod existujícím přihlášením.

## Migrace a předávání jednotlivých změn

- CLI a integrační testy používají databázi `my-portfolio-app-tracker-tests`. PhpStorm a web používají `my-portfolio-tracker`. Integrační testy obnovují tabulky; nespouštět nad stejnou databází současně testy a migrace.
- Připravit a otestovat Doctrine migraci. Migraci webové databáze spouští Filip a potvrdí výsledek; až poté následuje závislé ověření webu. Stejný migrační soubor slouží i pro nasazení do clusteru.
- [Version20261018102336](../migrations/Version20261018102336.php) byla 18. 10. 2026 uživatelem spuštěna na webové databázi a následně ověřena: 1 Portu portfolio a 20 položek watchlistu patří účtu `filip`, nesoulad vlastníka Portu pozice a portfolia je 0. Spuštění v clusteru zde zatím potvrzené není.
- [Version20261018104607](../migrations/Version20261018104607.php) pro 3b–3d byla 18. 10. 2026 spuštěna Filipem na webové databázi a následně ověřena v `doctrine_migrations`. Všechna převáděná data patří `filip`: 2 bankovní účty, 2307 výdajů, 137 příjmů, 71 štítků, 34 měsíčních příjmů, 1 domácnost a 2 zařízení. Počty odpovídají stavu před migrací, nesoulad vlastníků výdaje a hlavního štítku i rodičovského a potomkovského štítku je 0. Test převodu historie, ochran a návratu migrace prošel. Spuštění v clusteru zde zatím potvrzené není.
- Krok 4 nevyžaduje migraci webové databáze ani databáze v clusteru.
- [Version20261018112629](../migrations/Version20261018112629.php) pro krok 5 je ověřená na CLI testovací databázi a byla spuštěna Filipem na webové databázi. Všech 9 979 notifikací nyní patří `filip`; počty zůstaly stejné (9 685 `sent`, 294 `created`). Spuštění v clusteru zatím potvrzené není.
- [Version20261018113749](../migrations/Version20261018113749.php) pro krok 6 je ověřená na CLI testovací databázi a byla spuštěna Filipem na webové databázi. Převod 217 dividendových záznamů a 4 prognóz s 94 položkami pod Filipa je ověřený. Spuštění v clusteru zde zatím potvrzené není.
- Před každou další změnou přečíst tento plán. Po dokončení aktualizovat příslušný podkrok, důkazy ověření a stav migrace; commit doplnit, jakmile je známý.
- Celý krok odškrtnout až po splnění všech jeho podkroků a hlavního ověření. U kroku 3 jsou podmínky splněné: všechny podkroky, potvrzená migrace, automatické kontroly i ověření webu.
- Na konci každé předávané změny uvést její přesné číslo a rozsah, zbývající práci a commit příkaz pro Filipa. Commity provádí Filip.
