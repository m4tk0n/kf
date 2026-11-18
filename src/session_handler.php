<?php
declare(strict_types=1);

/**
 * Encrypted MySQL session storage.
 * Raw session IDs are never stored in the database; only SHA-256 hashes are.
 * A per-session MySQL advisory lock prevents concurrent requests overwriting data.
 */
final class KfDatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @var array<string, true> */
    private array $locks = [];

    private function key(): string
    {
        return hash_hmac('sha256', 'kf-session-encryption-v1', cfg('app.key'), true);
    }

    private function idHash(string $id): string
    {
        return hash('sha256', $id);
    }

    private function lockName(string $hash): string
    {
        return 'kf:sess:' . substr($hash, 0, 55);
    }

    private function acquire(string $id): void
    {
        $hash = $this->idHash($id);
        $name = $this->lockName($hash);
        if (isset($this->locks[$name])) {
            return;
        }
        $stmt = db()->prepare('SELECT GET_LOCK(?, 8)');
        $stmt->execute([$name]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Unable to acquire session lock.');
        }
        $this->locks[$name] = true;
    }

    private function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($plain, $nonce, $this->key());
    }

    private function decrypt(string $stored): ?string
    {
        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($stored) <= $nonceLength + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }
        $nonce = substr($stored, 0, $nonceLength);
        $cipher = substr($stored, $nonceLength);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        return $plain === false ? null : $plain;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        foreach (array_keys($this->locks) as $name) {
            try {
                $stmt = db()->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$name]);
            } catch (Throwable $e) {
                error_log('K&F session lock release failed: ' . $e->getMessage());
            }
        }
        $this->locks = [];
        return true;
    }

    public function read(string $id): string|false
    {
        $this->acquire($id);
        $hash = $this->idHash($id);
        $stmt = db()->prepare(
            'SELECT payload FROM ' . tbl('sessions') . ' WHERE id_hash = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $stored = $stmt->fetchColumn();
        if (!is_string($stored)) {
            return '';
        }
        $plain = $this->decrypt($stored);
        if ($plain === null) {
            db()->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE id_hash = ?')->execute([$hash]);
            error_log('K&F invalid encrypted session payload was discarded.');
            return '';
        }
        return $plain;
    }

    public function write(string $id, string $data): bool
    {
        $this->acquire($id);
        $hash = $this->idHash($id);
        $payload = $this->encrypt($data);
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        $stmt = db()->prepare(
            'INSERT INTO ' . tbl('sessions') . ' (id_hash, user_id, payload, created_at, last_activity, expires_at)
             VALUES (?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ' . KF_SESSION_IDLE_TTL . ' SECOND))
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload),
             last_activity = NOW(), expires_at = VALUES(expires_at)'
        );
        return $stmt->execute([$hash, $userId, $payload]);
    }

    public function destroy(string $id): bool
    {
        $this->acquire($id);
        $stmt = db()->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE id_hash = ?');
        return $stmt->execute([$this->idHash($id)]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = db()->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function validateId(string $id): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM ' . tbl('sessions') . ' WHERE id_hash = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$this->idHash($id)]);
        return (bool)$stmt->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
