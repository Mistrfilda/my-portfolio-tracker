# Stažení dat jedné akcie

Po vytvoření akcie v administraci se automaticky zařadí `stock_asset_download` do stávající fronty `JobRequest`. V přehledu akcií lze totéž spustit přes **Akce → Stáhnout aktuální data**. Při úpravě existující akcie se automatické stažení nespouští.

Job obsahuje pouze UUID. Worker načte aktuální nastavení akcie a stáhne povolená data:

- cenu přes nastavený zdroj WEB_SCRAP, Twelve Data nebo PSE;
- dividendy při zapnutém stahování ceny a zdroji dividend WEB;
- základní valuace a analytické cíle při zapnutém stahování valuací.

Jednotlivé části běží samostatně. Pokud například cena selže, ostatní části se ještě zkusí stáhnout. Job pak skončí chybou a použije dosavadní chování fronty pro opakování. Neúspěch se nezamění za úspěšné dokončení. Při nedostupné frontě zůstane nová akcie uložená a formulář zobrazí upozornění s možností později spustit stažení z přehledu.

Stejnou operaci lze provést synchronně bez fronty:

```sh
bin/console stock:asset:download-data <UUID>
```

## Procesy a souběh

`symfony/process` spouští existující Node skripty s argumenty `--data-dir <adresář> --strict`. Každý job má vlastní `puppeter/files/jobs/<náhodné-UUID>/` s adresáři requests, results a parsed; po dokončení se odstraní. Hromadné cron skripty používají dál původní cesty. Timeout je 180 sekund na scraper a při chybě se do logu dostane konec jeho výstupu.

Scrapery předávají čas zahájení načítání stránky `downloadedAt`. Import zamkne řádek akcie a přijme jen novější data stejného typu. Opakovaný nebo opožděný import tak nepřepíše novější cenu a opakované analytické cíle se neduplikují. Valuace a analytické cíle mají oddělený úklid záznamů. Starý formát hromadných souborů bez `downloadedAt` zůstává přijatelný s časem importu; před přechodem je proto nutné zpracovat nebo odstranit dřívější výsledky a vygenerovat nové.

Jednotlivé stažení nemění uložené globální časy ani počty posledního hromadného běhu. Vlastní čas kontroly dividend se zapíše i pro ověřenou prázdnou historii. Chybějící stránka, nerozpoznaný obsah nebo neplatná cena čas úspěchu neposune.

## Monitoring a přechod

Migrace `Version20260924085437` přidává tři nullable časy a mění `priceDownloadedAt` na nullable. Ceny a valuace doplní z existujících záznamů; kontrolu dividend nelze zpětně doložit, proto zůstane prázdná.

Nový monitoring vyhodnocuje každou akcii podle posledního úspěšného stažení, včetně samostatných jobů. Počítá vždy stejnou množinu povolených akcií jako downloader. Příklad: po přidání 21. akcie ukáže 20 aktuálních z 21 a po stažení 21 z 21. Opakovaný job počet dále nezvýší.

Očekávané cykly odpovídají současnému rozvrhu aplikace (Europe/Prague):

| Data | Cyklus |
| --- | --- |
| Ceny | Po–Pá 12:25, 16:25, 22:25; pokusy v :35/:45 patří do stejného cyklu |
| Dividendy | Po, St, Pá 06:00 |
| Valuace a analytické cíle | So 06:00 |

Na dokončení cyklu je 45 minut. U cen se zohlední také stejný hodinový limit jako při výběru akcií do downloaderu, takže nedávné ruční stažení těsně před cronem není falešně neaktuální. Nově vytvořená akcie má na první stažení 15 minut; počty ji zobrazí ihned, ale po tuto dobu nezpůsobí chybu push monitoru. Očekávaný cyklus se posouvá podle rozvrhu, i když cron vůbec neběžel. Při změně cron rozvrhu je třeba upravit také `StockAssetDataFreshness`.

Nasazení:

1. Nasadit nový PHP image s Composer závislostmi a scrapery a aplikovat migraci. Použít stejný image také pro worker a cron.
2. Nechat úspěšně proběhnout nové hromadné importy cen, dividend, valuací i analytických cílů. Lze použít obvyklé `composer stock-assets-downloaders`, `composer download-dividends` a `composer stock-valuation`, nebo počkat na příslušné cron běhy. Jde o skutečná stažení a standardní dividendový import může vytvářet notifikace.
3. Spustit `bin/console stock:monitoring:enable`. Příkaz ověří čerstvost všech povolených dat a odmítne aktivaci, pokud něco chybí. Nepodporované tituly nebo chyby zdroje je potřeba vyřešit před aktivací.

Do aktivace zůstává původní monitoring, aby samotné přidání prázdných sloupců nevyvolalo výpadek. Aktivace se ukládá jako `STOCK_DATA_MONITORING_ENABLED_AT` v systémových hodnotách. Poté se zobrazované počty počítají z akcií a push monitory respektují uvedené tolerance.

## Provoz

Stávající `docker/php/Dockerfile` obsahuje PHP 8.5, Node, Chromium a Puppeteer. Nová fronta ani jiná služba nejsou potřeba. Worker potřebuje přístup k síti a právo vytvářet soubory v nakonfigurovaném `puppeter.folder`. Při změně produkčního image je třeba zachovat Node v PATH, Chromium a nainstalované `puppeter/node_modules`. Samotný PHP Composer install Node závislosti neinstaluje.

Automatické testy používají mockované HTTP klienty a frontu, lokální Node procesy bez externích stránek a oddělenou integrační databázi. Produkční cluster ani skutečné scrape výsledky tím nejsou ověřeny.
