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
