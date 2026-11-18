<?php
declare(strict_types=1);

/**
 * Read-only source browser for the complete /_kf directory.
 * Excludes .env and .env.* files in every directory.
 */

$root = realpath(dirname(__DIR__));
if ($root === false) {
    http_response_code(500);
    exit('Project directory unavailable.');
}

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, max-age=0');

function ai_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function ai_excluded(string $name): bool
{
    return $name === '.env' || str_starts_with($name, '.env.');
}

function ai_scan(string $directory, string $root, array &$files): void
{
    $items = @scandir($directory);
    if ($items === false) {
        return;
    }

    foreach ($items as $name) {
        if ($name === '.' || $name === '..' || ai_excluded($name) || $name === '.git' || $name === 'ai-backups') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path)) {
            continue;
        }

        $relative = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root))), '/');

        // Generated source copies must never be copied into themselves.
        if ($relative === 'ai/copy' || str_starts_with($relative, 'ai/copy/')) {
            continue;
        }

        if (is_dir($path)) {
            ai_scan($path, $root, $files);
            continue;
        }

        if (!is_file($path)) {
            continue;
        }

        $size = @filesize($path);
        $files[] = [
            'path' => $relative,
            'size' => $size === false ? 0 : $size,
            'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
        ];
    }
}

function ai_get_files(string $root): array
{
    $files = [];
    ai_scan($root, $root, $files);
    usort($files, static fn(array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));
    return $files;
}

function ai_resolve_file(string $root, string $relative): string|false
{
    $relative = str_replace('\\', '/', $relative);
    if ($relative === '' || str_starts_with($relative, '/') || preg_match('~(^|/)\\.\\.?(/|$)~', $relative)) {
        return false;
    }

    foreach (explode('/', $relative) as $part) {
        if (ai_excluded($part) || $part === '.git' || $part === 'ai-backups') {
            return false;
        }
    }

    $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
    if ($candidate === false || !is_file($candidate) || is_link($root . DIRECTORY_SEPARATOR . $relative)) {
        return false;
    }

    if (!str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
        return false;
    }

    return $candidate;
}

function ai_is_text(string $content): bool
{
    return !str_contains($content, "\0");
}



/**
 * Zapisovací API. Klíč AI_WRITE_KEY se načítá přímo z .env a nikdy se neposílá klientovi.
 */
function ai_read_write_key(string $root): string
{
    $envFile = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || is_link($envFile)) {
        return '';
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*AI_WRITE_KEY\s*=\s*(.*?)\s*$/', $line, $match)) {
            $value = trim($match[1]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            return $value;
        }
    }
    return '';
}

function ai_authorized(string $root): bool
{
    $expected = ai_read_write_key($root);
    if ($expected === '' || strlen($expected) < 32) {
        return false;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $match)) {
        return false;
    }
    return hash_equals($expected, trim($match[1]));
}

function ai_validate_write_path(string $relative): string|false
{
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative)) {
        return false;
    }
    $parts = explode('/', $relative);
    foreach ($parts as $part) {
        if ($part === '' || ai_excluded($part) || $part === '.git' || $part === 'ai-backups') {
            return false;
        }
    }
    // Záměrně nelze měnit samotné administrační API.
    if ($parts[0] === 'ai') {
        return false;
    }
    return implode('/', $parts);
}

function ai_safe_target(string $root, string $relative, bool $mustExist): string|false
{
    $parent = dirname($relative);
    $parentPath = $parent === '.' ? $root : realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parent));
    if ($parentPath === false || !str_starts_with($parentPath, $root . DIRECTORY_SEPARATOR) && $parentPath !== $root) {
        return false;
    }
    $target = $parentPath . DIRECTORY_SEPARATOR . basename($relative);
    if (is_link($target)) {
        return false;
    }
    if ($mustExist && !is_file($target)) {
        return false;
    }
    if (!$mustExist && file_exists($target)) {
        return false;
    }
    return $target;
}

function ai_backup_file(string $root, string $relative, string $source): string|false
{
    $backupRoot = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ai-backups';
    if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0750, true) && !is_dir($backupRoot)) {
        return false;
    }
    $folder = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $destination = $backupRoot . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!@mkdir(dirname($destination), 0750, true)) {
        return false;
    }
    if (!@copy($source, $destination)) {
        return false;
    }
    @chmod($destination, 0600);
    return 'data/ai-backups/' . $folder . '/' . $relative;
}

