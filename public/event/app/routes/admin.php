<?php
// Halaman panitia: setup, login, dashboard, verifikasi, pengaturan, ekspor.
declare(strict_types=1);

function admin_setup(): void
{
    $hasAdmin = false;
    try {
        $hasAdmin = (int) row('SELECT COUNT(*) n FROM admin')['n'] > 0;
    } catch (PDOException $e) {
        // tabel belum ada
    }
    if ($hasAdmin) {
        forbidden('Setup sudah pernah dijalankan. Masuk lewat halaman login panitia.');
    }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!hash_equals((string) config('setup_key'), post('setup_key'))) {
                throw new UserError('Kunci setup salah.');
            }
            [$nama, $user, $pass] = [post('nama'), strtolower(post('username')), (string) ($_POST['password'] ?? '')];
            if ($nama === '' || !preg_match('/^[a-z0-9._-]{3,60}$/', $user)) {
                throw new UserError('Isi nama, dan username 3–60 karakter (huruf kecil, angka, titik, strip).');
            }
            if (mb_strlen($pass) < 10) {
                throw new UserError('Password minimal 10 karakter.');
            }
            migrate();
            q('INSERT INTO admin (nama, username, password_hash) VALUES (?, ?, ?)', [$nama, $user, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) db()->lastInsertId();
            flash('ok', 'Setup selesai. Periksa pengaturan kegiatan sebelum pendaftaran dibuka.');
            redirect('/admin/pengaturan');
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    render('admin/setup', 'Setup', ['error' => $error], ['narrow' => true]);
}

