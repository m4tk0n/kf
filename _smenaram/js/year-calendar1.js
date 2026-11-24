// js/year-calendar1.js
function changeYear(delta) {
    displayYear += delta;
    renderYearlyOverview();
}

function turnOffEditMode() {
    const pencilBtn = document.getElementById('pencil-btn');
    isEditMode = false;
    activePreset = { osoba1: undefined, osoba2: undefined, clear: false };
    if (pencilBtn) {
        pencilBtn.classList.remove('active');
        pencilBtn.textContent = '🔒';
    }
    document.getElementById('calendar')?.classList.remove('edit-mode');
}

function toggleYearlyView() {
    isYearlyView = !isYearlyView;

    const monthlyView = document.getElementById('monthly-view');
    const yearlyView = document.getElementById('yearly-view');
    const pencilBtn = document.getElementById('pencil-btn');
    const menuBtn = document.getElementById('menu-btn');

    if (isYearlyView) {
        turnOffEditMode();

        pencilBtn?.classList.add('disabled');
        menuBtn?.classList.add('active');

        monthlyView?.classList.add('hidden');
        yearlyView?.classList.remove('hidden');

        displayYear = currentDate.getFullYear();
        renderYearlyOverview();
    } else {
        pencilBtn?.classList.remove('disabled');
        menuBtn?.classList.remove('active');

        yearlyView?.classList.add('hidden');
        monthlyView?.classList.remove('hidden');

        renderCalendar();
    }
}

async function renderYearlyOverview() {
    const yearDisplay = document.getElementById('year-display');
    if (yearDisplay) yearDisplay.innerText = displayYear;

    const container = document.getElementById('yearly-container');
    if (!container) return;
    container.innerHTML = '';

    // Dnešní datum ve formátu YYYY-MM-DD
    const todayStr = formatDate(new Date());

    // Načtení všech směn pro vybraný rok
    const startStr = `${displayYear}-01-01`;
    const endStr = `${displayYear}-12-31`;
    await loadShifts(startStr, endStr);

    const shortWeekdays = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

    // Vykreslení 12 měsíců
    for (let m = 0; m < 12; m++) {
        const monthBlock = document.createElement('div');
        monthBlock.className = 'year-month-block';

        const table = document.createElement('table');
        table.className = 'year-month-table';

        let daysInMonth = new Date(displayYear, m + 1, 0).getDate();

        // 1. ŘÁDEK: Název měsíce (vždy přes 31 sloupců)
        let rowMonthTitle = `<tr class="month-title-row"><th colspan="31">${monthNames[m]}</th></tr>`;

        // 2. ŘÁDEK: Dny v týdnu
        let rowDayNames = '<tr class="weekday-row">';
        // 3. ŘÁDEK: Číslo dne v měsíci
        let rowDayNums = '<tr class="day-num-row">';
        // 4. ŘÁDEK: Záznam směn
        let rowShifts = '<tr class="shift-row">';

        // Cyklus vždy přes 31 dnů pro roční kalendář
        for (let d = 1; d <= 31; d++) {
            if (d <= daysInMonth) {
                const dateObj = new Date(displayYear, m, d);
                const dayOfWeek = shortWeekdays[dateObj.getDay()];
                const dateStr = formatDate(dateObj);

                // Kontrola dnešního dne
                const isToday = (dateStr === todayStr);

                const shift1 = shiftsData[dateStr]?.['Osoba1'] || '';
                const shift2 = shiftsData[dateStr]?.['Osoba2'] || '';

                let shiftClass = '';
                let shiftText = '';

                // Roční kalendář se vykresluje vždy ve standardním režimu
                if (shift1 && shift2) {
                    // Obě osoby -> úhlopříčné rozdělení bez textu
                    shiftClass = 'shift-both';
                    shiftText = '';
                } else if (shift1) {
                    // Pouze Osoba 1 -> červená barva + text
                    shiftClass = 'shift-person1';
                    shiftText = shift1;
                } else if (shift2) {
                    // Pouze Osoba 2 -> modrá barva + text
                    shiftClass = 'shift-person2';
                    shiftText = shift2;
                }

                rowDayNames += `<th>${dayOfWeek}</th>`;
                
                // 3. ŘÁDEK: Přidání třídy is-today pro dnešní den
                const todayClass = isToday ? ' class="is-today"' : '';
                rowDayNums += `<td${todayClass}>${d}</td>`;

                const classAttr = shiftClass ? ` class="${shiftClass}"` : '';
                rowShifts += `<td${classAttr}>${shiftText}</td>`;
            } else {
                // Doplnění prázdných buněk pro kratší měsíce (28, 29, 30 dnů)
                rowDayNames += `<th class="empty-cell"></th>`;
                rowDayNums += `<td class="empty-cell"></td>`;
                rowShifts += `<td class="empty-cell"></td>`;
            }
        }
        rowDayNames += '</tr>';
        rowDayNums += '</tr>';
        rowShifts += '</tr>';

        table.innerHTML = `
            <thead>
                ${rowMonthTitle}
                ${rowDayNames}
            </thead>
            <tbody>
                ${rowDayNums}
                ${rowShifts}
            </tbody>
        `;

        monthBlock.appendChild(table);
        container.appendChild(monthBlock);
    }
}