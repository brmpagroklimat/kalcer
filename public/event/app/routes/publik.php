<?php
// Halaman publik: pendaftaran instansi + peserta, dan masuk lewat link token.
declare(strict_types=1);

const FIELD_PESERTA = ['nama', 'nip', 'jabatan', 'hp', 'jk', 'menginap'];

function form_stamp(): string
{
    $t = (string) time();
    return $t . '.' . hash_hmac('sha256', $t, (string) config('setup_key'));
}

function form_stamp_ok(string $s): bool
{
    [$t, $sig] = array_pad(explode('.', $s, 2), 2, '');
    return hash_equals(hash_hmac('sha256', $t, (string) config('setup_key')), $sig)
        && time() - (int) $t >= 3;
}

function publik_home(array $old = [], array $errors = []): void
{
    render('publik/home', 'Registrasi', [
        'old' => $old,
        'errors' => $errors,
        'dates' => event_dates(),
        'open' => setting('daftar_dibuka') === '1' && before('batas_daftar'),
        'kuota' => (int) setting('kuota_per_instansi'),
        'stamp' => form_stamp(),
    ], ['nav' => 'daftar']);
}

/** Validasi satu baris peserta. Mengembalikan data bersih atau melempar UserError. */
function clean_peserta(array $p, string $label): array
{
    $d = [];
    foreach (FIELD_PESERTA as $k) {
        $d[$k] = trim((string) ($p[$k] ?? ''));
    }
    if ($d['nama'] === '' || mb_strlen($d['nama']) > 150) {
        throw new UserError("$label: nama wajib diisi.");
    }
    if (!in_array($d['jk'], ['L', 'P'], true)) {
        throw new UserError("$label: pilih jenis kelamin.");
    }
    if (!in_array($d['menginap'], ['ya', 'tidak'], true)) {
        throw new UserError("$label: pilih menginap atau tidak.");
    }
    if ($d['hp'] === '') {
        throw new UserError("$label: nomor HP/WA wajib diisi.");
    }
    $hp = valid_hp($d['hp']);
    if ($hp === null) {
        throw new UserError("$label: nomor HP/WA hanya angka, 10–15 digit, diawali 0 atau 62 (mis. 081234567890).");
    }
    $d['hp'] = $hp;
    $d['nip'] = preg_replace('/\s+/', '', $d['nip']);
    if ($d['nip'] !== '' && !valid_nip($d['nip'])) {
        throw new UserError("$label: NIP harus 18 digit angka (kosongkan bila tidak punya NIP).");
    }
    $d['bagikan_kontak'] = !empty($p['bagikan_kontak']) && $d['menginap'] ? 1 : 0;
    $d['menginap'] = $d['menginap'] === 'ya' ? 1 : 0;
    return $d;
}

function check_kuota(int $instansiId, string $tanggal, int $tambah): void
{
    $perInst = (int) setting('kuota_per_instansi');
    if ($perInst > 0) {
        $n = (int) row('SELECT COUNT(*) n FROM peserta WHERE instansi_id = ? AND tanggal = ? AND aktif = 1', [$instansiId, $tanggal])['n'];
        if ($n + $tambah > $perInst) {
            throw new UserError('Maksimal ' . $perInst . ' peserta per instansi untuk tanggal ' . tgl($tanggal) . '.');
        }
    }
    $perHari = (int) setting('kuota_per_hari');
    if ($perHari > 0) {
        $n = (int) row('SELECT COUNT(*) n FROM peserta WHERE tanggal = ? AND aktif = 1', [$tanggal])['n'];
        if ($n + $tambah > $perHari) {
            throw new UserError('Kuota peserta tanggal ' . tgl($tanggal) . ' sudah penuh. Hubungi panitia.');
        }
    }
}

