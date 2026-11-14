<?php
// KALCER Event: bootstrap (config, database, sesi, helper). Kompatibel PHP 8.0+.
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

const APP_DIR = __DIR__;
const SCHEMA_VERSION = '6';

// Tahap foto evidence, urut sesuai perjalanan.
const TAHAP = [
    'berangkat' => 'Berangkat',
    'tiba' => 'Tiba di lokasi',
    'kegiatan' => 'Kegiatan',
    'pulang' => 'Pulang',
    'tiba_kembali' => 'Tiba kembali',
];

const JENIS_BIAYA = [
    'bbm' => 'BBM',
    'tol' => 'Tol',
    'umum' => 'Kendaraan umum',
    'parkir' => 'Parkir',
    'lainnya' => 'Lainnya',
];

const STATUS_REIMBURSE = [
    'draft' => 'Belum diajukan',
    'diajukan' => 'Diajukan',
    'diverifikasi' => 'Diverifikasi',
    'sebagian' => 'Dibayar sebagian',
    'dibayar' => 'Dibayar',
];

// ---------- config ----------

function config(?string $key = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $publicHtml = dirname(__DIR__, 2);          // .../public_html
        $path = dirname($publicHtml) . '/event-config.php';
        if (!is_file($path)) {
            http_response_code(503);
            echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Segera dibuka | KALCER Event</title>'
                . '<!-- event-config.php tidak ditemukan di folder induk public_html. Lihat README repo (bagian Event). -->'
                . '<body style="margin:0;background:#f4f1ec;color:#1f2d33;font-family:system-ui,sans-serif">'
                . '<main style="max-width:36rem;margin:18vh auto;padding:0 20px"><h1 style="font-weight:400">Registrasi segera dibuka</h1>'
                . '<p style="color:#4f5f66;font-size:18px;line-height:1.6">Halaman registrasi kegiatan KALCER sedang disiapkan panitia. Silakan kembali lagi nanti.</p>'
                . '<p><a href="/" style="color:#1d4d63">Kembali ke KALCER</a></p></main></body></html>';
            exit;
        }
        $cfg = require $path;
        $cfg['uploads_dir'] = $cfg['uploads_dir'] ?? (dirname($publicHtml) . '/event-uploads');
        $cfg['base_url'] = rtrim($cfg['base_url'] ?? '/event', '/');
    }
    return $key === null ? $cfg : ($cfg[$key] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function schema_ready(): bool
{
    try {
        return setting('schema_version') === SCHEMA_VERSION;
    } catch (PDOException $e) {
        return false;
    }
}

function migrate(): void
{
    $sql = file_get_contents(APP_DIR . '/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
        db()->exec($stmt);
    }
    // Kolom tambahan pada tabel lama (MySQL 8 belum punya ADD COLUMN IF NOT EXISTS).
    ensure_column('peserta', 'kamar_id', 'INT UNSIGNED NULL');
    ensure_column('peserta', 'bagikan_kontak', 'TINYINT(1) NOT NULL DEFAULT 0');
    // v6: status "Dibayar sebagian" (pembayaran dicicil).
    db()->exec("ALTER TABLE instansi MODIFY reimburse_status ENUM('draft','diajukan','diverifikasi','sebagian','dibayar') NOT NULL DEFAULT 'draft'");
    // v5: publikasi per kamar. Pengaturan lama "semua dipublikasikan" dipindah ke tiap kamar.
    if (ensure_column('kamar', 'publik', 'TINYINT(1) NOT NULL DEFAULT 0') && setting('kamar_dipublikasikan') === '1') {
        db()->exec('UPDATE kamar SET publik = 1');
    }
    // v5: info untuk instansi jadi daftar bebas; isi hotel & shuttle lama ikut dipindah.
    if (setting('info_tambahan') === '' && (setting('info_hotel') !== '' || setting('info_shuttle') !== '')) {
        $items = [];
        foreach (['Hotel' => 'info_hotel', 'Shuttle' => 'info_shuttle'] as $judul => $k) {
            if (setting($k) !== '') {
                $items[] = ['judul' => $judul, 'isi' => setting($k)];
            }
        }
        set_setting('info_tambahan', json_encode($items, JSON_UNESCAPED_UNICODE));
    }
    set_setting('schema_version', SCHEMA_VERSION);
}

/** Tambah kolom bila belum ada. Mengembalikan true bila kolom baru saja dibuat. */
function ensure_column(string $table, string $column, string $definition): bool
{
    $exists = row(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
    if (!$exists) {
        db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        return true;
    }
    return false;
}

// ---------- pengaturan ----------

const SETTING_DEFAULTS = [
    'nama_kegiatan' => 'Kegiatan KALCER',
    'deskripsi' => '',
    'lokasi' => '',
    'tanggal_acara' => '2026-12-02,2026-12-03',
    'daftar_dibuka' => '1',
    'batas_daftar' => '2026-11-28 23:59',
    'batas_ganti' => '2026-12-01 12:00',
    'batas_upload' => '2026-12-17 23:59',
    'kuota_per_instansi' => '3',
    'kuota_per_hari' => '0',
    'info_hotel' => '',
    'info_shuttle' => '',
    'kontak_panitia' => '',
    'wa_panitia' => '',
    'kontak2_nama' => '',
    'kontak2_wa' => '',
    'kapasitas_kamar' => '2',
    'kamar_dipublikasikan' => '0',
    'info_tambahan' => '',
    'jadwal_link' => '',
    'jadwal_file' => '',
    'jadwal_ext' => '',
    'jadwal_nama' => '',
    'jadwal_diubah' => '',
];

function setting(string $k): string
{
    static $cache = null;
    if ($cache === null || $k === 'schema_version') {
        $cache = [];
        foreach (rows('SELECT k, v FROM pengaturan') as $r) {
            $cache[$r['k']] = (string) $r['v'];
        }
    }
    return $cache[$k] ?? (SETTING_DEFAULTS[$k] ?? '');
}

function set_setting(string $k, string $v): void
{
    q('INSERT INTO pengaturan (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

function event_dates(): array
{
    return array_values(array_filter(array_map('trim', explode(',', setting('tanggal_acara')))));
}

function before(string $settingKey): bool
{
    $v = setting($settingKey);
    return $v === '' || time() <= strtotime($v);
}

// ---------- sesi, CSRF, flash ----------

function start_session(): void
{
    session_name('KCEVENT');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 45,
        'path' => '/event',
        'secure' => (bool) config('secure_cookie'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        render_page('Sesi kedaluwarsa', '<h1>Sesi kedaluwarsa</h1><p>Halaman sudah terlalu lama dibuka. Muat ulang halaman lalu coba lagi.</p>');
        exit;
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- token instansi ----------

/**
 * Link instansi: "<slug-nama-instansi>-<6 karakter acak>". Slug hanya penanda agar mudah
 * dikenali; yang rahasia (dan yang di-hash di database) adalah 6 karakter acak. Link lama
 * (12 karakter / format panjang) tetap berlaku.
 * @return array{0:string,1:string} [token untuk URL, hash]
 */
function new_token(string $namaInstansi = ''): array
{
    $abc = 'abcdefghijkmnpqrstuvwxyz23456789';
    $secret = '';
    // 6 karakter dari 32 simbol (~1 miliar kemungkinan) + batas percobaan link salah per IP.
    for ($i = 0; $i < 6; $i++) {
        $secret .= $abc[random_int(0, strlen($abc) - 1)];
    }
    $slug = trim(substr(strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $namaInstansi), '-')), 0, 40), '-');
    return [($slug !== '' ? $slug . '-' : '') . $secret, hash('sha256', $secret)];
}

/** Cari instansi dari token di URL (format baru "slug-rahasia" maupun format lama). */
function find_instansi_by_token(string $token): ?array
{
    $r = row('SELECT id FROM instansi WHERE token_hash = ?', [hash('sha256', $token)]);
    if (!$r && preg_match('/(?:^|-)([a-z2-9]{6}|[a-z2-9]{12})$/', $token, $m)) {
        $r = row('SELECT id FROM instansi WHERE token_hash = ?', [hash('sha256', $m[1])]);
    }
    return $r;
}

function instansi_link(string $token): string
{
    return config('base_url') . '/i/' . $token;
}

function kode_instansi(int $id): string
{
    return 'KC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

// ---------- helper ----------

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return config('base_url') . $path;
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

function post(string $k, string $default = ''): string
{
    $v = $_POST[$k] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Ukuran file yang mudah dibaca, mis. "2,4 MB". */
function ukuran($bytes): string
{
    $b = (float) $bytes;
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) {
        if ($b < 1024 || $u === 'GB') {
            return ($i === 0 ? (string) (int) $b : number_format($b, 1, ',', '.')) . ' ' . $u;
        }
        $b /= 1024;
    }
    return '';
}

/** Batas upload efektif server (upload_max_filesize vs post_max_size), dalam byte. */
function server_upload_limit(): int
{
    $toBytes = static function (string $v): int {
        $v = trim($v);
        $n = (int) $v;
        switch (strtolower(substr($v, -1))) {
            case 'g': return $n * 1024 ** 3;
            case 'm': return $n * 1024 ** 2;
            case 'k': return $n * 1024;
        }
        return $n;
    };
    $a = $toBytes((string) ini_get('upload_max_filesize'));
    $b = $toBytes((string) ini_get('post_max_size'));
    return min($a ?: PHP_INT_MAX, $b ?: PHP_INT_MAX);
}

function rupiah($n): string
{
    return 'Rp' . number_format((float) $n, 0, ',', '.');
}

const BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function tgl(?string $date, bool $withDay = false): string
{
    if (!$date) {
        return '-';
    }
    $t = strtotime($date);
    $s = (int) date('j', $t) . ' ' . BULAN[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $withDay ? HARI[(int) date('w', $t)] . ', ' . $s : $s;
}

function tgl_jam(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    return tgl($dt) . ', ' . date('H.i', strtotime($dt));
}

function tgl_pendek(string $date): string
{
    $t = strtotime($date);
    return (int) date('j', $t) . ' ' . substr(BULAN[(int) date('n', $t)], 0, 3);
}

function malam_menginap(string $tanggal): string
{
    $prev = date('Y-m-d', strtotime($tanggal . ' -1 day'));
    return tgl_pendek($prev) . ' → ' . tgl_pendek($tanggal);
}

/** No. HP/WA: hanya angka, 10–15 digit, diawali 0 atau 62. Mengembalikan angka saja, atau null bila tidak valid. */
function valid_hp(string $hp): ?string
{
    $d = preg_replace('/\D/', '', $hp);
    return preg_match('/^(0|62)\d{8,13}$/', $d) ? $d : null;
}

/** NIP PNS/PPPK: 18 digit angka. */
function valid_nip(string $nip): bool
{
    return (bool) preg_match('/^\d{18}$/', $nip);
}

/** Daftar kabupaten/kota resmi (assets/kabkota.json), mis. "Kab. Bogor, Jawa Barat". */
function kabkota_list(): array
{
    static $list = null;
    if ($list === null) {
        $list = json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/kabkota.json'), true) ?: [];
    }
    return $list;
}

function valid_kabkota(string $v): bool
{
    return in_array($v, kabkota_list(), true);
}

/** Info untuk instansi (judul + isi), diatur panitia di Pengaturan. Mis. Hotel, Shuttle, Parkir. */
function info_items(): array
{
    $items = json_decode(setting('info_tambahan'), true);
    return is_array($items) ? array_values(array_filter($items, static fn ($i) => is_array($i) && trim((string) ($i['judul'] ?? '')) !== '' && trim((string) ($i['isi'] ?? '')) !== '')) : [];
}

function jk_label(?string $jk): string
{
    return $jk === 'L' ? 'Laki-laki' : ($jk === 'P' ? 'Perempuan' : '');
}

const KETERANGAN_TAMU = ['Panitia', 'Pejabat', 'Narasumber', 'Pendamping', 'Lainnya'];

function normalize_hp(string $hp): string
{
    return preg_replace('/[^0-9+]/', '', $hp);
}

function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function json_ok(array $data = []): void
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + $data);
    exit;
}

function wa_digits(string $wa): string
{
    $d = preg_replace('/\D/', '', $wa);
    return str_starts_with($d, '0') ? '62' . substr($d, 1) : $d;
}

/**
 * Kontak panitia (maks. 2 orang): nama + nomor WhatsApp. Kompatibel dengan isian lama
 * "Nama, 0812-xxx" di kontak_panitia.
 * @return array<int, array{nama:string,wa:string}>
 */
function kontak_list(): array
{
    $nama = setting('kontak_panitia');
    $wa = setting('wa_panitia');
    if ($wa === '' && preg_match('/\+?\d[\d\s.-]{6,}\d/', $nama, $m)) {
        $wa = $m[0];
        $nama = trim(str_replace($m[0], '', $nama), " ,;-");
    }
    $out = [];
    foreach ([[$nama, $wa], [setting('kontak2_nama'), setting('kontak2_wa')]] as [$n, $w]) {
        if (wa_digits($w) !== '') {
            $out[] = ['nama' => trim($n), 'wa' => wa_digits($w)];
        }
    }
    return $out;
}

/** Kontak pertama (untuk isian Pengaturan). */
function kontak_panitia(): array
{
    return kontak_list()[0] ?? ['nama' => setting('kontak_panitia'), 'wa' => ''];
}

/** Tautan "Hubungi panitia" ke WhatsApp (nomor tidak ditampilkan); dua kontak bila diisi. */
function hubungi_panitia(string $pesan = '', string $label = 'Hubungi panitia via WhatsApp'): string
{
    $list = kontak_list();
    if (!$list) {
        return 'hubungi panitia';
    }
    $pesan = $pesan !== '' ? $pesan : 'Halo panitia ' . setting('nama_kegiatan') . ', saya ingin bertanya.';
    $link = static fn (array $k, string $teks) => '<a class="wa-link" href="https://wa.me/' . h($k['wa']) . '?text=' . h(rawurlencode($pesan)) . '" target="_blank" rel="noopener">' . h($teks) . '</a>';
    if (count($list) === 1) {
        return $link($list[0], $label . ($list[0]['nama'] !== '' ? ' (' . $list[0]['nama'] . ')' : ''));
    }
    return h($label) . ': ' . implode(' atau ', array_map(static fn ($k, $i) => $link($k, $k['nama'] !== '' ? $k['nama'] : 'Panitia ' . ($i + 1)), $list, array_keys($list)));
}

function wa_link(string $text): string
{
    return 'https://wa.me/?text=' . rawurlencode($text);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------- render ----------

function view(string $name, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $name . '.php';
    return (string) ob_get_clean();
}

function render_page(string $title, string $body, array $opts = []): void
{
    echo view('layout', ['title' => $title, 'body' => $body] + $opts);
}

function render(string $name, string $title, array $vars = [], array $opts = []): void
{
    render_page($title, view($name, $vars), $opts);
}

function not_found(): void
{
    http_response_code(404);
    render_page('Halaman tidak ditemukan', '<h1>Halaman tidak ditemukan</h1><p>Alamat ini tidak ada. <a href="' . h(url('/')) . '">Kembali ke beranda</a>.</p>');
    exit;
}

function forbidden(string $msg = 'Anda tidak punya akses ke halaman ini.'): void
{
    http_response_code(403);
    render_page('Akses ditolak', '<h1>Akses ditolak</h1><p>' . h($msg) . '</p>');
    exit;
}
