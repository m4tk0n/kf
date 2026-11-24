// js/app.js
// Proměnné (currentDate, displayYear, isEditMode, isYearlyView, isFilterMode, shiftsData, activePreset, monthNames)
// a funkce formatDate() jsou přebírány z utils.js.

document.addEventListener('DOMContentLoaded', () => {
    const pencilBtn = document.getElementById('pencil-btn');
    const checkBtn = document.getElementById('check-btn');
    const menuBtn = document.getElementById('menu-btn');
    const medalBtn = document.querySelector('.icons-group .icon-btn[title="Ocenění"]');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');

    const modal = document.getElementById('preset-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const presetButtons = document.querySelectorAll('.preset-btn');

    // 1. Zámek / Tužka (Režim úprav)
    if (pencilBtn) {
        pencilBtn.textContent = '🔒';
        pencilBtn.addEventListener('click', function() {
            if (isYearlyView || this.classList.contains('disabled')) return;

            if (!isEditMode) {
                modal?.classList.remove('hidden');
            } else {
                turnOffEditMode();
            }
        });
    }

    // Výběr předvolby z modálního okna
    presetButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            activePreset = {
                osoba1: this.dataset.a,
                osoba2: this.dataset.m,
                osoba3: this.dataset.c,
                clear: this.dataset.clear === 'true'
            };

            isEditMode = true;
            if (pencilBtn) {
                pencilBtn.classList.add('active');
                pencilBtn.textContent = '✏️';
            }
            document.getElementById('calendar')?.classList.add('edit-mode');
            modal?.classList.add('hidden');
        });
    });

    closeModalBtn?.addEventListener('click', () => modal?.classList.add('hidden'));

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    // 2. Tlačítko zvýraznění (Fajfka)
    if (checkBtn) {
        checkBtn.addEventListener('click', function() {
            if (this.classList.contains('disabled') || isYearlyView) return;

            isFilterMode = !isFilterMode;
            this.classList.toggle('active', isFilterMode);
            document.body.classList.toggle('filter-mode', isFilterMode);
            
            renderCalendar();
        });
    }

    // 3. Ikona medaile (Závody)
    medalBtn?.addEventListener('click', toggleRacesView);

    // 4. Hamburger menu (Roční přehled)
    menuBtn?.addEventListener('click', toggleYearlyView);

    document.getElementById('prev-races-year')?.addEventListener('click', () => changeRacesYear(-1));
    document.getElementById('next-races-year')?.addEventListener('click', () => changeRacesYear(1));

    // 4. Navigace
    prevMonthBtn?.addEventListener('click', () => changeMonth(-1));
    nextMonthBtn?.addEventListener('click', () => changeMonth(1));

    document.getElementById('prev-year')?.addEventListener('click', () => changeYear(-1));
    document.getElementById('next-year')?.addEventListener('click', () => changeYear(1));

    // První načtení měsíce
    renderCalendar();
});

// Vypnutí režimu úprav (zamknutí)
function turnOffEditMode() {
    isEditMode = false;
    activePreset = { osoba1: undefined, osoba2: undefined, osoba3: undefined, clear: false };
    const pencilBtn = document.getElementById('pencil-btn');
    if (pencilBtn) {
        pencilBtn.classList.remove('active');
        pencilBtn.textContent = '🔒';
    }
    document.getElementById('calendar')?.classList.remove('edit-mode');
}

// Přepínání mezi Měsíčním a Ročním pohledem
function toggleYearlyView() {
    isYearlyView = !isYearlyView;

    const monthlyView = document.getElementById('monthly-view');
    const yearlyView = document.getElementById('yearly-view');
    const checkBtn = document.getElementById('check-btn');
    const pencilBtn = document.getElementById('pencil-btn');

    if (isYearlyView) {
        // Vypnutí úprav i filtru při vstupu do ročního přehledu
        turnOffEditMode();
        
        isFilterMode = false;
        document.body.classList.remove('filter-mode');
        if (checkBtn) checkBtn.classList.remove('active');

        // Deaktivace ikon v liště
        if (checkBtn) checkBtn.classList.add('disabled');
        if (pencilBtn) pencilBtn.classList.add('disabled');

        monthlyView?.classList.add('hidden');
        yearlyView?.classList.remove('hidden');

        renderYearlyOverview();
    } else {
        // Návrat do měsíčního kalendáře
        if (checkBtn) checkBtn.classList.remove('disabled');
        if (pencilBtn) pencilBtn.classList.remove('disabled');

        yearlyView?.classList.add('hidden');
        monthlyView?.classList.remove('hidden');

        renderCalendar();
    }
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

function changeYear(delta) {
    displayYear += delta;
    renderYearlyOverview();
}


// Přepínání samostatné stránky Závody
function toggleRacesView() {
    const racesView = document.getElementById('races-view');
    const monthlyView = document.getElementById('monthly-view');
    const yearlyView = document.getElementById('yearly-view');
    const menuBtn = document.getElementById('menu-btn');
    const medalBtn = document.querySelector('.icons-group .icon-btn[title="Ocenění"]');

    const opening = racesView?.classList.contains('hidden');
    if (!opening) {
        racesView?.classList.add('hidden');
        monthlyView?.classList.remove('hidden');
        if (medalBtn) medalBtn.classList.remove('active');
        renderCalendar();
        return;
    }

    isYearlyView = false;
    displayYear = currentDate.getFullYear();
    turnOffEditMode();
    isFilterMode = false;
    document.body.classList.remove('filter-mode');
    document.getElementById('check-btn')?.classList.remove('active', 'disabled');
    document.getElementById('pencil-btn')?.classList.remove('disabled');
    yearlyView?.classList.add('hidden');
    monthlyView?.classList.add('hidden');
    racesView?.classList.remove('hidden');
    menuBtn?.classList.remove('active');
    if (medalBtn) medalBtn.classList.add('active');
    renderRacesPage();
}

function changeRacesYear(delta) {
    displayYear += delta;
    renderRacesPage();
}

function renderRacesPage() {
    const yearDisplay = document.getElementById('races-year-display');
    if (yearDisplay) yearDisplay.textContent = displayYear;
    // Seznam je připravený pro závody daného roku; zatím zůstává prázdný.
}
