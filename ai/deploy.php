<?php
declare(strict_types=1);

/**
 * Nasazení balíčku souborů z ZIPu: vytvoření, přepsání i mazání souborů, vždy se zálohou
 * a s možností vrátit změny. Vstup chrání AI_WRITE_KEY z .env; klíč se píše jen do formuláře
 * v prohlížeči (nikdy do chatu). Soubor nenačítá aplikaci, takže funguje i po rozbité aktualizaci.
 *
 * ZIP: soubory na stejných cestách jako v projektu. Volitelný soubor _delete.txt v kořeni
 * obsahuje cesty ke smazání, jednu na řádek (řádky začínající # se ignorují).
 */

const DP_MAX_ZIP      = 8388608;    // maximální velikost nahraného ZIPu (8 MB)
const DP_MAX_FILE     = 2097152;    // maximální velikost jednoho souboru (2 MB)
const DP_MAX_TOTAL    = 20971520;   // maximální součet rozbalených souborů (20 MB)
const DP_MAX_ENTRIES  = 300;        // maximální počet položek v ZIPu
const DP_MAX_FAILS    = 5;          // počet chybných klíčů před zamčením
const DP_LOCK_SECONDS = 900;        // jak dlouho trvá zamčení po chybných klíčích
const DP_PENDING_TTL  = 3600;       // jak dlouho čeká nahraný ZIP na potvrzení
const DP_EXT          = ['php', 'css', 'js', 'ini', 'sql', 'json', 'txt', 'md', 'html', 'htaccess', 'webp', 'png', 'jpg', 'jpeg', 'gif'];
const DP_DENY_TOP     = ['ai', 'data', 'cache'];                // chráněné složky (první část cesty)
const DP_DENY_FILES   = ['install/installed.lock'];             // chráněné jednotlivé soubory

$root = realpath(dirname(__DIR__));
if ($root === false) {
    http_response_code(500);
    exit('Project directory unavailable.');
}
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

// ---------------------------------------------------------------- pomocné funkce
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dp_key(string $root): string
{
    $envFile = $root . '/.env';
    if (!is_file($envFile) || is_link($envFile)) {
        return '';
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*AI_WRITE_KEY\s*=\s*(.*?)\s*$/', $line, $m)) {
            $v = trim($m[1]);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            return $v;
        }
    }
    return '';
}

function dp_dir(string $root, string $sub = ''): string
{
    $dir = $root . '/data/ai-deploy' . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

/** Vrací chybovou hlášku, nebo null, když je klíč správný. */
function dp_auth(string $root): ?string
{
    $expected = dp_key($root);
    if (strlen($expected) < 32) {
        return 'V .env chybí AI_WRITE_KEY, nebo je kratší než 32 znaků.';
    }
    $stateFile = dp_dir($root) . '/auth.json';
    $state = is_file($stateFile) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
    $count = (int)($state['count'] ?? 0);
    $first = (int)($state['first'] ?? 0);
    if ($count >= DP_MAX_FAILS && time() - $first < DP_LOCK_SECONDS) {
        return 'Příliš mnoho neplatných pokusů. Zkus to později.';
    }
    if ($count >= DP_MAX_FAILS || time() - $first >= DP_LOCK_SECONDS) {
        $count = 0;
        $first = 0;
    }
    if (!hash_equals($expected, (string)($_POST['key'] ?? ''))) {
        file_put_contents($stateFile, json_encode(['count' => $count + 1, 'first' => $first ?: time()]), LOCK_EX);
        return 'Neplatný klíč.';
    }
    @unlink($stateFile);
    return null;
}

/** Vrací [normalizovaná cesta, null] nebo [null, důvod odmítnutí]. */
function dp_path(string $raw): array
{
    $p = str_replace('\\', '/', trim($raw));
    if ($p === '' || str_contains($p, "\0")) {
        return [null, 'prázdná cesta'];
    }
    if ($p[0] === '/' || preg_match('~^[A-Za-z]:~', $p)) {
        return [null, 'absolutní cesta'];
    }
    $parts = explode('/', $p);
    foreach ($parts as $s) {
        if ($s === '' || $s === '.' || $s === '..') {
            return [null, 'neplatná cesta'];
        }
        if ($s[0] === '.' && $s !== '.htaccess') {
            return [null, 'skryté soubory jsou chráněné'];
        }
        if (preg_match('~[\x00-\x1f\x7f:*?"<>|]~', $s)) {
            return [null, 'nepovolený znak v cestě'];
        }
    }
    if (in_array($parts[0], DP_DENY_TOP, true)) {
        return [null, 'chráněná složka ' . $parts[0] . '/'];
    }
    $rel = implode('/', $parts);
    if (in_array($rel, DP_DENY_FILES, true)) {
        return [null, 'chráněný soubor'];
    }
    if (!in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), DP_EXT, true)) {
        return [null, 'nepovolená přípona'];
    }
    return [$rel, null];
}