function publik_daftar(): void
{
    if (!(setting('daftar_dibuka') === '1' && before('batas_daftar'))) {
        throw new UserError('Registrasi sudah ditutup.');
    }
    $old = $_POST;
    $errors = [];
    try {
        if (post('website') !== '' || !form_stamp_ok(post('_stamp'))) {
            throw new UserError('Formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $inst = [
            'nama' => post('nama'),
            'kabkota' => post('kabkota'),
            'koordinator' => post('koordinator'),
            'hp' => normalize_hp(post('hp')),
            'email' => post('email'),
        ];
        foreach (['nama' => 'Nama instansi', 'kabkota' => 'Kabupaten/kota', 'koordinator' => 'Nama PIC instansi', 'hp' => 'HP/WA PIC instansi'] as $k => $label) {
            if ($inst[$k] === '') {
                throw new UserError("$label wajib diisi.");
            }
        }
        if (!valid_kabkota($inst['kabkota'])) {
            throw new UserError('Pilih kabupaten/kota dari daftar (ketik minimal 3 huruf, lalu pilih).');
        }
        if (valid_hp($inst['hp']) === null) {
            throw new UserError('No. HP/WA PIC instansi hanya angka, 10–15 digit, diawali 0 atau 62.');
        }
        $inst['hp'] = valid_hp($inst['hp']);
        if ($inst['email'] !== '' && !filter_var($inst['email'], FILTER_VALIDATE_EMAIL)) {
            throw new UserError('Format email PIC instansi tidak valid.');
        }
        if (row('SELECT id FROM instansi WHERE LOWER(nama) = LOWER(?) AND LOWER(kabkota) = LOWER(?)', [$inst['nama'], $inst['kabkota']])) {
            throw new UserError('Instansi ini sudah teregistrasi. Gunakan link instansi yang sudah dikirim, atau hubungi panitia untuk meminta link baru.');
        }

        // Satu instansi hanya ikut satu tanggal.
        $tanggal = post('ikut');
        if (!in_array($tanggal, event_dates(), true)) {
            throw new UserError('Pilih tanggal kegiatan.');
        }
        $ikut = [$tanggal];
        $semua = [];
        foreach ($ikut as $tgl) {
            $baris = array_filter((array) ($_POST['peserta'][$tgl] ?? []), static fn ($p) => is_array($p) && trim((string) ($p['nama'] ?? '')) !== '');
            if (!$baris) {
                throw new UserError('Isi minimal satu peserta untuk tanggal ' . tgl($tgl) . '.');
            }
            $i = 0;
            foreach ($baris as $p) {
                $semua[] = ['tanggal' => $tgl] + clean_peserta($p, 'Peserta ' . (++$i) . ' (' . tgl_pendek($tgl) . ')');
            }
        }
        foreach ($ikut as $tgl) {
            check_kuota(0, $tgl, count(array_filter($semua, static fn ($p) => $p['tanggal'] === $tgl)));
        }

        [$token, $hash] = new_token($inst['nama']);
        db()->beginTransaction();
        q('INSERT INTO instansi (nama, kabkota, koordinator, hp, email, token_hash) VALUES (?, ?, ?, ?, ?, ?)', [
            $inst['nama'], $inst['kabkota'], $inst['koordinator'], $inst['hp'], $inst['email'] ?: null, $hash,
        ]);
        $id = (int) db()->lastInsertId();
        foreach ($semua as $p) {
            q('INSERT INTO peserta (instansi_id, tanggal, nama, nip, jabatan, hp, jk, menginap, bagikan_kontak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $p['tanggal'], $p['nama'], $p['nip'] ?: null, $p['jabatan'] ?: null, $p['hp'], $p['jk'], $p['menginap'], $p['bagikan_kontak'],
            ]);
        }
        log_riwayat($id, 'daftar', 'Registrasi dengan ' . count($semua) . ' peserta: ' . implode(', ', array_column($semua, 'nama')));
        db()->commit();

        $instRow = row('SELECT * FROM instansi WHERE id = ?', [$id]);
        $mailed = mail_link_instansi($instRow, $token);
        session_regenerate_id(true);
        $_SESSION['instansi_id'] = $id;
        $_SESSION['new_token'] = ['token' => $token, 'mailed' => $mailed];
        redirect('/terdaftar');
    } catch (UserError $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $errors[] = $e->getMessage();
    }
    publik_home($old, $errors);
}

function publik_terdaftar(): void
{
    $new = $_SESSION['new_token'] ?? null;
    $inst = current_instansi();
    if (!$new || !$inst) {
        redirect('/saya');
    }
    unset($_SESSION['new_token']);
    render('publik/terdaftar', 'Registrasi berhasil', ['inst' => $inst, 'link' => instansi_link($new['token']), 'mailed' => $new['mailed']]);
}

function publik_masuk_token(string $token): void
{
    if (login_blocked()) {
        http_response_code(429);
        render_page('Terlalu banyak percobaan', '<h1>Terlalu banyak percobaan</h1><p>Terlalu banyak link yang salah dari perangkat ini. Coba lagi 15 menit lagi, atau ' . hubungi_panitia('', 'hubungi panitia via WhatsApp') . '.</p>', ['narrow' => true]);
        return;
    }
    $inst = find_instansi_by_token($token);
    if (!$inst) {
        login_failed();
        http_response_code(404);
        render_page('Link tidak berlaku', view('publik/link_mati'), ['narrow' => true]);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['instansi_id'] = (int) $inst['id'];
    redirect('/saya');
}

function publik_keluar(): void
{
    unset($_SESSION['instansi_id']);
    redirect(current_admin() ? '/admin' : '/');
}

function serve_upload(string $kind, string $id): void
{
    $table = ['e' => 'evidence', 'b' => 'biaya', 't' => 'pembayaran'][$kind];
    $f = row("SELECT * FROM $table WHERE id = ?", [(int) $id]);
    if (!$f) {
        not_found();
    }
    $inst = current_instansi();
    if (!current_admin() && (!$inst || (int) $inst['id'] !== (int) $f['instansi_id'])) {
        forbidden();
    }
    $ext = $f['mime'] === 'application/pdf' ? 'pdf' : 'jpg';
    send_file($f['file'], $f['mime'], kode_instansi((int) $f['instansi_id']) . "-$kind$id.$ext");
}
