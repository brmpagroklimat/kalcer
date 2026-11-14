<?php
// KALCER Event: front controller. Semua request /event/* masuk ke sini (lihat .htaccess).
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/files.php';
require __DIR__ . '/app/mail.php';
require __DIR__ . '/app/routes/publik.php';
require __DIR__ . '/app/routes/instansi.php';
require __DIR__ . '/app/routes/admin.php';
require __DIR__ . '/app/routes/microsite.php';
require __DIR__ . '/app/routes/rekap.php';
require __DIR__ . '/app/routes/kamar.php';

config();

// Error tak terduga: catat di log server, tampilkan halaman yang jelas ke pengguna.
set_exception_handler(static function (Throwable $e): void {
    $ref = strtoupper(bin2hex(random_bytes(3)));
    error_log("KALCER Event [$ref] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Terjadi kesalahan | KALCER Event</title><body style="margin:0;background:#f4f1ec;color:#1f2d33;font-family:system-ui,sans-serif">'
        . '<main style="max-width:36rem;margin:18vh auto;padding:0 20px"><h1 style="font-weight:500">Terjadi kesalahan di server</h1>'
        . '<p style="color:#4f5f66;font-size:18px;line-height:1.6">Permintaan Anda tidak bisa diproses. Coba lagi beberapa saat lagi. '
        . 'Kalau masih terjadi, sampaikan kode <b>' . $ref . '</b> ke panitia.</p>'
        . '<p><a href="/event/" style="color:#1d4d63">Kembali ke pendaftaran</a></p></main></body></html>';
});

start_session();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = '/' . trim((string) preg_replace('#^/event#', '', $path), '/');

if ($method === 'POST') {
    // Upload yang melebihi post_max_size datang dengan $_POST kosong.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        render_page('File terlalu besar', '<h1>File terlalu besar</h1><p>Ukuran upload melebihi batas server. Kembali lalu pilih file yang lebih kecil.</p>');
        exit;
    }
    check_csrf();
}

if (!schema_ready() && !str_starts_with($path, '/admin/setup')) {
    try {
        // Tabel sudah ada tapi versi lama: jalankan migrasi otomatis.
        row('SELECT 1 FROM admin LIMIT 1');
        migrate();
    } catch (PDOException $e) {
        render_page('Belum di-setup', '<h1>KALCER Event belum di-setup</h1><p>Panitia perlu membuka <a href="' . h(url('/admin/setup')) . '">halaman setup</a> terlebih dahulu.</p>');
        exit;
    }
}