function admin_login(): void
{
    if (current_admin()) {
        redirect('/admin');
    }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (login_blocked()) {
            $error = 'Terlalu banyak percobaan gagal. Coba lagi 15 menit lagi.';
        } else {
            $a = row('SELECT * FROM admin WHERE username = ?', [strtolower(post('username'))]);
            if ($a && password_verify((string) ($_POST['password'] ?? ''), $a['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $a['id'];
                redirect('/admin');
            }
            login_failed();
            $error = 'Username atau password salah.';
        }
    }
    render('admin/login', 'Login panitia', ['error' => $error], ['narrow' => true]);
}

function admin_keluar(): void
{
    unset($_SESSION['admin_id'], $_SESSION['instansi_id']);
    redirect('/admin/login');
}

function admin_dashboard(): void
{
    require_admin();
    $perHari = [];
    foreach (event_dates() as $d) {
        $perHari[$d] = row(
            "SELECT COUNT(DISTINCT instansi_id) instansi, COUNT(*) peserta,
                    SUM(menginap) menginap, SUM(menginap AND jk = 'L') menginap_l, SUM(menginap AND jk = 'P') menginap_p
             FROM peserta WHERE tanggal = ? AND aktif = 1",
            [$d]
        );
    }
    $reimburse = [];
    foreach (STATUS_REIMBURSE as $k => $label) {
        $reimburse[$k] = row(
            "SELECT COUNT(*) n, COALESCE(SUM(t.total), 0) total, COALESCE(SUM(t.disetujui), 0) disetujui FROM instansi i
             LEFT JOIN (SELECT instansi_id, SUM(nominal) total, SUM(CASE WHEN status = 'disetujui' THEN COALESCE(nominal_disetujui, nominal) ELSE 0 END) disetujui
                        FROM biaya GROUP BY instansi_id) t ON t.instansi_id = i.id
             WHERE i.reimburse_status = ?",
            [$k]
        );
    }
    $foto = row('SELECT COUNT(*) n, SUM(exif_ok = 0) tanpa_exif FROM evidence');
    $terbaru = rows('SELECT r.*, i.nama FROM riwayat r JOIN instansi i ON i.id = r.instansi_id ORDER BY r.id DESC LIMIT 12');
    render('admin/dashboard', 'Dashboard panitia', compact('perHari', 'reimburse', 'foto', 'terbaru'), ['admin' => true]);
}

function admin_instansi_list(): void
{
    require_admin();
    $where = ['1=1'];
    $params = [];
    $cari = trim((string) ($_GET['q'] ?? ''));
    if ($cari !== '') {
        $where[] = '(i.nama LIKE ? OR i.kabkota LIKE ? OR i.koordinator LIKE ? OR i.id = ?)';
        array_push($params, "%$cari%", "%$cari%", "%$cari%", (int) preg_replace('/\D/', '', $cari));
    }
    $tanggal = (string) ($_GET['tanggal'] ?? '');
    if (in_array($tanggal, event_dates(), true)) {
        $where[] = 'EXISTS (SELECT 1 FROM peserta p WHERE p.instansi_id = i.id AND p.aktif = 1 AND p.tanggal = ?)';
        $params[] = $tanggal;
    }
    $status = (string) ($_GET['status'] ?? '');
    if (isset(STATUS_REIMBURSE[$status])) {
        $where[] = 'i.reimburse_status = ?';
        $params[] = $status;
    }
    $list = rows(
        'SELECT i.*,
            (SELECT GROUP_CONCAT(CONCAT(p.tanggal, ":", c) ORDER BY p.tanggal) FROM (SELECT instansi_id, tanggal, COUNT(*) c FROM peserta WHERE aktif = 1 GROUP BY instansi_id, tanggal) p WHERE p.instansi_id = i.id) hari,
            (SELECT COUNT(*) FROM peserta WHERE instansi_id = i.id AND aktif = 1 AND menginap = 1) menginap,
            (SELECT COUNT(*) FROM evidence WHERE instansi_id = i.id) foto,
            (SELECT COALESCE(SUM(nominal), 0) FROM biaya WHERE instansi_id = i.id) total_biaya
         FROM instansi i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC',
        $params
    );
    render('admin/instansi_list', 'Instansi', compact('list', 'cari', 'tanggal', 'status'), ['admin' => true]);
}

function admin_find_instansi(string $id): array
{
    require_admin();
    $inst = row('SELECT * FROM instansi WHERE id = ?', [(int) $id]);
    if (!$inst) {
        not_found();
    }
    return $inst;
}

function admin_instansi_detail(string $id): void
{
    $inst = admin_find_instansi($id);
    $foto = [];
    foreach (rows('SELECT * FROM evidence WHERE instansi_id = ? ORDER BY tanggal, uploaded_at', [$inst['id']]) as $f) {
        $foto[$f['tanggal']][$f['tahap']][] = $f;
    }
    render('admin/instansi_detail', $inst['nama'], [
        'inst' => $inst,
        'byDate' => peserta_by_date((int) $inst['id']),
        'foto' => $foto,
        'biaya' => rows('SELECT * FROM biaya WHERE instansi_id = ? ORDER BY tanggal, id', [$inst['id']]),
        'riwayat' => rows('SELECT * FROM riwayat WHERE instansi_id = ? ORDER BY id DESC', [$inst['id']]),
        'newLink' => $_SESSION['admin_new_link'][$inst['id']] ?? null,
    ], ['admin' => true]);
    unset($_SESSION['admin_new_link'][$inst['id']]);
}

function admin_instansi_link(string $id): void
{
    $inst = admin_find_instansi($id);
    [$token, $hash] = new_token();
    q('UPDATE instansi SET token_hash = ? WHERE id = ?', [$hash, $inst['id']]);
    log_riwayat((int) $inst['id'], 'link_baru', 'Link instansi diterbitkan ulang; link lama tidak berlaku.');
    $mailed = mail_link_instansi($inst, $token);
    $_SESSION['admin_new_link'][$inst['id']] = ['link' => instansi_link($token), 'mailed' => $mailed];
    redirect('/admin/instansi/' . $inst['id']);
}

function admin_instansi_buka(string $id): void
{
    $inst = admin_find_instansi($id);
    $_SESSION['instansi_id'] = (int) $inst['id'];
    redirect('/saya');
}

function admin_instansi_reimburse(string $id): void
{
    $inst = admin_find_instansi($id);
    $status = post('status');
    if (!isset(STATUS_REIMBURSE[$status])) {
        throw new UserError('Status tidak valid.');
    }
    if ($status === 'diverifikasi') {
        $pending = (int) row("SELECT COUNT(*) n FROM biaya WHERE instansi_id = ? AND status = 'menunggu'", [$inst['id']])['n'];
        if ($pending > 0) {
            throw new UserError("Masih ada $pending bukti yang belum diperiksa.");
        }
    }
    q('UPDATE instansi SET reimburse_status = ?, catatan_panitia = ?, dibayar_at = ? WHERE id = ?', [
        $status, post('catatan') ?: null, $status === 'dibayar' ? date('Y-m-d H:i:s') : null, $inst['id'],
    ]);
    log_riwayat((int) $inst['id'], 'status', 'Status reimburse: ' . STATUS_REIMBURSE[$status] . (post('catatan') ? ' (' . post('catatan') . ')' : ''));
    flash('ok', 'Status reimburse: ' . STATUS_REIMBURSE[$status] . '.');
    redirect('/admin/instansi/' . $inst['id'] . '#reimburse');
}

function admin_biaya(string $id): void
{
    require_admin();
    $b = row('SELECT * FROM biaya WHERE id = ?', [(int) $id]);
    if (!$b) {
        not_found();
    }
    $status = post('status');
    if (!in_array($status, ['menunggu', 'disetujui', 'ditolak'], true)) {
        throw new UserError('Status tidak valid.');
    }
    $nominal = post('nominal_disetujui') === '' ? null : (int) preg_replace('/[^0-9]/', '', post('nominal_disetujui'));
    if ($status === 'ditolak' && post('catatan') === '') {
        throw new UserError('Tulis alasan penolakan agar instansi tahu apa yang harus diperbaiki.');
    }
    q('UPDATE biaya SET status = ?, nominal_disetujui = ?, catatan = ? WHERE id = ?', [
        $status, $status === 'disetujui' ? ($nominal ?? (int) $b['nominal']) : null, post('catatan') ?: null, $b['id'],
    ]);
    log_riwayat((int) $b['instansi_id'], 'verifikasi', JENIS_BIAYA[$b['jenis']] . ' ' . rupiah($b['nominal']) . ': ' . $status . (post('catatan') ? ' (' . post('catatan') . ')' : ''));
    redirect('/admin/instansi/' . $b['instansi_id'] . '#reimburse');
}

function admin_instansi_hapus(string $id): void
{
    $inst = admin_find_instansi($id);
    if (strtoupper(post('konfirmasi')) !== kode_instansi((int) $inst['id'])) {
        throw new UserError('Ketik kode instansi ' . kode_instansi((int) $inst['id']) . ' untuk konfirmasi.');
    }
    $files = array_merge(
        array_column(rows('SELECT file FROM evidence WHERE instansi_id = ?', [$inst['id']]), 'file'),
        array_column(rows('SELECT file FROM biaya WHERE instansi_id = ?', [$inst['id']]), 'file')
    );
    q('DELETE FROM instansi WHERE id = ?', [$inst['id']]);
    foreach ($files as $f) {
        delete_upload($f);
    }
    flash('ok', $inst['nama'] . ' dihapus beserta seluruh data dan filenya.');
    redirect('/admin/instansi');
}

function admin_evidence_zip(string $id): void
{
    $inst = admin_find_instansi($id);
    if (!class_exists('ZipArchive')) {
        throw new UserError('Ekstensi ZIP tidak tersedia di server.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'kcz');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $base = kode_instansi((int) $inst['id']) . '-' . slug($inst['nama']);
    $csv = [['Jenis', 'Tanggal', 'Tahap/Jenis', 'Waktu foto', 'Diunggah', 'Lat', 'Lon', 'Nominal', 'Status', 'File']];
    $n = [];
    foreach (rows('SELECT * FROM evidence WHERE instansi_id = ? ORDER BY tanggal, FIELD(tahap, "berangkat", "tiba", "kegiatan", "pulang", "tiba_kembali"), uploaded_at', [$inst['id']]) as $f) {
        $key = $f['tanggal'] . $f['tahap'];
        $n[$key] = ($n[$key] ?? 0) + 1;
        $name = "foto/{$f['tanggal']}/" . array_search($f['tahap'], array_keys(TAHAP), true) + 1 . "-{$f['tahap']}-{$n[$key]}.jpg";
        if (is_file(file_path($f['file']))) {
            $zip->addFile(file_path($f['file']), "$base/$name");
        }
        $csv[] = ['Foto', $f['tanggal'], TAHAP[$f['tahap']], $f['taken_at'] ?? 'tanpa timestamp kamera', $f['uploaded_at'], $f['lat'], $f['lon'], '', '', $name];
    }
    foreach (rows('SELECT * FROM biaya WHERE instansi_id = ? ORDER BY tanggal, id', [$inst['id']]) as $i => $b) {
        $ext = $b['mime'] === 'application/pdf' ? 'pdf' : 'jpg';
        $name = 'biaya/' . ($i + 1) . "-{$b['jenis']}-{$b['nominal']}.$ext";
        if (is_file(file_path($b['file']))) {
            $zip->addFile(file_path($b['file']), "$base/$name");
        }
        $csv[] = ['Biaya', $b['tanggal'], JENIS_BIAYA[$b['jenis']] . ($b['keterangan'] ? ': ' . $b['keterangan'] : ''), '', $b['created_at'], '', '', $b['nominal'], $b['status'], $name];
    }
    $zip->addFromString("$base/daftar-file.csv", csv_string($csv));
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $base . '.zip"');
    readfile($tmp);
    unlink($tmp);
}

function csv_string(array $rows): string
{
    $fh = fopen('php://temp', 'w+');
    fwrite($fh, "\xEF\xBB\xBF");
    foreach ($rows as $r) {
        fputcsv($fh, array_map(static fn ($v) => $v === null ? '' : (string) $v, $r), ';', '"', '');
    }
    rewind($fh);
    $s = stream_get_contents($fh);
    fclose($fh);
    return (string) $s;
}

function admin_ekspor(string $jenis): void
{
    require_admin();
    $tanggal = (string) ($_GET['tanggal'] ?? '');
    $filterTgl = in_array($tanggal, event_dates(), true);
    $data = [];
    if ($jenis === 'peserta' || $jenis === 'menginap') {
        $data[] = $jenis === 'peserta'
            ? ['Tanggal', 'Kode', 'Instansi', 'Kab/Kota', 'Nama', 'NIP', 'Jabatan', 'HP', 'JK', 'Menginap', 'Koordinator', 'HP koordinator']
            : ['Malam', 'Tanggal acara', 'Kode', 'Instansi', 'Nama', 'JK', 'HP'];
        $sql = 'SELECT p.*, i.nama instansi, i.kabkota, i.koordinator, i.hp hp_koor FROM peserta p JOIN instansi i ON i.id = p.instansi_id
                WHERE p.aktif = 1' . ($jenis === 'menginap' ? ' AND p.menginap = 1' : '') . ($filterTgl ? ' AND p.tanggal = ?' : '') .
               ' ORDER BY p.tanggal, ' . ($jenis === 'menginap' ? 'p.jk, ' : '') . 'i.nama, p.id';
        foreach (rows($sql, $filterTgl ? [$tanggal] : []) as $p) {
            $data[] = $jenis === 'peserta'
                ? [$p['tanggal'], kode_instansi((int) $p['instansi_id']), $p['instansi'], $p['kabkota'], $p['nama'], $p['nip'], $p['jabatan'], $p['hp'], $p['jk'], $p['menginap'] ? 'Ya' : 'Tidak', $p['koordinator'], $p['hp_koor']]
                : [malam_menginap($p['tanggal']), $p['tanggal'], kode_instansi((int) $p['instansi_id']), $p['instansi'], $p['nama'], $p['jk'], $p['hp']];
        }
    } else {
        $data[] = ['Kode', 'Instansi', 'Kab/Kota', 'Koordinator', 'HP', 'Status', 'Jumlah bukti', 'Total diajukan', 'Total disetujui', 'Bank', 'No. rekening', 'Atas nama', 'Diajukan', 'Dibayar', 'Catatan panitia'];
        foreach (rows(
            "SELECT i.*, COUNT(b.id) n, COALESCE(SUM(b.nominal), 0) total,
                    COALESCE(SUM(CASE WHEN b.status = 'disetujui' THEN COALESCE(b.nominal_disetujui, b.nominal) ELSE 0 END), 0) disetujui
             FROM instansi i LEFT JOIN biaya b ON b.instansi_id = i.id GROUP BY i.id ORDER BY i.nama"
        ) as $i) {
            $data[] = [kode_instansi((int) $i['id']), $i['nama'], $i['kabkota'], $i['koordinator'], $i['hp'], STATUS_REIMBURSE[$i['reimburse_status']], $i['n'], $i['total'], $i['disetujui'], $i['bank'], $i['no_rek'] ? "'" . $i['no_rek'] : '', $i['atas_nama'], $i['diajukan_at'], $i['dibayar_at'], $i['catatan_panitia']];
        }
    }
    $name = "kalcer-$jenis" . ($filterTgl ? "-$tanggal" : '') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo csv_string($data);
}

const SETTING_FIELDS = [
    'nama_kegiatan' => 'Nama kegiatan',
    'lokasi' => 'Lokasi kegiatan',
    'tanggal_acara' => 'Tanggal acara',
    'daftar_dibuka' => 'Pendaftaran dibuka',
    'batas_daftar' => 'Batas pendaftaran',
    'batas_ganti' => 'Batas perubahan peserta',
    'batas_upload' => 'Batas upload foto & bukti biaya',
    'kuota_per_instansi' => 'Maks. peserta per instansi per hari',
    'kuota_per_hari' => 'Kuota peserta per hari (0 = tanpa batas)',
    'info_hotel' => 'Info hotel',
    'info_shuttle' => 'Info shuttle',
    'kontak_panitia' => 'Kontak panitia',
];

function admin_pengaturan(): void
{
    $admin = require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_keys(SETTING_FIELDS) as $k) {
            $v = $k === 'daftar_dibuka' ? (isset($_POST[$k]) ? '1' : '0') : post($k);
            if (in_array($k, ['batas_daftar', 'batas_ganti', 'batas_upload'], true) && $v !== '') {
                $t = strtotime($v);
                if (!$t) {
                    throw new UserError(SETTING_FIELDS[$k] . ' tidak valid.');
                }
                $v = date('Y-m-d H:i', $t);
            }
            if ($k === 'tanggal_acara') {
                $ds = array_filter(array_map('trim', explode(',', $v)));
                foreach ($ds as $d) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
                        throw new UserError('Tanggal acara harus berformat YYYY-MM-DD, dipisah koma.');
                    }
                }
                $v = implode(',', $ds);
            }
            if (in_array($k, ['kuota_per_instansi', 'kuota_per_hari'], true)) {
                $v = (string) max(0, (int) $v);
            }
            set_setting($k, $v);
        }
        flash('ok', 'Pengaturan disimpan.');
        redirect('/admin/pengaturan');
    }
    render('admin/pengaturan', 'Pengaturan', [
        'admins' => rows('SELECT id, nama, username, created_at FROM admin ORDER BY id'),
        'me' => $admin,
    ], ['admin' => true]);
}

