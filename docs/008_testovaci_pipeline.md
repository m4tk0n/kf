# Automatické integrační testy K&F

## Co testy spouštějí

Workflow `.github/workflows/tests.yml` se spouští při každém pushi do `main`, při pull requestu do `main` a ručně přes GitHub Actions.

Testy běží v oddělené GitHub Actions službě MySQL. Nikdy se nepřipojují k živé databázi ani nepoužívají produkční `.env`. Testovací skript navíc odmítne pokračovat, pokud `APP_ENV` není `test` nebo databáze nemá přesný název `kf_test`.

Databázové tabulky se vytvářejí z aktuálních SQL souborů v `install/sql/tables`, nikoliv z napodobené testovací struktury.

## Ověřovaný průchod

1. Vytvoří se izolovaní uživatelé s rolemi 1, 2, 3 a 4 a testovací město, klub a kapela.
2. Pro roli 1 se vytvoří přihlášená session.
3. Připraví se kompletní data akce, draft a skutečný WebP obrázek.
4. Skutečné GET routy `route_event_new()` a `route_event_preview()` vykreslí formulář s uloženými hodnotami, chráněným náhledem obrázku a detail akce před odesláním.\n5. Testovací POST požadavky ověřují také CSRF token a kontrolu Origin; skutečná routa `route_event_submit()` odešle návrh do `pending`.
5. Ověří se DB záznam, JSON návrhu, obrázek, vyčištění draftu a dočasného obrázku.
6. Skutečná routa `route_review_post()` schválí návrh pod rolí 4.
8. Ověří se publikovaný záznam v `events`, vazba v `event_bands`, JSON detailu, obě WebP varianty, odstranění pending souborů a zvýšení `approved_count`.
9. Samostatně se skutečnou routou otestuje přímé zveřejnění role 2 podle aktuálního oprávnění `publish_direct`.
10. Otestuje se zamítnutí návrhu a smazání vlastního čekajícího návrhu přes `/my`.

PHP syntax check prochází všechny PHP soubory v repozitáři.

## Role – důležitý rozdíl

Aktuální `config.php` definuje role:

| Hodnota | Role | Přidání akce |
|---:|---|---|
| 1 | user | pending |
| 2 | trusted | přímé zveřejnění |
| 3 | moderator | přímé zveřejnění; schvalování |
| 4 | admin | přímé zveřejnění; schvalování |

Testy spouštějí skutečné routy a rozhodnutí se řídí funkcí `can('publish_direct')`.

Uživatel dříve uvedl, že účet označený jako role 2 odeslal akci do pending. To neodpovídá aktuálnímu kódu ani konfiguraci. Integrační test proto potvrzuje chování kódu na GitHubu: role 1 jde do pending a role 2 zveřejňuje přímo. Pokud živý server vykazuje jiné chování, je nutné porovnat nasazené soubory, DB hodnotu role a session. Testovací pipeline sama o sobě neověřuje produkční server.

## Spuštění lokálně

Vyžaduje PHP 8.3+ s rozšířeními `pdo_mysql`, `gd`, `mbstring`, `curl` a testovací MySQL databázi pojmenovanou přesně `kf_test`.

Nastav testovací proměnné prostředí (ne produkční přihlašovací údaje), připrav `.env` s `APP_ENV=test`, `DB_NAME=kf_test` a spusť:

```bash
php tests/integration/events_workflow.php
```

Skript při spuštění maže a znovu vytváří tabulky v databázi `kf_test`. Nikdy jej nespouštěj proti produkční databázi.
\n\nAdresář `tests/` je při FTP nasazení výslovně vyloučen. Deploy workflow zároveň odstraní případnou dřívější kopii `tests/` ze serveru, aby testovací spouštěcí skripty nebyly veřejnou součástí webu.\n