<?php
// KALCER Event: bootstrap (config, database, sesi, helper). Kompatibel PHP 8.0+.
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

const APP_DIR = __DIR__;
const SCHEMA_VERSION = '1';

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
                . '<main style="max-width:36rem;margin:18vh auto;padding:0 20px"><h1 style="font-weight:500">Pendaftaran segera dibuka</h1>'
                . '<p style="color:#4f5f66;font-size:18px;line-height:1.6">Halaman pendaftaran kegiatan KALCER sedang disiapkan panitia. Silakan kembali lagi nanti.</p>'
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
    set_setting('schema_version', SCHEMA_VERSION);
}

// ---------- pengaturan ----------

const SETTING_DEFAULTS = [
    'nama_kegiatan' => 'Kegiatan KALCER',
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

function new_token(): array
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    return [$token, hash('sha256', $token)];
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
    render_page('Halaman tidak ditemukan', '<h1>Halaman tidak ditemukan</h1><p>Alamat ini tidak ada. <a href="' . h(url('/')) . '">Kembali ke pendaftaran</a>.</p>');
    exit;
}

function forbidden(string $msg = 'Anda tidak punya akses ke halaman ini.'): void
{
    http_response_code(403);
    render_page('Akses ditolak', '<h1>Akses ditolak</h1><p>' . h($msg) . '</p>');
    exit;
}