/** Absolutní cesta v projektu; false, když by vedla přes symbolický odkaz nebo přes složku. */
function dp_abs(string $root, string $rel): string|false
{
    $parts = explode('/', $rel);
    $last = array_pop($parts);
    $cur = $root;
    foreach ($parts as $s) {
        $cur .= '/' . $s;
        if (is_link($cur) || (file_exists($cur) && !is_dir($cur))) {
            return false;
        }
    }
    $file = $cur . '/' . $last;
    return (is_link($file) || is_dir($file)) ? false : $file;
}

function dp_cleanup(string $root): void
{
    foreach (glob(dp_dir($root) . '/pending-*.zip') ?: [] as $f) {
        if (time() - (int)@filemtime($f) > DP_PENDING_TTL) {
            @unlink($f);
        }
    }
}

/** Sestaví plán změn z ZIPu. Nic se nezapisuje. */
function dp_plan(string $root, string $zipFile): array
{
    $plan = ['writes' => [], 'deletes' => [], 'errors' => []];
    if (!class_exists('ZipArchive')) {
        $plan['errors'][] = ['', 'Na serveru chybí PHP rozšíření zip.'];
        return $plan;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::RDONLY) !== true) {
        $plan['errors'][] = ['', 'ZIP se nepodařilo otevřít.'];
        return $plan;
    }
    if ($zip->numFiles > DP_MAX_ENTRIES) {
        $plan['errors'][] = ['', 'ZIP má víc než ' . DP_MAX_ENTRIES . ' položek.'];
        $zip->close();
        return $plan;
    }

    $total = 0;
    $seen = [];
    $deleteList = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (str_ends_with($name, '/')) {
            continue;
        }
        if ($name === '_delete.txt') {
            $deleteList = (string)$zip->getFromIndex($i, DP_MAX_FILE);
            continue;
        }
        [$rel, $err] = dp_path($name);
        if ($rel === null) {
            $plan['errors'][] = [$name, $err];
            continue;
        }
        if (isset($seen[$rel])) {
            $plan['errors'][] = [$name, 'cesta je v ZIPu dvakrát'];
            continue;
        }
        $seen[$rel] = true;
        $abs = dp_abs($root, $rel);
        if ($abs === false) {
            $plan['errors'][] = [$name, 'cíl je složka nebo symbolický odkaz'];
            continue;
        }
        $data = $zip->getFromIndex($i, DP_MAX_FILE + 1);
        if ($data === false || strlen($data) > DP_MAX_FILE) {
            $plan['errors'][] = [$name, 'soubor je prázdný, poškozený nebo větší než ' . (DP_MAX_FILE / 1048576) . ' MB'];
            continue;
        }
        $total += strlen($data);
        if ($total > DP_MAX_TOTAL) {
            $plan['errors'][] = [$name, 'součet velikostí překročil ' . (DP_MAX_TOTAL / 1048576) . ' MB'];
            break;
        }
        $status = !is_file($abs) ? 'new' : (md5_file($abs) === md5($data) ? 'same' : 'changed');
        $plan['writes'][] = ['path' => $rel, 'data' => $data, 'status' => $status];
    }
    $zip->close();

    foreach (preg_split('/\R/', $deleteList) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$rel, $err] = dp_path($line);
        if ($rel === null) {
            $plan['errors'][] = ['_delete.txt: ' . $line, $err];
            continue;
        }
        if (isset($seen[$rel])) {
            $plan['errors'][] = [$line, 'soubor je současně v ZIPu i v seznamu ke smazání'];
            continue;
        }
        $abs = dp_abs($root, $rel);
        $plan['deletes'][] = ['path' => $rel, 'status' => ($abs !== false && is_file($abs)) ? 'delete' : 'missing'];
    }
    return $plan;
}

/**
 * Provede operace [['op' => 'write'|'delete', 'path' => ..., 'data' => ...]].
 * Nejdřív zazálohuje vše, co se změní, zapíše manifest a teprve potom mění soubory.
 */
