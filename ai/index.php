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
        if ($name === '.' || $name === '..' || ai_excluded($name) || $name === '.git') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path)) {
            continue;
        }

        $relative = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root))), '/');

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
        if (ai_excluded($part) || $part === '.git') {
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

$action = (string)($_GET['action'] ?? '');

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

if ($action === 'read') {
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
    echo $content;
    exit;
}

if ($action === 'all') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="kf-source-all.txt"');
    foreach (ai_get_files($root) as $file) {
        $path = ai_resolve_file($root, $file['path']);
        if ($path === false) {
            continue;
        }
        $content = @file_get_contents($path);
        if ($content === false || !ai_is_text($content)) {
            continue;
        }
        echo "\n\n";
        echo str_repeat('=', 80) . "\n";
        echo 'FILE: ' . $file['path'] . "\n";
        echo str_repeat('=', 80) . "\n";
        echo $content;
        if (!str_ends_with($content, "\n")) {
            echo "\n";
        }
    }
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
<button id="all">Zobrazit vše (TXT)</button>
</div>
<input id="search" type="search" placeholder="Filtrovat soubory…">
<div id="stats" class="meta">Načítám seznam…</div>
<div id="files"></div>
<div class="meta">API: <a href="?action=list">files.json</a> · <a href="?action=all">all.txt</a></div>
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
  button.addEventListener('click',()=>readFile(file.path));listEl.appendChild(button);
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
document.getElementById('all').addEventListener('click',()=>window.open('?action=all','_blank'));
loadList();
</script>
</body>
</html>
