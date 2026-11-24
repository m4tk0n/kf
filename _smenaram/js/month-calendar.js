// js/month-calendar.js
const SMENARAM_TODAY_IMAGE_URL = new URL('../images/todayblue.png', document.currentScript.src).href;
async function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const todayStr = formatDate(new Date());

    const monthDisplay = document.getElementById('month-display');
    if (monthDisplay) monthDisplay.innerText = `${monthNames[month]} ${year}`;

    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;
    calendarEl.innerHTML = '';

    // Výpočet počátečního dne (pondělí)
    let firstDayOfMonth = new Date(year, month, 1);
    let dayOfWeek = firstDayOfMonth.getDay();
    let isoDay = dayOfWeek === 0 ? 7 : dayOfWeek;

    let startDate = new Date(firstDayOfMonth);
    startDate.setDate(startDate.getDate() - (isoDay - 1));

    let endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 41);

    try {
        await loadShifts(formatDate(startDate), formatDate(endDate));
    } catch (error) {
        console.error('Kalendář nelze vykreslit bez aktuálních dat.', error);
        calendarEl.textContent = 'Směny se nepodařilo načíst.';
        return;
    }

    let iteratorDate = new Date(startDate);

    for (let i = 0; i < 42; i++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'day-cell';

        const cellMonth = iteratorDate.getMonth();
        const dateStr = formatDate(iteratorDate);

        if (cellMonth === month) {
            dayCell.classList.add('current-month');
        } else {
            dayCell.classList.add('other-month');
        }

        const shift1 = shiftsData[dateStr]?.['Osoba1'];
        const shift2 = shiftsData[dateStr]?.['Osoba2'];
        const shift3 = shiftsData[dateStr]?.['Osoba3'];

        // Pořadí v dni: 1. Osoba3 (A - Z, zelená), 2. Osoba1, 3. Osoba2
        const badge3 = shift3 ? `<div class="shift-badge shift-person3">${shift3}</div>` : '';
        const badge1 = shift1 ? `<div class="shift-badge shift-person1">${shift1}</div>` : '';
        const badge2 = shift2 ? `<div class="shift-badge shift-person2">${shift2}</div>` : '';

        const shiftsHtml = `
            <div class="shift-slot">${badge3}</div>
            <div class="shift-slot">${badge1}</div>
            <div class="shift-slot">${badge2}</div>
        `;

        if (shift1 || shift2 || shift3) {
            dayCell.classList.add('has-shift');
        }

        const isToday = (dateStr === todayStr);
        if (isToday) {
            dayCell.classList.add('is-today');
        }

        const todayImgHtml = isToday ? `<img src="${SMENARAM_TODAY_IMAGE_URL}" class="today-circle-img" alt="Dnes">` : '';

        dayCell.innerHTML = `
            <div class="day-header">
                <span class="day-number">${iteratorDate.getDate()}</span>
            </div>
            <div class="shifts-container">${shiftsHtml}</div>
            ${todayImgHtml}
        `;

        const currentIterDate = new Date(iteratorDate);

        dayCell.addEventListener('click', async () => {
            if (isEditMode) {
                await applyPresetToDay(formatDate(currentIterDate));
            }
        });

        calendarEl.appendChild(dayCell);
        iteratorDate.setDate(iteratorDate.getDate() + 1);
    }
}

// Logika aplikování předvolby
async function applyPresetToDay(dateStr) {
    const currentOsoba1 = shiftsData[dateStr]?.['Osoba1'] || '';
    const currentOsoba2 = shiftsData[dateStr]?.['Osoba2'] || '';
    const currentOsoba3 = shiftsData[dateStr]?.['Osoba3'] || '';

    let newOsoba1 = currentOsoba1;
    let newOsoba2 = currentOsoba2;
    let newOsoba3 = currentOsoba3;

    if (activePreset.clear) {
        newOsoba1 = '';
        newOsoba2 = '';
        newOsoba3 = '';
    } else {
        if (activePreset.osoba1 !== undefined) {
            newOsoba1 = (currentOsoba1 === activePreset.osoba1) ? '' : activePreset.osoba1;
        }
        if (activePreset.osoba2 !== undefined) {
            newOsoba2 = (currentOsoba2 === activePreset.osoba2) ? '' : activePreset.osoba2;
        }
        if (activePreset.osoba3 !== undefined) {
            newOsoba3 = (currentOsoba3 === activePreset.osoba3) ? '' : activePreset.osoba3;
        }
    }

    const saved = await saveDayShifts(dateStr, newOsoba1, newOsoba2, newOsoba3);
    if (saved) {
        await renderCalendar();
    }
}
