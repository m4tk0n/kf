# Vstupné u akcí

## Formulář

Formulář `/event/new` obsahuje nepovinnou položku Vstupné se třemi volbami:

- Neuvádět – žádné vstupné se neukládá.
- Text – libovolný text do 100 znaků, například „Zdarma“, „Dobrovolné“ nebo „Předprodej 250 Kč / na místě 300 Kč“.
- Částka – nezáporná číselná hodnota s nejvýše dvěma desetinnými místy a povinně vybranou měnou.

Měna se při výběru státu/města automaticky nastaví podle `countries.currency_code`. Uživatel ji může změnit; nabídka obsahuje měny použité v katalogu zemí.

## Uložení

V rozpracovaném formuláři a v JSON návrhu pending se ukládají `admission_type`, `admission_value` a `admission_currency`.

Při zveřejnění se vstupné uloží do JSON `data/events` jako objekt `admission`:

```json
{
  "admission": {
    "type": "amount",
    "value": 250,
    "currency": "CZK"
  }
}
```

Textová varianta má tvar `{"type":"text","value":"Dobrovolné"}`. Pokud uživatel zvolí „Neuvádět“, hodnota `admission` je `null`.

## Zobrazení a kompatibilita

Vstupné se zobrazuje v náhledu před odesláním, při moderaci a v detailu publikované akce. Starší JSON záznamy s historickým textovým polem `price` zůstávají čitelné.