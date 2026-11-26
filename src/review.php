<?php
// src/review.php
declare(strict_types=1);

// Fronta pending: moje návrhy (/my), schvalování moderátorem (/review), obrázek návrhu (/pending/image).

function kf_pending_get(int $id): ?array
{
    $row = q('SELECT id, type, created_by, title, date_from, created_at FROM ' . tbl('pending') . ' WHERE id = ?', [$id])->fetch();
    if (!$row) {
        return null;
    }
    $row['data'] = json_read_file(json_bucket_rel('pending', $id));
    return $row;
}

function kf_pending_image_path(int $id): ?string
{
    $p = abs_path(dirname(json_bucket_rel('pending', $id))) . '/' . $id . '_l.webp';
    return is_file($p) ? $p : null;
}

/** Smaže návrh včetně JSON a obrázku (po schválení i zamítnutí fronta nebobtná). */
function kf_pending_remove(int $id): void
{
    $img = kf_pending_image_path($id);
    if ($img !== null) {
        @unlink($img);
    }
    json_remove('pending', $id);
    q('DELETE FROM ' . tbl('pending') . ' WHERE id = ?', [$id]);
}

function route_pending_image(): never
{
    $u = require_login();
    $id = (int)($_GET['id'] ?? 0);
    $row = $id > 0 ? kf_pending_get($id) : null;
    if ($row === null || !(can('review') || (int)$row['created_by'] === (int)$u['id'])) {
        if ($row !== null) {
            security_log('forbidden', ['action' => 'pending_image']);
        }
        abort($row === null ? 404 : 403, t($row === null ? 'error.404' : 'error.403'));
    }
    $p = kf_pending_image_path($id);
    if ($p === null) {
        abort(404, t('error.404'));
    }
    kf_serve_webp($p);
}

function route_my(): never
{
    $u = guard('submit');
    $rows = q(
        'SELECT id, type, title, date_from, created_at FROM ' . tbl('pending') . ' WHERE created_by = ? ORDER BY id DESC LIMIT ' . (int)cfg('paging.max_per_page'),
        [(int)$u['id']]
    )->fetchAll();
    page(t('my.title'), 'my', ['rows' => $rows]);
}

function route_my_delete(): never
{
    guard('submit');
    $row = kf_pending_get((int)post_str('id', 10));
    if ($row === null) {
        redirect('my?m=invalid');
    }
    require_can('edit_pending', $row); // vlastní návrh, nebo moderátor
    kf_pending_remove((int)$row['id']);
    redirect('my?m=pending_deleted');
}

function route_review(): never
{
    guard('review');
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $p = kf_pending_get($id);
        if ($p === null) {
            abort(404, t('error.404'));
        }
        $ev = null;
        $imgUrl = '';
        if ((int)$p['type'] === (int)cfg('enums.pending_type.event') && is_array($p['data']['values'] ?? null)) {
            $ev = kf_event_view_data(kf_event_clean($p['data']['values']));
            $imgUrl = kf_pending_image_path($id) !== null ? url('pending/image?id=' . $id) : '';
        }
        page(t('review.title'), 'review', ['mode' => 'detail', 'pend' => $p, 'ev' => $ev, 'imgUrl' => $imgUrl]);
    }
    $rows = q(
        'SELECT p.id, p.type, p.title, p.date_from, p.created_at, u.display_name FROM ' . tbl('pending') . ' p JOIN ' . tbl('users') . ' u ON u.id = p.created_by ORDER BY p.id LIMIT '
        . (int)cfg('paging.max_per_page')
    )->fetchAll();
    page(t('review.title'), 'review', ['mode' => 'list', 'rows' => $rows]);
}

/** POST review: action = approve | reject, id = návrh. */
function route_review_post(): never
{
    guard('review');
    $id = (int)post_str('id', 10);
    $action = post_str('action', 10);
    $p = $id > 0 ? kf_pending_get($id) : null;
    if ($p === null || !in_array($action, ['approve', 'reject'], true)) {
        redirect('review?m=review_invalid');
    }
    if ($action === 'reject') {
        kf_pending_remove($id);
        security_log('pending_rejected', ['id' => $id]);
        redirect('review?m=review_rejected');
    }

    if ((int)$p['type'] !== (int)cfg('enums.pending_type.event') || !is_array($p['data']['values'] ?? null)) {
        redirect('review?id=' . $id . '&m=review_unsupported');
    }
    $v = kf_event_clean($p['data']['values']);
    if (kf_event_validate($v)) {
        redirect('review?id=' . $id . '&m=review_invalid');
    }
    if (kf_event_duplicate($v, false)) {
        redirect('review?id=' . $id . '&m=review_duplicate');
    }
    try {
        kf_event_publish($v, kf_pending_image_path($id), (int)$p['created_by']);
    } catch (Throwable $e) {
        error_log('event_approve: ' . $e->getMessage());
        redirect('review?id=' . $id . '&m=save_failed');
    }
    kf_pending_remove($id);
    user_approved_increment((int)$p['created_by']);
    security_log('pending_approved', ['id' => $id]);
    redirect('review?m=review_approved');
}
