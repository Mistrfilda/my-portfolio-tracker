# Oddělení uživatelských dat

Plán oddělení osobních dat podle účtu `AppAdmin`. Číslování a rozsah vycházejí z dohodnuté tabulky níže. Menší změny jsou podkroky, nikoli náhrada celého kroku.

Aktualizováno 18. 10. 2026. **Kroky 1–3 jsou hotové v popsaném rozsahu.** Krok 3a a Portu portfolia jsou v commitu `e8aad85`; podkroky 3b–3d jsou dokončené společně v pracovní větvi, včetně automatických kontrol, migrace webové databáze a ověření webu. Následuje krok 4; commit 3b–3d doplnit po jeho vytvoření Filipem.

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
- [ ] **4. Souhrny portfolia a web.**
- [ ] **5. Směrování notifikací.**
- [ ] **6. Dividendy a prognózy.**
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

## Doklady dokončených částí

| Část | Implementace | Hlavní automatické ověření |
|---|---|---|
| 1 | `1a8f237` | [StockPositionRepositoryOwnershipTest](../tests/Integration/Stock/StockPositionRepositoryOwnershipTest.php): dva vlastníci, cizí ID, nepřihlášený uživatel, explicitní vlastník a změna účtu v jednom procesu. |
| 2 | `0fdb398` | [PositionOwnershipTest](../tests/Integration/Asset/PositionOwnershipTest.php) a [PositionWebAccessTest](../tests/Integration/Asset/PositionWebAccessTest.php): seznamy, ID, editace, uzavření, historie, zachování vlastníka a odmítnutí cizích URL. |
| 3a a Portu portfolia | `e8aad85` | [PersonalAssetOwnershipTest](../tests/Integration/Asset/PersonalAssetOwnershipTest.php): přístupy a vazby podle vlastníka, unikátnost tickeru, mazání watchlistu a přístup běžného účtu. [PersonalAssetOwnershipMigrationTest](../tests/Integration/Migrations/PersonalAssetOwnershipMigrationTest.php): převod historie a ochrany migrace. |

Po implementaci 3a a Portu portfolií prošlo `composer cs-fix && composer build-all`: 793 unit testů a 176 integračních testů, včetně statické analýzy a validace schématu. Kontroly změněných PHP souborů v PhpStormu prošly. V izolovaném Playwrightu byly pod účtem `filip` ověřeny přehledy Portu a watchlistu, jejich editace a formuláře Portu pozic. Oddělení dvou uživatelů pokrývají integrační testy.

Podkroky 3b–3d pokrývají [PersonalDataOwnershipTest](../tests/Integration/PersonalData/PersonalDataOwnershipTest.php), [PersonalIncomeImportOwnershipTest](../tests/Integration/PersonalData/PersonalIncomeImportOwnershipTest.php), [PersonalDataOwnershipMigrationTest](../tests/Integration/Migrations/PersonalDataOwnershipMigrationTest.php) a [WorkMonthlyIncomeOwnerProviderTest](../tests/Unit/Cash/Income/WorkMonthlyIncomeOwnerProviderTest.php). Ověřují cizí ID a vazby, prázdný účet, běžný účet, změnu vlastníka v jednom procesu, import do shodných období a identifikátorů, opakované zpracování, konfigurovaného vlastníka a ochrany migrace.

Po implementaci 3b–3d prošlo `composer cs-fix && composer build-all`: 796 unit testů (3592 assertions), 210 integračních testů (973 assertions), statická analýza, validace mapování a schématu, Latte a kontrola závislostí. PhpStorm nenašel chyby v 59 změněných či nových PHP souborech.

Po potvrzené migraci byly v izolovaném Playwrightu pod účtem `filip` ověřeny seznamy bankovních účtů, výdajů, štítků a bankovních příjmů, pracovní příjmy a přehled výdajů, formuláře bankovního účtu, úpravy a duplikace výdaje, importu včetně výběru vlastních účtů a úpravy štítku. Dále seznam domácností, úprava domácnosti, seznam zařízení s grafy, vytvoření a úprava zařízení a přehled jeho záznamů. Stránky vracely HTTP 200 se správným nadpisem; nebyly zaznamenány chyby JavaScriptu. Webový dump neobsahuje žádná měření zařízení, proto jejich naplněnou historii a oddělení dvou uživatelů ověřují integrační testy. Formuláře ani import nebyly na webu odesílány.

Při průchodu webem nalezen starší nesouvisející problém: `BankAccountGridFactory` vede akcí „Editovat“ na `PortfolioGoal:edit` místo `BankAccount:edit`. Odkaz byl chybný už před touto změnou a zůstává beze změny; samotný formulář bankovního účtu byl ověřen přes správnou adresu. Opravu navigace řešit samostatně.

Samotné dokončení těchto částí ještě neznamená oddělení všech dat aplikace. Například globální metody `StockPositionRepository::findAll()` a `PortuAssetRepository::findAll()` ještě používají další výpočty; jejich spotřebitele je potřeba projít v dalších krocích. Filtrování watchlistu v novém AI snímku rovněž neznamená dokončené oddělení AI analýz z kroku 7.

## Migrace a předávání jednotlivých změn

- CLI a integrační testy používají databázi `my-portfolio-app-tracker-tests`. PhpStorm a web používají `my-portfolio-tracker`. Integrační testy obnovují tabulky; nespouštět nad stejnou databází současně testy a migrace.
- Připravit a otestovat Doctrine migraci. Migraci webové databáze spouští Filip a potvrdí výsledek; až poté následuje závislé ověření webu. Stejný migrační soubor slouží i pro nasazení do clusteru.
- [Version20261018102336](../migrations/Version20261018102336.php) byla 18. 10. 2026 uživatelem spuštěna na webové databázi a následně ověřena: 1 Portu portfolio a 20 položek watchlistu patří účtu `filip`, nesoulad vlastníka Portu pozice a portfolia je 0. Spuštění v clusteru zde zatím potvrzené není.
- [Version20261018104607](../migrations/Version20261018104607.php) pro 3b–3d byla 18. 10. 2026 spuštěna Filipem na webové databázi a následně ověřena v `doctrine_migrations`. Všechna převáděná data patří `filip`: 2 bankovní účty, 2307 výdajů, 137 příjmů, 71 štítků, 34 měsíčních příjmů, 1 domácnost a 2 zařízení. Počty odpovídají stavu před migrací, nesoulad vlastníků výdaje a hlavního štítku i rodičovského a potomkovského štítku je 0. Test převodu historie, ochran a návratu migrace prošel. Spuštění v clusteru zde zatím potvrzené není.
- Před každou další změnou přečíst tento plán. Po dokončení aktualizovat příslušný podkrok, důkazy ověření a stav migrace; commit doplnit, jakmile je známý.
- Celý krok odškrtnout až po splnění všech jeho podkroků a hlavního ověření. U kroku 3 jsou podmínky splněné: všechny podkroky, potvrzená migrace, automatické kontroly i ověření webu.
- Na konci každé předávané změny uvést její přesné číslo a rozsah, zbývající práci a commit příkaz pro Filipa. Commity provádí Filip.
