<?php
// src/review.php
declare(strict_types=1);

function kf_pending_get(int $id): ?array
{
    try {
        $row = q('SELECT id, type, created_by, title, date_from, created_at, payload FROM ' . tbl('pending') . ' WHERE id = ?', [$id])->fetch();
    } catch (Throwable $e) {
        // Existing databases may not yet have the payload column; keep their queue usable.
        $row = q('SELECT id, type, created_by, title, date_from, created_at FROM ' . tbl('pending') . ' WHERE id = ?', [$id])->fetch();
    }
    if (!$row) return null;
    $rel = json_bucket_rel('pending', $id);
    $row['data'] = json_read_file($rel);
    if ((!is_array($row['data']) || $row['data'] === []) && is_string($row['payload'] ?? null) && $row['payload'] !== '') {
        $decoded = json_decode($row['payload'], true);
        if (is_array($decoded)) {
            $row['data'] = $decoded;
            json_write_file($rel, $decoded, true);
        }
    }
    unset($row['payload']);
    return $row;
}

function kf_pending_image_path(int $id): ?string
{
    $p = abs_path(dirname(json_bucket_rel('pending', $id))) . '/' . $id . '_l.webp';
    return is_file($p) ? $p : null;
}

function kf_pending_lock_state(int $id): ?array
{
    $data = json_read_file(json_bucket_rel('pending', $id));
    $lock = $data['lock'] ?? null;
    if (!is_array($lock) || (int)($lock['user_id'] ?? 0) < 1) return null;
    if ((int)($lock['at'] ?? 0) + max(60, (int)cfg('review.lock_ttl_sec', 900)) <= time()) return null;
    return $lock;
}

function kf_pending_lock(int $id, array $user, bool $force = false): bool
{
    $uid = (int)$user['id'];
    $name = (string)$user['display_name'];
    $ttl = max(60, (int)cfg('review.lock_ttl_sec', 900));
    return (bool)json_locked(json_bucket_rel('pending', $id), static function (array &$data) use ($uid, $name, $ttl, $force): bool {
        $lock = $data['lock'] ?? null;
        $active = is_array($lock) && (int)($lock['user_id'] ?? 0) > 0 && (int)($lock['at'] ?? 0) + $ttl > time();
        if ($active && (int)$lock['user_id'] !== $uid && !$force) return false;
        $data['lock'] = ['user_id' => $uid, 'name' => $name, 'at' => time()];
        return true;
    }, true);
}

function kf_pending_unlock(int $id, int $uid): bool
{
    return (bool)json_locked(json_bucket_rel('pending', $id), static function (array &$data) use ($uid): bool {
        if ((int)($data['lock']['user_id'] ?? 0) !== $uid) return false;
        unset($data['lock']);
        return true;
    }, true);
}

function kf_pending_acquire(int $id, array $user): void
{
    if (!kf_pending_lock($id, $user)) redirect('review?id=' . $id . '&m=review_locked');
}

function kf_pending_remove(int $id): void
{
    $img = kf_pending_image_path($id);
    if ($img !== null) @unlink($img);
    json_remove('pending', $id);
    q('DELETE FROM ' . tbl('pending') . ' WHERE id = ?', [$id]);
}

function route_pending_image(): never
{
    $u = require_login();
    $id = (int)($_GET['id'] ?? 0);
    $row = $id > 0 ? kf_pending_get($id) : null;
    if ($row === null || !(can('review') || (int)$row['created_by'] === (int)$u['id'])) {
        if ($row !== null) security_log('forbidden', ['action' => 'pending_image']);
        abort($row === null ? 404 : 403, t($row === null ? 'error.404' : 'error.403'));
    }
    $p = kf_pending_image_path($id);
    if ($p === null) abort(404, t('error.404'));
    kf_serve_webp($p);
}

function route_my(): never
{
    $u = guard('submit');
    $rows = q('SELECT id, type, title, date_from, created_at FROM ' . tbl('pending') . ' WHERE created_by = ? ORDER BY id DESC LIMIT ' . (int)cfg('paging.max_per_page'), [(int)$u['id']])->fetchAll();
    page(t('my.title'), 'my', ['rows' => $rows]);
}