function dp_apply(string $root, array $ops, string $label): array
{
    $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $dir = $root . '/data/ai-backups/' . $id;
    $manifest = ['time' => gmdate('c'), 'label' => $label, 'written' => [], 'deleted' => []];
    $result = ['id' => $id, 'written' => 0, 'deleted' => 0, 'errors' => []];

    foreach ($ops as $op) {
        $abs = dp_abs($root, $op['path']);
        if ($abs === false) {
            return $result + ['fatal' => 'Neplatný cíl: ' . $op['path']];
        }
        $existed = is_file($abs);
        if ($existed) {
            $copy = $dir . '/files/' . $op['path'];
            if ((!is_dir(dirname($copy)) && !@mkdir(dirname($copy), 0750, true)) || !@copy($abs, $copy)) {
                return $result + ['fatal' => 'Nepodařilo se vytvořit zálohu souboru ' . $op['path'] . '. Nic se nezměnilo.'];
            }
            @chmod($copy, 0600);
        }
        if ($op['op'] === 'write') {
            $manifest['written'][] = ['path' => $op['path'], 'existed' => $existed];
        } elseif ($existed) {
            $manifest['deleted'][] = $op['path'];
        }
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (@file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        return $result + ['fatal' => 'Nepodařilo se zapsat manifest zálohy. Nic se nezměnilo.'];
    }

    foreach ($ops as $op) {
        $abs = dp_abs($root, $op['path']);
        if ($abs === false) {
            $result['errors'][] = $op['path'] . ': neplatný cíl';
            continue;
        }
        if ($op['op'] === 'delete') {
            if (is_file($abs)) {
                if (@unlink($abs)) {
                    $result['deleted']++;
                } else {
                    $result['errors'][] = $op['path'] . ': nelze smazat';
                }
            }
            continue;
        }
        $parent = dirname($abs);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            $result['errors'][] = $op['path'] . ': nelze vytvořit složku';
            continue;
        }
        $tmp = $parent . '/.kfdeploy-' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, (string)$op['data'], LOCK_EX) === false || !@rename($tmp, $abs)) {
            @unlink($tmp);
            $result['errors'][] = $op['path'] . ': nelze zapsat';
            continue;
        }
        @chmod($abs, 0644);
        $result['written']++;
    }
    return $result;
}

function dp_backups(string $root): array
{
    $list = [];
    foreach (glob($root . '/data/ai-backups/*/manifest.json') ?: [] as $f) {
        $m = json_decode((string)file_get_contents($f), true);
        if (is_array($m) && preg_match('~/([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/manifest\.json$~', $f, $mm)) {
            $list[] = ['id' => $mm[1], 'time' => (string)($m['time'] ?? ''), 'label' => (string)($m['label'] ?? ''),
                       'written' => count($m['written'] ?? []), 'deleted' => count($m['deleted'] ?? [])];
        }
    }
    usort($list, static fn(array $a, array $b): int => strcmp($b['id'], $a['id']));
    return array_slice($list, 0, 30);
}

/** Vrátí stav před danou změnou jako novou, také zálohovanou operaci. */
function dp_restore(string $root, string $id): array
{
    if (!preg_match('~^[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$~', $id)) {
        return ['fatal' => 'Neplatné ID zálohy.'];
    }
    $dir = $root . '/data/ai-backups/' . $id;
    $m = is_file($dir . '/manifest.json') ? json_decode((string)file_get_contents($dir . '/manifest.json'), true) : null;
    if (!is_array($m)) {
        return ['fatal' => 'Záloha nenalezena.'];
    }
    $ops = [];
    foreach ((array)($m['written'] ?? []) as $w) {
        [$rel] = dp_path((string)($w['path'] ?? ''));
        if ($rel === null) {
            return ['fatal' => 'Manifest zálohy obsahuje neplatnou cestu.'];
        }
        if (!empty($w['existed'])) {
            $data = @file_get_contents($dir . '/files/' . $rel);
            if ($data === false) {
                return ['fatal' => 'V záloze chybí soubor ' . $rel . '.'];
            }
            $ops[] = ['op' => 'write', 'path' => $rel, 'data' => $data];
        } else {
            $ops[] = ['op' => 'delete', 'path' => $rel];
        }
    }
    foreach ((array)($m['deleted'] ?? []) as $d) {
        [$rel] = dp_path((string)$d);
        $data = $rel === null ? false : @file_get_contents($dir . '/files/' . $rel);
        if ($rel === null || $data === false) {
            return ['fatal' => 'V záloze chybí smazaný soubor ' . (string)$d . '.'];
        }
        $ops[] = ['op' => 'write', 'path' => $rel, 'data' => $data];
    }
    return dp_apply($root, $ops, 'návrat zálohy ' . $id);
}