function admin_akun(): void
{
    $admin = require_admin();
    $aksi = post('aksi');
    if ($aksi === 'tambah') {
        [$nama, $user, $pass] = [post('nama'), strtolower(post('username')), (string) ($_POST['password'] ?? '')];
        if ($nama === '' || !preg_match('/^[a-z0-9._-]{3,60}$/', $user) || mb_strlen($pass) < 10) {
            throw new UserError('Isi nama, username (3–60 karakter: huruf kecil/angka/titik/strip), dan password minimal 10 karakter.');
        }
        if (row('SELECT id FROM admin WHERE username = ?', [$user])) {
            throw new UserError('Username sudah dipakai.');
        }
        q('INSERT INTO admin (nama, username, password_hash) VALUES (?, ?, ?)', [$nama, $user, password_hash($pass, PASSWORD_DEFAULT)]);
        flash('ok', "Akun panitia $user ditambahkan.");
    } elseif ($aksi === 'password') {
        $me = row('SELECT * FROM admin WHERE id = ?', [$admin['id']]);
        if (!password_verify((string) ($_POST['lama'] ?? ''), $me['password_hash'])) {
            throw new UserError('Password lama salah.');
        }
        $baru = (string) ($_POST['baru'] ?? '');
        if (mb_strlen($baru) < 10) {
            throw new UserError('Password baru minimal 10 karakter.');
        }
        q('UPDATE admin SET password_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $admin['id']]);
        flash('ok', 'Password diganti.');
    } elseif ($aksi === 'hapus') {
        $id = (int) post('id');
        if ($id === (int) $admin['id']) {
            throw new UserError('Tidak bisa menghapus akun sendiri.');
        }
        q('DELETE FROM admin WHERE id = ?', [$id]);
        flash('ok', 'Akun panitia dihapus.');
    }
    redirect('/admin/pengaturan#akun');
}
