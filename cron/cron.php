<?php
// cron/cron.php
declare(strict_types=1);

if (!defined('KF_ROOT')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}

/** Spouští úlohy z cfg('cron.tasks') v dávkách; zámek proti souběhu (flock) a stav v cron_state.json. */
function kf_cron_run(): void
{
    if (!cfg('cron.enabled')) {
        return;
    }
    $stateRel = (string)cfg('cron.state_file');
    $interval = (int)cfg('cron.interval_sec');
    if ((int)(json_read_file($stateRel)['last_run'] ?? 0) + $interval > time()) {
        return;
    }
    $lockFile = abs_path((string)cfg('cron.lock_file'));
    ensure_dir(dirname($lockFile));
    $lock = @fopen($lockFile, 'c');
    if (!$lock) {
        return;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return;
    }
    try {
        $state = json_read_file($stateRel);
        if ((int)($state['last_run'] ?? 0) + $interval > time()) {
            return;
        }
        ignore_user_abort(true);
        @set_time_limit((int)cfg('cron.lock_timeout_sec'));
        $state['last_run'] = time();
        json_write_file($stateRel, $state);

        foreach ((array)cfg('cron.tasks') as $task) {
            $fn = 'kf_cron_task_' . $task;
            if (!preg_match('/^[a-z_]+$/', (string)$task) || !function_exists($fn)) {
                continue;
            }
            try {
                $fn();
                $state['tasks'][$task] = time();
            } catch (Throwable $e) {
                log_append((string)cfg('storage.error_log_file'), 'cron ' . $task . ': ' . $e->getMessage());
            }
        }
        json_write_file($stateRel, $state);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function kf_cron_task_session_cleanup(): void
{
    session_cleanup();
}

function kf_cron_task_tmp_cleanup(): void
{
    tmp_cleanup();
}

function kf_cron_task_ratelimit_cleanup(): void
{
    ratelimit_cleanup();
}

/** Interní AI import (jen mode = internal); implementace přijde v kroku 3 jako ai_import_run(). */
function kf_cron_task_ai_import(): void
{
    if (!cfg('ai_import.enabled') || cfg('ai_import.mode') !== 'internal') {
        return;
    }
    if (function_exists('ai_import_run')) {
        ai_import_run();
    }
}

if (cfg('cron.enabled')) {
    register_shutdown_function(static function (): void {
        try {
            if (cfg('cron.after_response') && function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            kf_cron_run();
        } catch (Throwable $e) {
            try {
                log_append((string)cfg('storage.error_log_file'), 'cron: ' . $e->getMessage());
            } catch (Throwable) {
            }
        }
    });
}