// ---------------------------------------------------------------- výstup
const DP_STATUS = ['new' => 'nový', 'changed' => 'změněn', 'same' => 'beze změny', 'delete' => 'smazat', 'missing' => 'neexistuje, přeskočeno'];

function dp_html(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>K&amp;F – nasazení souborů</title><style>'
        . ':root{color-scheme:dark}body{margin:0;padding:14px;background:#10141b;color:#e8edf4;font:15px/1.5 system-ui,sans-serif;max-width:720px}'
        . 'h1{font-size:19px;margin:0 0 10px}h2{font-size:16px;margin:22px 0 8px}p{color:#9ba9ba}'
        . 'form{background:#191f29;border:1px solid #2b3543;border-radius:9px;padding:12px;margin:10px 0}'
        . 'input,button{font:inherit;color:inherit;background:#111720;border:1px solid #2b3543;border-radius:7px;padding:10px;width:100%;box-sizing:border-box;margin:4px 0 10px}'
        . 'button{cursor:pointer;background:#253246}button.danger{background:#7a2323}input[type=radio]{width:auto;margin:0 8px 0 0}'
        . 'table{width:100%;border-collapse:collapse;font-size:13px}td,th{border-bottom:1px solid #2b3543;padding:6px 4px;text-align:left;overflow-wrap:anywhere}'
        . '.ok{color:#8bd49a}.bad{color:#ff7777}.mut{color:#9ba9ba}code{font-size:12px}'
        . '</style></head><body><h1>K&amp;F – nasazení souborů</h1>' . $body . '</body></html>';
    exit;
}

function dp_forms(): string
{
    return '<form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="preview">'
        . '<h2>Nahrát balíček (ZIP)</h2><p>Klíč AI_WRITE_KEY se píše jen sem, nikdy do chatu.</p>'
        . '<input type="password" name="key" placeholder="AI_WRITE_KEY" autocomplete="off" required>'
        . '<input type="file" name="zip" accept=".zip" required><button type="submit">Zkontrolovat změny</button></form>'
        . '<form method="post"><input type="hidden" name="action" value="backups"><h2>Zálohy</h2>'
        . '<input type="password" name="key" placeholder="AI_WRITE_KEY" autocomplete="off" required>'
        . '<button type="submit">Zobrazit zálohy a vrátit změny</button></form>';
}

function dp_msg(string $text, bool $ok = false): string
{
    return '<p class="' . ($ok ? 'ok' : 'bad') . '">' . h($text) . '</p>';
}

// ---------------------------------------------------------------- obsluha požadavku
dp_cleanup($root);
$action = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (string)($_POST['action'] ?? '') : '';
if ($action === '') {
    dp_html(dp_forms());
}

$authError = dp_auth($root);
if ($authError !== null) {
    dp_html(dp_msg($authError) . dp_forms(), 403);
}

if ($action === 'preview') {
    $f = $_FILES['zip'] ?? null;
    if (!is_array($f) || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
        dp_html(dp_msg('Soubor se nepodařilo nahrát.') . dp_forms(), 400);
    }
    if ((int)$f['size'] > DP_MAX_ZIP) {
        dp_html(dp_msg('ZIP je větší než ' . (DP_MAX_ZIP / 1048576) . ' MB.') . dp_forms(), 413);
    }
    $id = bin2hex(random_bytes(8));
    $dest = dp_dir($root) . '/pending-' . $id . '.zip';
    if (!move_uploaded_file((string)$f['tmp_name'], $dest)) {
        dp_html(dp_msg('ZIP se nepodařilo uložit.') . dp_forms(), 500);
    }
    $plan = dp_plan($root, $dest);
    $changes = 0;
    $rows = '';
    foreach ($plan['writes'] as $w) {
        $rows .= '<tr><td><code>' . h($w['path']) . '</code></td><td>' . h(DP_STATUS[$w['status']]) . '</td></tr>';
        $changes += $w['status'] === 'same' ? 0 : 1;
    }
    foreach ($plan['deletes'] as $d) {
        $rows .= '<tr><td><code>' . h($d['path']) . '</code></td><td>' . h(DP_STATUS[$d['status']]) . '</td></tr>';
        $changes += $d['status'] === 'delete' ? 1 : 0;
    }
    $body = '<h2>Plán změn</h2>';
    if ($plan['errors']) {
        @unlink($dest);
        $body .= dp_msg('ZIP obsahuje položky, které nelze nasadit. Nic se nezapíše, dokud je neopravíš.');
        foreach ($plan['errors'] as [$name, $why]) {
            $body .= '<p class="bad"><code>' . h($name) . '</code>: ' . h($why) . '</p>';
        }
        dp_html($body . dp_forms(), 422);
    }
    if ($changes === 0) {
        @unlink($dest);
        dp_html($body . dp_msg('Žádné změny: všechny soubory jsou stejné.', true) . dp_forms());
    }
    $body .= '<table><tr><th>Soubor</th><th>Stav</th></tr>' . $rows . '</table>'
        . '<form method="post"><input type="hidden" name="action" value="apply"><input type="hidden" name="id" value="' . h($id) . '">'
        . '<p>Před zápisem se změněné a mazané soubory zazálohují.</p>'
        . '<input type="password" name="key" placeholder="AI_WRITE_KEY" autocomplete="off" required>'
        . '<button type="submit" class="danger">Nasadit ' . $changes . ' změn</button></form>';
    dp_html($body);
}

