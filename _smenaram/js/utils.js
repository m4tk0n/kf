// js/utils.js
// Globální stav
let currentDate = new Date();
currentDate.setDate(1);

let displayYear = currentDate.getFullYear();
let isEditMode = false;
let isYearlyView = false;
let isFilterMode = false;
let shiftsData = {};

// Předvolba pro zápis
let activePreset = { osoba1: undefined, osoba2: undefined, osoba3: undefined, clear: false };

const monthNames = [
    "leden", "únor", "březen", "duben", "květen", "červen",
    "červenec", "srpen", "září", "říjen", "listopad", "prosinec"
];

// Formátování data na YYYY-MM-DD
function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}
