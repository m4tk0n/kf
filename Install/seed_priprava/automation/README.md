# Automatizovaný průzkum seed dat

## Co se spouští

Workflow `.github/workflows/seed-research.yml`:
- automaticky každé pondělí v 03:23 UTC,
- ručně přes GitHub → Actions → K&F seed research crawler → Run workflow,
- zpracovává soubory v `Install/seed_priprava/`,
- používá jeden běh najednou; souběžné běhy se řadí za sebe,
- ukládá nálezy a checkpoint do `research_candidates.json` a změny automaticky commitne do `main`.

Při ručním spuštění lze zvolit rozsah roků. Výchozí rozsah je 2026–2000.

## Co automat skutečně dělá

1. Načte aktuální seed JSON a provede strukturální audit ID a vazeb.
2. Stáhne oficiální archivy The Fialky 2000–2015 a 2016–2026.
3. Projde ročníkové archivy Bandzone pro zadané období.
4. Uloží textové výřezy obsahující datum, odkazy, URL zdroje, rok archivu a kontrolní hash.
5. Zachová dříve nalezené kandidáty a přidá nové; opakované položky deduplikuje podle URL + textového výřezu.
6. Při chybě jedné stránky pokračuje dalšími roky a chybu zapíše do checkpointu.

## Bezpečnost a ověřování

- Výstup je pouze fronta surových nálezů se stavem `unreviewed`.
- Crawler nikdy nevytváří ani neupravuje ověřené akce, kapely, místa, line-upy, evidence claims ani ID seed záznamů.
- Samotný archivní záznam není důkazem skutečného konání, adresy místa ani účasti všech kapel v programu.
- Automatický audit je read-only a vypisuje strukturální chyby/varování do artefaktu běhu.
- Žádné API klíče ani externí placené služby nejsou potřeba.

## Omezení první verze

Tato verze automatizuje sběr archivních podkladů a kontrolu konzistence, nikoli plnohodnotné ověřování každého tvrzení na internetu. K tomu je potřeba samostatný vyhledávací/AI krok s přístupem k webu; ten musí pracovat s důkazy a nesmí automaticky povyšovat nejasné nálezy do `ok`.

## Výstupy

- `research_candidates.json` — surová fronta nalezených výřezů.
- `seed-audit-errors.txt` / `seed-audit-warnings.txt` — artefakty auditu konkrétního běhu.
- GitHub Actions run log — stav stažení každého archivu a případné chyby.
