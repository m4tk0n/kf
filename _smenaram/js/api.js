// js/api.js
// Načtení směn pro definované období
async function loadShifts(start, end) {
    try {
        const response = await fetch(`api.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`, {
            cache: 'no-store'
        });
        const text = await response.text();

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${text}`);
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            throw new Error(`Server nevrátil platný JSON: ${text}`);
        }

        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            throw new Error('Server vrátil neočekávaný formát dat.');
        }

        shiftsData = data;
    } catch (e) {
        console.error('Chyba při načítání směn:', e);
        throw e;
    }
}

// Uložení směn všech osob v jednom dotazu
async function saveDayShifts(datum, osoba1, osoba2, osoba3) {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            cache: 'no-store',
            body: JSON.stringify({ datum, osoba1, osoba2, osoba3 })
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (jsonErr) {
            throw new Error(`Server nevrátil platný JSON: ${text}`);
        }

        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || `HTTP ${response.status}`);
        }

        return true;
    } catch (e) {
        console.error('Chyba při ukládání směny:', e);
        alert('Směnu se nepodařilo uložit. Zkontroluj připojení a databázi. Podrobnosti jsou v konzoli.');
        return false;
    }
}