function route_my_delete(): never
{
    $u = guard('submit');
    $row = kf_pending_get((int)post_str('id', 10));
    if ($row === null) redirect('my?m=invalid');
    require_can('edit_pending', $row);
    if (kf_pending_lock_state((int)$row['id']) !== null) redirect('my?m=pending_locked');
    kf_pending_remove((int)$row['id']);
    redirect('my?m=pending_deleted');
}

function route_review(): never
{
    $u = guard('review');
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $p = kf_pending_get($id);
        if ($p === null) abort(404, t('error.404'));
        $mine = kf_pending_lock($id, $u);
        $p = kf_pending_get($id) ?? $p;
        $lock = $p['data']['lock'] ?? null;
        $ev = null;
        $imgUrl = '';
        if ((int)$p['type'] === (int)cfg('enums.pending_type.event') && is_array($p['data']['values'] ?? null)) {
            $ev = kf_event_view_data(kf_event_clean($p['data']['values']));
            $imgUrl = kf_pending_image_path($id) !== null ? url('pending/image?id=' . $id) : '';
        }
        $venue = null;
        if ((int)$p['type'] === (int)cfg('enums.pending_type.venue') && is_array($p['data']['values'] ?? null)) {
            $venue = kf_venue_view_data(kf_venue_clean($p['data']['values']));
            $imgUrl = kf_pending_image_path($id) !== null ? url('pending/image?id=' . $id) : '';
        }
        page(t('review.title'), 'review', ['mode'=>'detail','pend'=>$p,'ev'=>$ev,'venue'=>$venue,'imgUrl'=>$imgUrl,'lock'=>$lock,'lockMine'=>$mine,'canTakeover'=>can('manage_users')]);
    }
    $rows = q('SELECT p.id, p.type, p.title, p.date_from, p.created_at, u.display_name FROM ' . tbl('pending') . ' p JOIN ' . tbl('users') . ' u ON u.id = p.created_by ORDER BY p.id LIMIT ' . (int)cfg('paging.max_per_page'))->fetchAll();
    foreach ($rows as &$row) $row['lock'] = kf_pending_lock_state((int)$row['id']);
    unset($row);
    page(t('review.title'), 'review', ['mode'=>'list','rows'=>$rows]);
}

function route_review_post(): never
{
    $u = guard('review');
    $id = (int)post_str('id', 10);
    $action = post_str('action', 20);
    $p = $id > 0 ? kf_pending_get($id) : null;
    if ($p === null) redirect('review?m=review_invalid');

    if ($action === 'takeover' && can('manage_users')) {
        kf_pending_lock($id, $u, true);
        redirect('review?id=' . $id);
    }
    if ($action === 'unlock') {
        if (!kf_pending_unlock($id, (int)$u['id'])) redirect('review?id=' . $id . '&m=review_locked');
        redirect('review?id=' . $id . '&m=review_unlocked');
    }
    if (!in_array($action, ['approve','reject'], true)) redirect('review?m=review_invalid');
    kf_pending_acquire($id, $u);
    $p = kf_pending_get($id) ?? $p;
    if ($action === 'reject') {
        kf_pending_remove($id);
        security_log('pending_rejected', ['id'=>$id]);
        redirect('review?m=review_rejected');
    }
    if (!is_array($p['data']['values'] ?? null)) redirect('review?id=' . $id . '&m=review_unsupported');
    if ((int)$p['type'] === (int)cfg('enums.pending_type.venue')) {
        $v = kf_venue_clean($p['data']['values']);
        if (kf_venue_validate($v) || kf_venue_duplicate($v, false)) redirect('review?id=' . $id . '&m=review_invalid');
        try {
            kf_venue_publish($v, kf_pending_image_path($id), (int)$p['created_by']);
        } catch (Throwable $e) {
            error_log('venue_approve: ' . $e->getMessage());
            redirect('review?id=' . $id . '&m=save_failed');
        }
        kf_pending_remove($id);
        user_approved_increment((int)$p['created_by']);
        security_log('pending_approved', ['id'=>$id, 'type'=>'venue']);
        redirect('review?m=review_approved');
    }
    if ((int)$p['type'] !== (int)cfg('enums.pending_type.event')) redirect('review?id=' . $id . '&m=review_unsupported');
    $v = kf_event_clean($p['data']['values']);
    if (kf_event_validate($v)) redirect('review?id=' . $id . '&m=review_invalid');
    if (kf_event_duplicate($v, false)) redirect('review?id=' . $id . '&m=review_duplicate');
    try {
        kf_event_publish($v, kf_pending_image_path($id), (int)$p['created_by']);
    } catch (Throwable $e) {
        error_log('event_approve: ' . $e->getMessage());
        redirect('review?id=' . $id . '&m=save_failed');
    }
    kf_pending_remove($id);
    user_approved_increment((int)$p['created_by']);
    security_log('pending_approved', ['id'=>$id]);
    redirect('review?m=review_approved');
}

