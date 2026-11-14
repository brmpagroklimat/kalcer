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
    render('publik/home', 'Pendaftaran', [
        'old' => $old,
        'errors' => $errors,
        'dates' => event_dates(),
        'open' => setting('daftar_dibuka') === '1' && before('batas_daftar'),
        'kuota' => (int) setting('kuota_per_instansi'),
        'stamp' => form_stamp(),
    ]);
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
    $d['hp'] = normalize_hp($d['hp']);
    $d['nip'] = preg_replace('/\s+/', '', $d['nip']);
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
        throw new UserError('Pendaftaran sudah ditutup.');
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
        foreach (['nama' => 'Nama instansi', 'kabkota' => 'Kabupaten/kota', 'koordinator' => 'Nama koordinator', 'hp' => 'HP/WA koordinator'] as $k => $label) {
            if ($inst[$k] === '') {
                throw new UserError("$label wajib diisi.");
            }
        }
        if ($inst['email'] !== '' && !filter_var($inst['email'], FILTER_VALIDATE_EMAIL)) {
            throw new UserError('Format email koordinator tidak valid.');
        }
        if (row('SELECT id FROM instansi WHERE LOWER(nama) = LOWER(?) AND LOWER(kabkota) = LOWER(?)', [$inst['nama'], $inst['kabkota']])) {
            throw new UserError('Instansi ini sudah terdaftar. Gunakan link instansi yang sudah dikirim, atau hubungi panitia untuk meminta link baru.');
        }

        $dates = event_dates();
        $ikut = array_values(array_intersect($dates, (array) ($_POST['ikut'] ?? [])));
        if (!$ikut) {
            throw new UserError('Pilih minimal satu tanggal kegiatan.');
        }
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

        [$token, $hash] = new_token();
        db()->beginTransaction();
        q('INSERT INTO instansi (nama, kabkota, koordinator, hp, email, token_hash) VALUES (?, ?, ?, ?, ?, ?)', [
            $inst['nama'], $inst['kabkota'], $inst['koordinator'], $inst['hp'], $inst['email'] ?: null, $hash,
        ]);
        $id = (int) db()->lastInsertId();
        foreach ($semua as $p) {
            q('INSERT INTO peserta (instansi_id, tanggal, nama, nip, jabatan, hp, jk, menginap) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $p['tanggal'], $p['nama'], $p['nip'] ?: null, $p['jabatan'] ?: null, $p['hp'], $p['jk'], $p['menginap'],
            ]);
        }
        log_riwayat($id, 'daftar', 'Mendaftar dengan ' . count($semua) . ' peserta: ' . implode(', ', array_column($semua, 'nama')));
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
    render('publik/terdaftar', 'Pendaftaran berhasil', ['inst' => $inst, 'link' => instansi_link($new['token']), 'mailed' => $new['mailed']], ['narrow' => true]);
}

function publik_masuk_token(string $token): void
{
    $inst = row('SELECT id FROM instansi WHERE token_hash = ?', [hash('sha256', $token)]);
    if (!$inst) {
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
    $table = $kind === 'e' ? 'evidence' : 'biaya';
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