function ai_write_api(string $root): never
{
    header('Cache-Control: no-store');
    if (!ai_authorized($root)) {
        ai_json(['ok' => false, 'error' => 'Unauthorized. Check AI_WRITE_KEY and Bearer token.'], 401);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        ai_json(['ok' => false, 'error' => 'Use POST with a JSON body.'], 405);
    }

    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 2097152) {
        ai_json(['ok' => false, 'error' => 'Request too large (maximum 2 MB).'], 413);
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data)) {
        ai_json(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $action = (string)($data['action'] ?? '');
    $relative = ai_validate_write_path((string)($data['file'] ?? $data['path'] ?? ''));
    if ($relative === false) {
        ai_json(['ok' => false, 'error' => 'Invalid or protected path.'], 400);
    }

    if ($action === 'create' || $action === 'write') {
        if (!array_key_exists('content', $data) || !is_string($data['content'])) {
            ai_json(['ok' => false, 'error' => 'The content field must be a string.'], 400);
        }
        if (strlen($data['content']) > 2097152) {
            ai_json(['ok' => false, 'error' => 'File too large (maximum 2 MB).'], 413);
        }

        $exists = file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($action === 'create' && $exists) {
            ai_json(['ok' => false, 'error' => 'File already exists. Use write to replace it.'], 409);
        }
        if ($action === 'write' && !$exists) {
            ai_json(['ok' => false, 'error' => 'File does not exist. Use create first.'], 404);
        }
        $target = ai_safe_target($root, $relative, $action === 'write');
        if ($target === false) {
            ai_json(['ok' => false, 'error' => 'Target directory must exist; symbolic links are not allowed.'], 400);
        }

        $backup = null;
        if ($action === 'write') {
            $backup = ai_backup_file($root, $relative, $target);
            if ($backup === false) {
                ai_json(['ok' => false, 'error' => 'Could not create a backup; file was not changed.'], 500);
            }
        }
        $written = @file_put_contents($target, $data['content'], LOCK_EX);
        if ($written === false) {
            ai_json(['ok' => false, 'error' => 'Could not write file.', 'backup' => $backup], 500);
        }
        ai_json(['ok' => true, 'action' => $action, 'file' => $relative, 'bytes' => $written, 'backup' => $backup]);
    }

    if ($action === 'mkdir') {
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = dirname($target);
        $realParent = realpath($parent);
        if ($realParent === false || ($realParent !== $root && !str_starts_with($realParent, $root . DIRECTORY_SEPARATOR)) || file_exists($target) || is_link($target)) {
            ai_json(['ok' => false, 'error' => 'Invalid directory target or it already exists.'], 409);
        }
        if (!@mkdir($target, 0755)) {
            ai_json(['ok' => false, 'error' => 'Could not create directory.'], 500);
        }
        ai_json(['ok' => true, 'action' => 'mkdir', 'path' => $relative]);
    }

    if ($action === 'rename') {
        $target = ai_safe_target($root, $relative, true);
        $newRelative = ai_validate_write_path((string)($data['new_path'] ?? ''));
        if ($target === false || $newRelative === false) {
            ai_json(['ok' => false, 'error' => 'Invalid source or destination path.'], 400);
        }
        $newTarget = ai_safe_target($root, $newRelative, false);
        if ($newTarget === false) {
            ai_json(['ok' => false, 'error' => 'Destination directory must exist and target must not exist.'], 409);
        }
        $backup = ai_backup_file($root, $relative, $target);
        if ($backup === false) {
            ai_json(['ok' => false, 'error' => 'Could not create a backup; file was not renamed.'], 500);
        }
        if (!@rename($target, $newTarget)) {
            ai_json(['ok' => false, 'error' => 'Rename failed.', 'backup' => $backup], 500);
        }
        ai_json(['ok' => true, 'action' => 'rename', 'from' => $relative, 'to' => $newRelative, 'backup' => $backup]);
    }

    if ($action === 'delete') {
        $target = ai_safe_target($root, $relative, true);
        if ($target === false) {
            ai_json(['ok' => false, 'error' => 'File not found or access denied.'], 404);
        }
        $backup = ai_backup_file($root, $relative, $target);
        if ($backup === false) {
            ai_json(['ok' => false, 'error' => 'Could not create a backup; file was not deleted.'], 500);
        }
        if (!@unlink($target)) {
            ai_json(['ok' => false, 'error' => 'Delete failed.', 'backup' => $backup], 500);
        }
        ai_json(['ok' => true, 'action' => 'delete', 'file' => $relative, 'backup' => $backup]);
    }

    ai_json(['ok' => false, 'error' => 'Unknown action. Use create, write, rename, delete or mkdir.'], 400);
}


/**
 * Vytvoří textové kopie zdrojových souborů do /ai/copy.
 * Každá kopie začíná jednoznačnou cestou původního souboru.
 */
function ai_generate_source_copies(string $root): void
{
    $copyDir = $root . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'copy';
    if (!is_dir($copyDir) && !@mkdir($copyDir, 0755, true) && !is_dir($copyDir)) {
        return;
    }
    if (is_link($copyDir) || !is_writable($copyDir)) {
        return;
    }

    $files = ai_get_files($root);
    $active = [];
    $manifest = [
        'PROJECT: /_kf/',
        'GENERATED: ' . date('Y-m-d H:i:s'),
        'FILES: ' . count($files),
        str_repeat('=', 72),
        '',
    ];

    foreach ($files as $file) {
        $relative = (string)$file['path'];
        $source = ai_resolve_file($root, $relative);
        if ($source === false || (int)$file['size'] > 2097152) {
            continue;
        }

        $content = @file_get_contents($source);
        if ($content === false || !ai_is_text($content)) {
            continue;
        }

        // Zachováme příponu původního souboru a cestu zakódujeme do názvu.
        $copyName = str_replace('/', '__', $relative) . '.txt';
        $destination = $copyDir . DIRECTORY_SEPARATOR . $copyName;
        $header = "ORIGINAL PATH: /_kf/" . $relative . "\n"
            . "ORIGINAL FILENAME: " . basename($relative) . "\n"
            . "COPY FILENAME: " . $copyName . "\n"
            . "GENERATED: " . date('Y-m-d H:i:s') . "\n"
            . str_repeat('-', 72) . "\n\n";
        $temporary = $destination . '.tmp';
        if (@file_put_contents($temporary, $header . $content, LOCK_EX) === false) {
            @unlink($temporary);
            continue;
        }
        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            continue;
        }

        $active[$copyName] = true;
        $manifest[] = $copyName . '  <-  ' . $relative;
    }

    // Odstraníme pouze naše staré TXT kopie; jiné soubory v adresáři necháme.
    foreach (glob($copyDir . DIRECTORY_SEPARATOR . '*.txt') ?: [] as $oldCopy) {
        if (is_file($oldCopy) && !is_link($oldCopy) && !isset($active[basename($oldCopy)]) && basename($oldCopy) !== 'index.txt') {
            @unlink($oldCopy);
        }
    }

    $manifestPath = $copyDir . DIRECTORY_SEPARATOR . 'index.txt';
    $manifestTmp = $manifestPath . '.tmp';
    if (@file_put_contents($manifestTmp, implode("\n", $manifest) . "\n", LOCK_EX) !== false) {
        @rename($manifestTmp, $manifestPath);
    }
}

