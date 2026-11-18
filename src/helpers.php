<?php
declare(strict_types=1);

// ---------- .env a config ----------
function load_env(string $file): void
{
    if (!is_file($file)) {
        throw new RuntimeException('.env not found');
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $GLOBALS['KF_ENV'][trim($k)] = $v;
    }
}

function env(string $key): string
{
    if (!isset($GLOBALS['KF_ENV'][$key])) {
        throw new RuntimeException("Missing .env key: $key");
    }
    return $GLOBALS['KF_ENV'][$key];
}

function cfg(string $key): mixed
{
    $v = $GLOBALS['KF_CFG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            throw new RuntimeException("Missing config key: $key");
        }
        $v = $v[$part];
    }
    return $v;
}

function path(string $dir, string $sub = ''): string
{
    return KF_ROOT . '/' . cfg("paths.$dir") . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

// ---------- databáze ----------
function tbl(string $name): string
{
    return '`' . cfg('db.prefix') . preg_replace('/[^a-z_]/', '', $name) . '`';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . cfg('db.host') . ';dbname=' . cfg('db.name') . ';charset=' . cfg('db.charset');
        $pdo = new PDO($dsn, cfg('db.user'), cfg('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---------- text ----------
function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $s): string
{
    static $map = [
        'á'=>'a','ä'=>'a','à'=>'a','â'=>'a','å'=>'a','ą'=>'a','ã'=>'a','æ'=>'ae',
        'č'=>'c','ć'=>'c','ç'=>'c','ď'=>'d','é'=>'e','ě'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ę'=>'e',
        'í'=>'i','ï'=>'i','î'=>'i','ł'=>'l','ľ'=>'l','ĺ'=>'l','ň'=>'n','ń'=>'n','ñ'=>'n',
        'ó'=>'o','ö'=>'o','ô'=>'o','ő'=>'o','ø'=>'o','õ'=>'o','œ'=>'oe',
        'ř'=>'r','š'=>'s','ś'=>'s','ß'=>'ss','ť'=>'t','ú'=>'u','ů'=>'u','ü'=>'u','ű'=>'u','û'=>'u',
        'ý'=>'y','ÿ'=>'y','ž'=>'z','ź'=>'z','ż'=>'z',
    ];
    $s = strtr(mb_strtolower($s, 'UTF-8'), $map);
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

// ---------- jazyky ----------
function lang_code(?string $set = null): string
{
    static $lang = null;
    if ($set !== null) {
        $lang = $set;
    }
    return $lang ?? cfg('app.default_lang');
}

function lang_groups(?string $add = null): array
{
    static $g = ['common' => true];
    if ($add !== null) {
        $g[$add] = true;
    }
    return array_keys($g);
}

function lang_load(string $group): void
{
    lang_groups($group);
}

function lang_map(string $lang): array
{
    static $cache = [];
    $groups = lang_groups();
    $ck = $lang . '|' . implode(',', $groups);
    if (!isset($cache[$ck])) {
        $map = [];
        foreach ($groups as $g) {
            $f = path('lang', "$lang/$g.ini");
            if (is_file($f)) {
                $map += parse_ini_file($f) ?: [];
            }
        }
        $cache[$ck] = $map;
    }
    return $cache[$ck];
}

// Překlad za běhu; chybějící klíč v aktuálním jazyce se doplní z výchozího
function L(string $key, array $params = []): string
{
    $s = lang_map(lang_code())[$key] ?? lang_map(cfg('app.default_lang'))[$key] ?? $key;
    foreach ($params as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

// Množné číslo: klíče key-one / key-few / key-many
function Ln(string $key, int $n): string
{
    $form = cfg('plural.' . lang_code())($n);
    return L("$key-$form", ['n' => $n]);
}

function url(string $path = '', ?string $lang = null): string
{
    return rtrim(cfg('app.base_url'), '/') . '/' . ($lang ?? lang_code()) . '/' . ltrim($path, '/');
}

// Jazyk pro adresu bez jazyka: cookie z přepínače, pak hlavička prohlížeče, pak výchozí
function detect_lang(): string
{
    $langs = cfg('app.languages');
    $header = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    $preferences = [];

    foreach (explode(',', $header) as $index => $item) {
        $parts = array_map('trim', explode(';', $item));
        $tag = $parts[0] ?? '';
        if ($tag === '') {
            continue;
        }

        $quality = 1.0;
        foreach (array_slice($parts, 1) as $parameter) {
            if (preg_match('/^q=([0-9.]+)$/', $parameter, $match)) {
                $quality = (float)$match[1];
                break;
            }
        }
        if ($quality <= 0 || $quality > 1) {
            continue;
        }

        $primary = explode('-', $tag)[0];
        if (in_array($primary, $langs, true)) {
            $preferences[] = [
                'language' => $primary,
                'quality' => $quality,
                'order' => $index,
            ];
        }
    }

    usort($preferences, static function (array $a, array $b): int {
        return ($b['quality'] <=> $a['quality']) ?: ($a['order'] <=> $b['order']);
    });

    return $preferences[0]['language'] ?? cfg('app.default_lang');
}

function set_lang_cookie(string $lang): void
{
    setcookie(cfg('app.cookie_lang'), $lang, [
        'expires'  => time() + 86400 * (int)cfg('app.cookie_days'),
        'path'     => rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/') . '/',
        'secure'   => str_starts_with(cfg('app.base_url'), 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ---------- šablony ----------
// Klíče [btn-back] se nahradí při kompilaci šablony (ne ve výstupu), takže data z DB
// se nikdy nepřekládají. Zkompilovaná šablona se kešuje po jazycích.
function view(string $name, array $vars = []): string
{
    $lang  = lang_code();
    $def   = cfg('app.default_lang');
    $src   = path('templates', "$name.php");
    $dst   = path('cache', 'tpl/' . $lang . '/' . str_replace('/', '_', $name) . '.php');
    $files = array_merge(
        [$src],
        glob(path('lang', "$lang/*.ini")) ?: [],
        glob(path('lang', "$def/*.ini")) ?: []
    );
    $newest = max(array_map('filemtime', $files));

    if (!is_file($dst) || filemtime($dst) < $newest) {
        $map = [];
        foreach (lang_map($lang) + lang_map($def) as $k => $v) {
            $map['[' . $k . ']'] = e($v);
        }
        @mkdir(dirname($dst), 0755, true);
        file_put_contents($dst, strtr((string)file_get_contents($src), $map), LOCK_EX);
    }

    extract($vars, EXTR_SKIP);
    ob_start();
    include $dst;
    return (string)ob_get_clean();
}

// Celá stránka, nebo jen obsah, když ho JavaScript žádá hlavičkou X-Partial
function page(string $tpl, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    $title   = (string)($vars['title'] ?? L('site-name'));
    $content = view($tpl, $vars);
    header('Vary: X-Partial');
    if (($_SERVER['HTTP_X_PARTIAL'] ?? '') === '1') {
        header('X-Title: ' . rawurlencode($title));
        echo $content;
        return;
    }
    echo view('layout', ['content' => $content, 'title' => $title]);
}


// ---------- autentizace a oprávnění ----------
// Session obsahuje pouze interní stav; role se nikdy nepovažuje za důvěryhodnou
// hodnotu ze session a při autorizaci se vždy načítá z databáze.
const KF_SESSION_IDLE_TTL = 1800;       // 30 minut nečinnosti
const KF_SESSION_ABSOLUTE_TTL = 43200;  // 12 hodin od přihlášení

function clear_authenticated_session(): void
{
    unset($_SESSION['user'], $_SESSION['csrf_token']);
    session_regenerate_id(true);
}

function refresh_authenticated_user(): ?array
{
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        return null;
    }

    $now = time();
    $created = (int)($_SESSION['auth_created_at'] ?? 0);
    $lastSeen = (int)($_SESSION['auth_last_seen_at'] ?? 0);
    if ($created <= 0 || $lastSeen <= 0 ||
        $now - $lastSeen > KF_SESSION_IDLE_TTL ||
        $now - $created > KF_SESSION_ABSOLUTE_TTL) {
        clear_authenticated_session();
        return null;
    }

    $users = tbl('users');
    $stmt = db()->prepare("SELECT id, role, state, confirmed FROM {$users} WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$sessionUser['id']]);
    $fresh = $stmt->fetch();

    $role = (int)($fresh['role'] ?? 0);
    $state = (int)($fresh['state'] ?? 2);
    $knownRoles = array_map('intval', array_keys(cfg('enums.role')));
    if (!$fresh || !in_array($role, $knownRoles, true) ||
        !in_array($state, [0, 1, 2], true) || $state === 2 ||
        (int)$fresh['confirmed'] !== 1) {
        clear_authenticated_session();
        return null;
    }

    // Role and state are refreshed from DB; no permission is taken from cookie/session.
    $_SESSION['user']['role'] = $role;
    $_SESSION['user']['state'] = $state;
    $_SESSION['auth_last_seen_at'] = $now;
    return $_SESSION['user'];
}

function require_role(int $minimumRole): array
{
    $user = refresh_authenticated_user();
    if (!$user || (int)$user['role'] < $minimumRole) {
        // Deliberately do not reveal that a protected route exists.
        http_response_code(404);
        header('Cache-Control: no-store, private');
        exit('Not Found');
    }
    return $user;
}

function require_csrf_token(): void
{
    $submitted = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        header('Cache-Control: no-store, private');
        exit('Forbidden');
    }
}


/**
 * Database-backed PHP sessions. The browser stores only the random session ID;
 * the serialized session payload stays in the prefixed sessions table.
 */
final class KfDatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        try {
            $hash = hash('sha256', $id);
            $stmt = $this->pdo->prepare(
                "SELECT payload FROM {$this->table} WHERE id_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1"
            );
            $stmt->execute([$hash]);
            $payload = $stmt->fetchColumn();
            if ($payload === false) {
                $delete = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id_hash = ? AND expires_at <= UTC_TIMESTAMP()");
                $delete->execute([$hash]);
                return '';
            }
            return (string)$payload;
        } catch (Throwable $e) {
            error_log('K&F session read failed: ' . $e->getMessage());
            return false;
        }
    }

    public function validateId(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM {$this->table} WHERE id_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1"
            );
            $stmt->execute([hash('sha256', $id)]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('K&F session validation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table}
                 SET user_id = ?, last_activity = UTC_TIMESTAMP(),
                     expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 12 HOUR)
                 WHERE id_hash = ? AND expires_at > UTC_TIMESTAMP()"
            );
            $userId = $_SESSION['user']['id'] ?? null;
            $userId = is_numeric($userId) && (int)$userId > 0 ? (int)$userId : null;
            $stmt->bindValue(1, $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(2, hash('sha256', $id), PDO::PARAM_STR);
            return $stmt->execute() && $stmt->rowCount() >= 0;
        } catch (Throwable $e) {
            error_log('K&F session timestamp update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $userId = is_numeric($userId) && (int)$userId > 0 ? (int)$userId : null;
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table}
                    (id_hash, user_id, payload, created_at, last_activity, expires_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 12 HOUR))
                 ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    payload = VALUES(payload),
                    last_activity = UTC_TIMESTAMP(),
                    expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 12 HOUR)"
            );
            $stmt->bindValue(1, hash('sha256', $id), PDO::PARAM_STR);
            $stmt->bindValue(2, $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(3, $data, PDO::PARAM_LOB);
            return $stmt->execute();
        } catch (Throwable $e) {
            error_log('K&F session write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id_hash = ?");
            return $stmt->execute([hash('sha256', $id)]);
        } catch (Throwable $e) {
            error_log('K&F session destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires_at <= UTC_TIMESTAMP()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('K&F session cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
}
