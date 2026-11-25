<?php
// Akses instansi (lewat link token) dan panitia (username + password).
declare(strict_types=1);

final class UserError extends RuntimeException
{
}

function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $id = $_SESSION['admin_id'] ?? null;
        $admin = $id ? row('SELECT id, nama, username FROM admin WHERE id = ?', [$id]) : null;
    }
    return $admin;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) {
        redirect('/admin/login');
    }
    return $a;
}

function current_instansi(): ?array
{
    $id = $_SESSION['instansi_id'] ?? null;
    return $id ? row('SELECT * FROM instansi WHERE id = ?', [$id]) : null;
}

function require_instansi(): array
{
    $i = current_instansi();
    if (!$i) {
        render('instansi/masuk', 'Masuk', [], ['narrow' => true]);
        exit;
    }
    return $i;
}

/** Panitia yang sedang membuka halaman instansi boleh melewati batas waktu. */
function is_panitia(): bool
{
    return current_admin() !== null;
}

function actor(): string
{
    $a = current_admin();
    return $a ? 'Panitia: ' . $a['nama'] : 'Instansi';
}

function log_riwayat(int $instansiId, string $aksi, string $ket): void
{
    q('INSERT INTO riwayat (instansi_id, aksi, keterangan, oleh) VALUES (?, ?, ?, ?)', [$instansiId, $aksi, $ket, actor()]);
}

function login_blocked(): bool
{
    $n = row('SELECT COUNT(*) n FROM login_gagal WHERE ip = ? AND at > NOW() - INTERVAL 15 MINUTE', [client_ip()]);
    return (int) $n['n'] >= 8;
}

function login_failed(): void
{
    q('INSERT INTO login_gagal (ip) VALUES (?)', [client_ip()]);
    q('DELETE FROM login_gagal WHERE at < NOW() - INTERVAL 1 DAY');
}
