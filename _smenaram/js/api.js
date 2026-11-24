// js/api.js
// Načtení směn pro definované období
async function loadShifts(start, end) {
    try {
        const response = await fetch(`api.php?start=${start}&end=${end}`);
        const text = await response.text();

        try {
            shiftsData = JSON.parse(text);
        } catch (jsonErr) {
            console.error("Server nevrátil platný JSON. Odpověď serveru:\n", text);
            return;
        }
    } catch (e) {
        console.error("Chyba při síťovém požadavku:", e);
    }
}

// Uložení směn všech osob v jednom dotazu
async function saveDayShifts(datum, osoba1, osoba2, osoba3) {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ datum, osoba1, osoba2, osoba3 })
        });
    } catch (e) {
        console.error("Chyba při ukládání směny:", e);
    }
}