function kf_event_pending_update(int $id, array $v, ?GdImage $newImage, bool $removeImage, int $uid): void
{
    $rel = json_bucket_rel('pending', $id);
    $base = abs_path(dirname($rel)) . '/' . $id;
    $oldImage = kf_pending_image_path($id);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        json_locked($rel, static function (array &$data) use ($id,$v,$newImage,$removeImage,$uid,$base,$oldImage): void {
            $ttl = max(60, (int)cfg('review.lock_ttl_sec', 900));
            $lock = $data['lock'] ?? null;
            if (!is_array($lock) || (int)($lock['user_id'] ?? 0) !== $uid || (int)($lock['at'] ?? 0) + $ttl <= time()) throw new RuntimeException('review_lock_lost');
            $hasImage = $oldImage !== null;
            if ($newImage !== null) { kf_img_write($newImage, $base, ['l']); $hasImage = true; }
            elseif ($removeImage) { @unlink($base . '_l.webp'); $hasImage = false; }
            unset($v['image_url']);
            $data['kind'] = 'event';
            $data['values'] = $v;
            $data['flyer'] = $hasImage;
            $data['lock']['at'] = time();
            q('UPDATE ' . tbl('pending') . ' SET title = ?, name_ascii = ?, date_from = ? WHERE id = ?', [$v['name'], kf_ascii($v['name']), $v['date_from'], $id]);
        }, true);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function route_review_edit(): never
{
    $u = guard('review');
    $id = (int)($_GET['id'] ?? 0);
    $p = $id > 0 ? kf_pending_get($id) : null;
    if ($p === null || (int)$p['type'] !== (int)cfg('enums.pending_type.event') || !is_array($p['data']['values'] ?? null)) abort(404, t('error.404'));
    kf_pending_acquire($id, $u);
    $p = kf_pending_get($id) ?? $p;
    $v = kf_event_clean($p['data']['values']);
    $v['image_url'] = '';
    kf_event_form_page($v, [], '', 200, ['formAction'=>url('review/edit?id='.$id),'pageTitle'=>t('review.edit.title'),'submitLabel'=>t('review.btn.save'),'backUrl'=>url('review?id='.$id),'imgUrl'=>kf_pending_image_path($id)!==null?url('pending/image?id='.$id):'']);
}

function route_review_edit_post(): never
{
    $u = guard('review');
    $id = (int)post_str('id', 10);
    $p = $id > 0 ? kf_pending_get($id) : null;
    if ($p === null || (int)$p['type'] !== (int)cfg('enums.pending_type.event')) redirect('review?m=review_invalid');
    kf_pending_acquire($id, $u);
    [$v,$errors] = kf_event_input();
    $opts = ['formAction'=>url('review/edit?id='.$id),'pageTitle'=>t('review.edit.title'),'submitLabel'=>t('review.btn.save'),'backUrl'=>url('review?id='.$id),'imgUrl'=>kf_pending_image_path($id)!==null?url('pending/image?id='.$id):''];
    if ($errors) { $v['image_url']=''; kf_event_form_page($v,$errors,'',422,$opts); }
    [$newImage,$imgError] = kf_event_image_request($v['image_url']);
    if ($imgError !== null) { $v['image_url']=''; kf_event_form_page($v,[$imgError],'',422,$opts); }
    try {
        kf_event_pending_update($id,$v,$newImage,post_str('remove_image',1)==='1',(int)$u['id']);
    } catch (Throwable $e) {
        error_log('review_edit: '.$e->getMessage());
        redirect('review?id='.$id.'&m='.($e->getMessage()==='review_lock_lost'?'review_locked':'save_failed'));
    }
    redirect('review?id='.$id.'&m=saved');
}