if ($action === 'apply') {
    $id = (string)($_POST['id'] ?? '');
    $file = preg_match('~^[a-f0-9]{16}$~', $id) ? dp_dir($root) . '/pending-' . $id . '.zip' : '';
    if ($file === '' || !is_file($file)) {
        dp_html(dp_msg('Nahraný ZIP už neexistuje (platí hodinu). Nahraj ho znovu.') . dp_forms(), 404);
    }
    $plan = dp_plan($root, $file);   // plán se vždy sestaví znovu, nic se nepřebírá z předchozího kroku
    if ($plan['errors']) {
        @unlink($file);
        dp_html(dp_msg('ZIP už nesplňuje podmínky. Nic se nezměnilo.') . dp_forms(), 422);
    }
    $ops = [];
    foreach ($plan['writes'] as $w) {
        if ($w['status'] !== 'same') {
            $ops[] = ['op' => 'write', 'path' => $w['path'], 'data' => $w['data']];
        }
    }
    foreach ($plan['deletes'] as $d) {
        if ($d['status'] === 'delete') {
            $ops[] = ['op' => 'delete', 'path' => $d['path']];
        }
    }
    $r = dp_apply($root, $ops, 'nasazení ZIPu');
    @unlink($file);
    if (isset($r['fatal'])) {
        dp_html(dp_msg($r['fatal']) . dp_forms(), 500);
    }
    $body = dp_msg('Hotovo: zapsáno ' . $r['written'] . ', smazáno ' . $r['deleted'] . '.', !$r['errors'])
        . '<p>Záloha: <code>' . h($r['id']) . '</code></p>';
    foreach ($r['errors'] as $e) {
        $body .= '<p class="bad">' . h($e) . '</p>';
    }
    dp_html($body . dp_forms());
}

if ($action === 'backups') {
    $rows = '';
    foreach (dp_backups($root) as $b) {
        $rows .= '<tr><td><input type="radio" name="id" value="' . h($b['id']) . '" required></td><td><code>' . h($b['id']) . '</code><br><span class="mut">'
            . h($b['label']) . ' · zapsáno ' . $b['written'] . ', smazáno ' . $b['deleted'] . '</span></td></tr>';
    }
    if ($rows === '') {
        dp_html('<h2>Zálohy</h2><p>Zatím žádné.</p>' . dp_forms());
    }
    dp_html('<h2>Zálohy</h2><form method="post"><input type="hidden" name="action" value="restore"><table>' . $rows . '</table>'
        . '<p>Vrátí soubory do stavu před vybraným nasazením. Aktuální stav se přitom také zazálohuje.</p>'
        . '<input type="password" name="key" placeholder="AI_WRITE_KEY" autocomplete="off" required>'
        . '<button type="submit" class="danger">Vrátit vybranou zálohu</button></form>');
}

if ($action === 'restore') {
    $r = dp_restore($root, (string)($_POST['id'] ?? ''));
    if (isset($r['fatal'])) {
        dp_html(dp_msg($r['fatal']) . dp_forms(), 400);
    }
    $body = dp_msg('Vráceno: zapsáno ' . $r['written'] . ', smazáno ' . $r['deleted'] . '.', !$r['errors'])
        . '<p>Záloha předchozího stavu: <code>' . h($r['id']) . '</code></p>';
    foreach ($r['errors'] as $e) {
        $body .= '<p class="bad">' . h($e) . '</p>';
    }
    dp_html($body . dp_forms());
}

dp_html(dp_msg('Neznámá akce.') . dp_forms(), 400);