ai_generate_source_copies($root);

$action = (string)($_GET['action'] ?? '');
if ($action === 'write') { ai_write_api($root); }

if ($action === 'list') {
    $files = ai_get_files($root);
    ai_json([
        'project' => '/_kf/',
        'generated_at' => gmdate('c'),
        'excluded' => ['.env', '.env.*', '.git/', 'symbolic links'],
        'file_count' => count($files),
        'files' => $files,
    ]);
}

if ($action === 'txt' || $action === 'read') {
    $relative = (string)($_GET['file'] ?? '');
    $path = ai_resolve_file($root, $relative);
    if ($path === false) {
        ai_json(['error' => 'File not found or access denied.'], 404);
    }

    $content = @file_get_contents($path);
    if ($content === false) {
        ai_json(['error' => 'File could not be read.'], 500);
    }
    if (!ai_is_text($content)) {
        ai_json(['error' => 'Binary file: content is not displayed.'], 415);
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . basename($relative) . '.txt"');
    echo $content;
    exit;
}

?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>K&F – AI Code Viewer</title>
<style>
:root{color-scheme:dark;--bg:#10141b;--panel:#191f29;--line:#2b3543;--text:#e8edf4;--muted:#9ba9ba;--accent:#79b8ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,sans-serif}
header{padding:16px;border-bottom:1px solid var(--line);background:var(--panel)}
h1{font-size:19px;margin:0 0 4px}p{margin:0;color:var(--muted)}
main{display:grid;grid-template-columns:minmax(230px,330px) 1fr;min-height:calc(100vh - 76px)}
aside{border-right:1px solid var(--line);padding:12px;min-height:0}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
input,button{font:inherit;border:1px solid var(--line);border-radius:7px;background:#111720;color:var(--text);padding:9px}
input{width:100%;margin-bottom:9px}button{cursor:pointer}button:hover{border-color:var(--accent)}
#files{overflow:auto;max-height:calc(100vh - 190px)}.file{display:block;width:100%;text-align:left;margin:2px 0;padding:7px 8px;border:0;background:transparent;overflow-wrap:anywhere}
.file.active,.file:hover{background:#253246}.file small{display:block;color:var(--muted)}
section{padding:14px;min-width:0}#current{color:var(--accent);margin-bottom:10px;overflow-wrap:anywhere}
pre{white-space:pre;overflow:auto;background:#0b0f14;border:1px solid var(--line);border-radius:8px;padding:14px;min-height:70vh;margin:0;font:12px/1.55 ui-monospace,monospace}
a{color:var(--accent)}.meta{font-size:12px;color:var(--muted);margin-top:8px}
@media(max-width:700px){main{grid-template-columns:1fr}aside{border-right:0;border-bottom:1px solid var(--line)}#files{max-height:34vh}pre{min-height:45vh}}
</style>
</head>
<body>
<header>
<h1>K&F – AI Code Viewer</h1>
<p>Read-only přístup ke zdrojovým souborům adresáře /_kf/. Soubory .env a .env.* jsou vyloučeny.</p>
</header>
<main>
<aside>
<div class="toolbar">
<button id="refresh">Obnovit seznam</button>

</div>
<input id="search" type="search" placeholder="Filtrovat soubory…">
<div id="stats" class="meta">Načítám seznam…</div>
<div id="files"></div>
<div class="meta">API: <a href="?action=list">files.json</a></div>
</aside>
<section>
<div id="current">Vyber soubor ze seznamu.</div>
<pre id="content">Obsah souboru se zobrazí zde.</pre>
<div class="meta">Pouze čtení. Zobrazený obsah odpovídá aktuálním souborům na serveru.</div>
</section>
</main>
<script>
const listEl=document.getElementById('files'),searchEl=document.getElementById('search'),statsEl=document.getElementById('stats'),contentEl=document.getElementById('content'),currentEl=document.getElementById('current');
let files=[],selected='';
async function loadList(){
 statsEl.textContent='Načítám…';
 try{
  const response=await fetch('?action=list',{cache:'no-store'}),data=await response.json();
  if(!response.ok)throw new Error(data.error||'Chyba načítání');
  files=data.files||[];renderList();
  statsEl.textContent=files.length+' souborů · vynecháno: .env, .env.* a .git';
 }catch(error){statsEl.textContent='Chyba: '+error.message;}
}
function renderList(){
 const term=searchEl.value.toLowerCase();
 listEl.replaceChildren();
 files.filter(file=>file.path.toLowerCase().includes(term)).forEach(file=>{
  const button=document.createElement('button');button.className='file'+(selected===file.path?' active':'');
  button.textContent=file.path;
  const small=document.createElement('small');small.textContent=(file.size||0).toLocaleString('cs-CZ')+' B';button.appendChild(small);
  button.addEventListener('click',()=>readFile(file.path));
  const link=document.createElement('a');link.href='?action=txt&file='+encodeURIComponent(file.path);link.target='_blank';link.rel='noopener';link.textContent='TXT';link.className='file';
  link.style.display='inline-block';link.style.width='auto';link.style.marginLeft='6px';link.style.padding='5px 8px';link.textContent='Otevřít TXT';
  const row=document.createElement('div');row.appendChild(button);row.appendChild(link);listEl.appendChild(row);
 });
}
async function readFile(path){
 selected=path;renderList();currentEl.textContent=path;contentEl.textContent='Načítám…';
 try{
  const response=await fetch('?action=read&file='+encodeURIComponent(path),{cache:'no-store'});
  const body=await response.text();
  if(!response.ok)throw new Error(body);
  contentEl.textContent=body;
 }catch(error){contentEl.textContent='Soubor se nepodařilo načíst: '+error.message;}
}
searchEl.addEventListener('input',renderList);
document.getElementById('refresh').addEventListener('click',loadList);
loadList();
</script>
</body>
</html>
