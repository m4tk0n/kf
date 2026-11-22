<?php
// src/mail.php
declare(strict_types=1);

function mail_clean_header(string $s): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $s) ?? '');
}

/** Zařadí e-mail do fronty data/mail_queue.json (odesílá cron). */
function mail_enqueue(string $to, string $subject, string $body): bool
{
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $max = (int)cfg('mail.queue_max');
    return (bool)json_locked((string)cfg('storage.mail_queue_file'), static function (array &$d) use ($to, $subject, $body, $max): bool {
        $d['queue'] = array_values((array)($d['queue'] ?? []));
        $d['queue'][] = ['to' => $to, 'subject' => mail_clean_header($subject), 'body' => $body, 'attempts' => 0, 'created' => time()];
        if ($max > 0 && count($d['queue']) > $max) {
            $d['queue'] = array_slice($d['queue'], -$max);
        }
        return true;
    });
}

/** Odešle dávku z fronty přes mail(); dodržuje limits.emails_per_hour a mail.queue_batch. Vrací počet odeslaných. */
function mail_process_queue(): int
{
    if (cfg('mail.transport') !== 'mail') {
        return 0; // SMTP je zatím jen rezerva
    }
    $from = mail_clean_header((string)env('MAIL_FROM', ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }
    $fromName = mail_clean_header((string)env('MAIL_FROM_NAME', ''));
    $perHour = (int)cfg('limits.emails_per_hour');
    $batch = (int)cfg('mail.queue_batch');
    $maxAttempts = (int)cfg('mail.max_attempts');

    return (int)json_locked((string)cfg('storage.mail_queue_file'), static function (array &$d) use ($from, $fromName, $perHour, $batch, $maxAttempts): int {
        $now = time();
        $sent = array_values(array_filter((array)($d['sent'] ?? []), static fn($t): bool => (int)$t > $now - 3600));
        $queue = array_values((array)($d['queue'] ?? []));
        $allowed = min($batch, $perHour - count($sent));
        $done = 0;
        $rest = [];
        foreach ($queue as $m) {
            if ($done >= $allowed) {
                $rest[] = $m;
                continue;
            }
            $headers = [
                'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $from . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $ok = @mail(
                (string)$m['to'],
                '=?UTF-8?B?' . base64_encode((string)$m['subject']) . '?=',
                str_replace(["\r\n", "\r"], "\n", (string)$m['body']),
                implode("\r\n", $headers)
            );
            if ($ok) {
                $sent[] = $now;
                $done++;
                continue;
            }
            $m['attempts'] = (int)($m['attempts'] ?? 0) + 1;
            if ($m['attempts'] < $maxAttempts) {
                $rest[] = $m;
            }
        }
        $d['sent'] = $sent;
        $d['queue'] = $rest;
        return $done;
    });
}
