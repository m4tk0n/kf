<?php
function assetVersion($relativePath) {
    $filePath = __DIR__ . '/' . ltrim($relativePath, '/');
    return is_file($filePath) ? (string) filemtime($filePath) : '0';
}
?>
<!-- index.php -->
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SmenarAM</title>
    <link rel="stylesheet" href="style.css?v=<?= assetVersion('style.css') ?>">
</head>
<body>

<!-- Horní lišta -->
<div class="top-bar">
    <span class="app-title">SměnářAM</span>
    <div class="icons-group">
        <span class="icon-btn" title="Ocenění" aria-label="Závody">
            <svg viewBox="0 0 48 48" width="30" height="30" aria-hidden="true" focusable="false">
                <path d="M18 12c-5 3-7 9-5 14l-5 9 9-2 5-7z" fill="#f2cf72"/>
                <path d="M29 13c6 4 8 10 5 16l7 7-10-1-7-8z" fill="#f2cf72"/>
                <path d="M19 12c-2-4 1-8 6-8 5 0 8 4 6 9l-5 8-8-2z" fill="#f4d58a"/>
                <path d="M21 18c-3 1-5 4-5 8l-5 7 7 2 7-8 3-9z" fill="#e7b58e"/>
                <path d="M27 19c4 1 6 4 6 8l5 7-7 2-7-8-2-8z" fill="#e7b58e"/>
                <path d="M20 25l8 1 4 9-7 3-7-5z" fill="#df5c55"/>
                <path d="M19 31l-7 8-7 1 1 3 10-1 9-8z" fill="#34495e"/>
                <path d="M29 31l6 7 7 2-1 3-10-2-8-7z" fill="#34495e"/>
                <path d="M17 11c-1-5 3-9 9-9 5 0 9 4 8 9-2-2-4-3-7-3-3 0-6 2-10 3z" fill="#e9c45f"/>
                <path d="M18 9c2-4 6-5 10-4-3 2-4 4-5 7z" fill="#f5df9b"/>
                <circle cx="28" cy="14" r="1" fill="#59463b"/>
            </svg>
        </span>
        <span id="pencil-btn" class="icon-btn" title="Režim úprav">🔒</span>
        <span id="check-btn" class="icon-btn" title="Zvýraznit volné dny">✅</span>
        <span id="menu-btn" class="icon-btn" title="Roční přehled">☰</span>
    </div>
</div>

<!-- 1. MĚSÍČNÍ POHLED -->
<div id="monthly-view">
    <div class="month-picker">
        <span id="prev-month" class="nav-btn">&lt;</span>
        <span id="month-display"></span>
        <span id="next-month" class="nav-btn">&gt;</span>
    </div>

    <div class="weekdays">
        <div>Po</div><div>Út</div><div>St</div><div>Čt</div><div>Pá</div><div>So</div><div>Ne</div>
    </div>

    <div id="calendar" class="calendar-grid"></div>
</div>

<!-- 2. ROČNÍ POHLED -->
<div id="yearly-view" class="hidden">
    <div class="year-picker">
        <span id="prev-year" class="nav-btn">&lt;</span>
        <span id="year-display">2026</span>
        <span id="next-year" class="nav-btn">&gt;</span>
    </div>
    <div id="yearly-container" class="yearly-container"></div>
</div>

<!-- 3. STRÁNKA ZÁVODŮ -->
<div id="races-view" class="hidden">
    <div class="year-picker">
        <span id="prev-races-year" class="nav-btn">&lt;</span>
        <span id="races-year-display"></span>
        <span id="next-races-year" class="nav-btn">&gt;</span>
    </div>
    <div class="races-page-title">Závody</div>
    <div id="races-container" class="races-container"></div>
</div>

<!-- Vyskakovací dialog pro výběr razítka -->
<div id="preset-modal" class="modal-overlay hidden">
    <div class="modal-content">
        <h3>Vyberte směnu</h3>
        <div class="modal-buttons">
            <button class="preset-btn btn-person1" data-a="N">A - N</button>
            <button class="preset-btn btn-person3" data-c="Z">A - Z</button>
            <button class="preset-btn btn-person2" data-m="R">M - R</button>
            <button class="preset-btn btn-person2" data-m="N">M - N</button>
            <button class="preset-btn btn-delete" data-clear="true">Vymazat den</button>
        </div>
        <button id="close-modal-btn" class="btn-cancel">Zrušit</button>
    </div>
</div>

<!-- Načtení skriptů -->
<script src="js/utils.js?v=<?= assetVersion('js/utils.js') ?>"></script>
<script src="js/api.js?v=<?= assetVersion('js/api.js') ?>"></script>
<script src="js/month-calendar.js?v=<?= assetVersion('js/month-calendar.js') ?>"></script>
<script src="js/year-calendar.js?v=<?= assetVersion('js/year-calendar.js') ?>"></script>
<script src="js/app.js?v=<?= assetVersion('js/app.js') ?>"></script>
</body>
</html>
