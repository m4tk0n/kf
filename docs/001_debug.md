# docs/001_debug.md

## Nezávislá diagnostika K&F

Diagnostika je oddělená od běžných modulů aplikace. Bez výslovného zapnutí se její JavaScript ani CSS nenačítají.

### Zapnutí pro administrátora

1. Přihlásit se jako admin.
2. Otevřít stránku s parametrem `?debug=1`, například `https://7x.wz.cz/_kf/cs/?debug=1`.
3. Ve spodní části stránky se zobrazí panel K&F DEBUG.

### Zapnutí při testování jako host

Do serverového souboru `.env` přidat vlastní dlouhý náhodný klíč:

```ini
DEBUG_KEY=vlastni-dlouhy-nahodny-tajny-klic
```

Poté otevřít stránku s parametry `?debug=1&debug_key=VÁŠ_KLÍČ`. Server nastaví krátkodobou HttpOnly cookie, parametr s klíčem se z adresy odstraní a diagnostika bude dostupná i na dalších stránkách v daném prohlížeči. Přístup je platný 30 minut a přetrvá při navigaci mezi stránkami. Debug vypnete přidáním `?debug=0` k URL. Po testování klíč z `.env` odstranit nebo změnit.

Klíč nikdy nevkládat do GitHubu ani do veřejných souborů. Bez admin účtu a bez `DEBUG_KEY` se debug hostům nezpřístupní.

### Co panel zaznamenává

- volání `alert()` včetně zásobníku volání (call stack),
- JavaScript chyby včetně souboru, řádku a sloupce,
- odmítnuté Promise,
- chyby načítání CSS/JS a dalších zdrojů,
- Fetch a XMLHttpRequest požadavky, HTTP status a dobu trvání,
- kliknutí na odkazy a tlačítka,
- odeslání formulářů,
- načtené JS/CSS soubory a jejich dobu načítání,
- rozměry viewportu a stav připojení.

Záznam lze zkopírovat tlačítkem **Kopírovat**. Panel uchovává nejvýše 300 posledních událostí v paměti aktuální stránky. Citlivé hodnoty běžných URL parametrů se před zobrazením maskují; těla požadavků a formulářová hesla se nezaznamenávají.

### Serverové chyby

PHP výjimky a fatální chyby se zapisují strukturovaně do `data/error.log`. Soubor je chráněn adresářovým `.htaccess`. Log obsahuje čas, ID požadavku, metodu, cestu, typ chyby, soubor, řádek a zásobník volání. Posledních 80 serverových záznamů lze načíst přímo do panelu přes chráněný diagnostický endpoint. Query string se do serverového logu nezapisuje.

### Bezpečnost

Diagnostický panel může obsahovat interní technické informace. Zapínat ho pouze při testování, nesdílet veřejně jeho výpis a po dokončení testu ukončit debug režim. Běžní návštěvníci žádný diagnostický skript ani panel nenačítají.