$routes = [
    ['GET', '/', 'publik_beranda'],
    ['GET', '/daftar', 'publik_home'],
    ['POST', '/daftar', 'publik_daftar'],
    ['GET', '/jadwal', 'publik_jadwal'],
    ['GET', '/materi', 'publik_materi'],
    ['GET', '/galeri', 'publik_galeri'],
    ['GET', '/galeri/unduh', 'galeri_zip'],
    ['GET', '/media/m/(\d+)', 'media_materi'],
    ['GET', '/media/jadwal', 'media_jadwal'],
    ['GET', '/media/g/(\d+)(/thumb)?', 'media_galeri'],
    ['GET', '/terdaftar', 'publik_terdaftar'],
    ['GET', '/i/([A-Za-z0-9_-]{6,})', 'publik_masuk_token'],
    ['POST', '/keluar', 'publik_keluar'],

    ['GET', '/saya', 'inst_peserta'],
    ['POST', '/saya/instansi', 'inst_update_instansi'],
    ['POST', '/saya/peserta', 'inst_tambah_peserta'],
    ['POST', '/saya/peserta/(\d+)', 'inst_ubah_peserta'],
    ['POST', '/saya/peserta/(\d+)/ganti', 'inst_ganti_peserta'],
    ['POST', '/saya/peserta/(\d+)/hapus', 'inst_hapus_peserta'],
    ['GET', '/saya/foto', 'inst_foto'],
    ['POST', '/saya/foto', 'inst_upload_foto'],
    ['POST', '/saya/foto/(\d+)/hapus', 'inst_hapus_foto'],
    ['GET', '/saya/biaya', 'inst_biaya'],
    ['POST', '/saya/rekening', 'inst_rekening'],
    ['POST', '/saya/biaya', 'inst_tambah_biaya'],
    ['POST', '/saya/biaya/(\d+)/hapus', 'inst_hapus_biaya'],
    ['POST', '/saya/ajukan', 'inst_ajukan'],
    ['GET', '/file/(e|b|t)/(\d+)', 'serve_upload'],

    ['GET', '/admin/setup', 'admin_setup'],
    ['POST', '/admin/setup', 'admin_setup'],
    ['GET', '/admin/login', 'admin_login'],
    ['POST', '/admin/login', 'admin_login'],
    ['POST', '/admin/keluar', 'admin_keluar'],
    ['GET', '/admin', 'admin_dashboard'],
    ['GET', '/admin/instansi', 'admin_instansi_list'],
    ['GET', '/admin/instansi/(\d+)', 'admin_instansi_detail'],
    ['POST', '/admin/instansi/(\d+)/link', 'admin_instansi_link'],
    ['POST', '/admin/instansi/(\d+)/buka', 'admin_instansi_buka'],
    ['POST', '/admin/instansi/(\d+)/reimburse', 'admin_instansi_reimburse'],
    ['POST', '/admin/instansi/(\d+)/hapus', 'admin_instansi_hapus'],
    ['GET', '/admin/instansi/(\d+)/evidence\.zip', 'admin_evidence_zip'],
    ['POST', '/admin/biaya/(\d+)', 'admin_biaya'],
    ['POST', '/admin/instansi/(\d+)/bayar', 'admin_bayar'],
    ['POST', '/admin/instansi/(\d+)/rekening', 'admin_rekening'],
    ['POST', '/admin/bayar/(\d+)/hapus', 'admin_bayar_hapus'],
    ['GET', '/admin/rekap', 'admin_rekap'],
    ['GET', '/admin/rekap/(peserta|menginap|foto|reimburse)', 'admin_rekap'],
    ['GET', '/admin/kamar', 'admin_kamar'],
    ['POST', '/admin/kamar/rekomendasi', 'admin_kamar_rekomendasi'],
    ['POST', '/admin/kamar/penempatan', 'admin_kamar_penempatan'],
    ['POST', '/admin/kamar/daftar', 'admin_kamar_daftar'],
    ['POST', '/admin/kamar/reset', 'admin_kamar_reset'],
    ['POST', '/admin/kamar/publikasi', 'admin_kamar_publikasi'],
    ['POST', '/admin/kamar/(\d+)/hapus', 'admin_kamar_hapus'],
    ['POST', '/admin/tamu', 'admin_tamu_tambah'],
    ['POST', '/admin/tamu/(\d+)/hapus', 'admin_tamu_hapus'],
    ['GET', '/admin/konten', 'admin_konten'],
    ['POST', '/admin/jadwal', 'admin_jadwal_simpan'],
    ['POST', '/admin/materi', 'admin_materi_tambah'],
    ['POST', '/admin/materi/(\d+)/hapus', 'admin_materi_hapus'],
    ['POST', '/admin/galeri', 'admin_galeri_tambah'],
    ['POST', '/admin/galeri/(\d+)/hapus', 'admin_galeri_hapus'],
    ['GET', '/admin/pengaturan', 'admin_pengaturan'],
    ['POST', '/admin/pengaturan', 'admin_pengaturan'],
    ['POST', '/admin/akun', 'admin_akun'],
    ['GET', '/admin/ekspor/(peserta|menginap|reimburse)\.csv', 'admin_ekspor'],
];

foreach ($routes as [$m, $pattern, $handler]) {
    if ($m === $method && preg_match('#^' . $pattern . '$#', $path, $match)) {
        array_shift($match);
        try {
            $handler(...$match);
        } catch (UserError $e) {
            if (wants_json()) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            flash('error', $e->getMessage());
            $back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            redirect(str_starts_with($back, config('base_url')) ? $back : '/');
        }
        exit;
    }
}
not_found();
