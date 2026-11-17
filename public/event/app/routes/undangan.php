<?php
// Daftar undangan: panitia menetapkan instansi mana ikut tanggal berapa, sebelum registrasi.
// Saat daftar terisi, formulir registrasi berubah jadi pilihan nama instansi (tanggal ikut otomatis).
declare(strict_types=1);

/**
 * Cocokkan teks kab/kota bebas ke daftar resmi, mis. "kabupaten bogor" → "Kab. Bogor, Jawa Barat".
 * Mengembalikan null bila tidak ketemu atau ambigu (mis. "Bogor": Kab. atau Kota?).
 */
function cari_kabkota(string $v): ?string
{
    $v = trim(preg_replace('/\s+/', ' ', $v));
    if ($v === '') {
        return null;
    }
    $norm = static function (string $s): string {
        $s = mb_strtolower($s);
        $s = preg_replace('/^kabupaten\s+/u', 'kab. ', $s);
        $s = preg_replace('/^kab\s+/u', 'kab. ', $s);
        return trim(preg_replace('/\s*,\s*/', ', ', $s));
    };
    $cari = $norm($v);
    $hasil = [];
    foreach (kabkota_list() as $k) {
        $nk = $norm($k);
        if ($nk === $cari) {
            return $k;
        }
        // Tanpa provinsi: "Kab. Bogor" cocok dengan "Kab. Bogor, Jawa Barat".
        if (str_starts_with($nk, $cari . ', ')) {
            $hasil[] = $k;
        }
    }
    return count($hasil) === 1 ? $hasil[0] : null;
}

function admin_undangan(): void
{
    require_admin();
    $list = rows(
        'SELECT u.*, i.id inst_id,
            (SELECT COUNT(*) FROM peserta p WHERE p.instansi_id = u.instansi_id AND p.aktif = 1) peserta,
            (SELECT COUNT(*) FROM evidence e WHERE e.instansi_id = u.instansi_id) foto
         FROM undangan u LEFT JOIN instansi i ON i.id = u.instansi_id ORDER BY u.tanggal, u.nama'
    );
    $perTanggal = array_fill_keys(event_dates(), []);
    foreach ($list as $u) {
        $perTanggal[$u['tanggal']][] = $u;
    }
    // Instansi yang registrasi sebelum daftar undangan dipakai (tidak terhubung ke undangan).
    $lepas = rows('SELECT i.id, i.nama, i.kabkota FROM instansi i LEFT JOIN undangan u ON u.instansi_id = i.id WHERE u.id IS NULL ORDER BY i.nama');
    render('admin/undangan', 'Undangan & tanggal', compact('perTanggal', 'lepas'), ['admin' => true]);
}

function admin_undangan_tambah(): void
{
    require_admin();
    $tanggal = post('tanggal');
    if (!in_array($tanggal, event_dates(), true)) {
        throw new UserError('Pilih tanggal.');
    }
    // Satu per satu (nama + kab/kota) atau banyak sekaligus (tempel dari Excel: Nama [Tab atau ;] Kab/kota).
    $baris = [];
    if (post('banyak') !== '') {
        foreach (preg_split('/\R/', post('banyak')) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $kol = array_map('trim', preg_split('/\t|;/', $line));
            $baris[] = [$n + 1, $kol[0] ?? '', $kol[1] ?? '', $line];
        }
    } else {
        $baris[] = [0, post('nama'), post('kabkota'), ''];
    }
    if (!$baris) {
        throw new UserError('Isi nama instansi.');
    }
    $masuk = 0;
    $gagal = [];
    $sisa = [];
    foreach ($baris as [$no, $nama, $kab, $asli]) {
        $label = $no ? "Baris $no ($nama)" : $nama;
        $kabResmi = cari_kabkota($kab);
        if ($nama === '' || mb_strlen($nama) > 200) {
            $gagal[] = ($no ? "Baris $no" : 'Nama') . ': nama instansi kosong atau terlalu panjang.';
            $sisa[] = $asli;
            continue;
        }
        if ($kabResmi === null) {
            $gagal[] = "$label: kabupaten/kota \"$kab\" tidak dikenali" . ($kab !== '' ? ' atau ambigu (tulis mis. "Kab. Bogor" atau "Kota Bogor")' : '') . '.';
            $sisa[] = $asli;
            continue;
        }
        if ($ada = row('SELECT tanggal FROM undangan WHERE nama = ? AND kabkota = ?', [$nama, $kabResmi])) {
            $gagal[] = "$label: sudah ada di daftar (" . tgl($ada['tanggal']) . ').';
            $sisa[] = $asli;
            continue;
        }
        // Instansi yang sudah registrasi sebelumnya dengan nama yang sama langsung dihubungkan.
        $inst = row('SELECT i.id FROM instansi i LEFT JOIN undangan u ON u.instansi_id = i.id WHERE u.id IS NULL AND i.nama = ? AND i.kabkota = ?', [$nama, $kabResmi]);
        if ($inst && ($t = instansi_tanggal((int) $inst['id'])) && $t !== $tanggal) {
            $gagal[] = "$label: sudah registrasi untuk " . tgl($t) . '. Pilih tanggal itu, atau pindahkan setelah masuk daftar.';
            $sisa[] = $asli;
            continue;
        }
        q('INSERT INTO undangan (nama, kabkota, tanggal, instansi_id) VALUES (?, ?, ?, ?)', [$nama, $kabResmi, $tanggal, $inst['id'] ?? null]);
        $masuk++;
    }
    if ($masuk) {
        flash('ok', "$masuk instansi ditambahkan ke " . tgl($tanggal) . '.');
    }
    foreach (array_slice($gagal, 0, 12) as $g) {
        flash('error', $g);
    }
    if (count($gagal) > 12) {
        flash('error', 'dan ' . (count($gagal) - 12) . ' baris lain tidak bisa ditambahkan.');
    }
    if ($sisa && post('banyak') !== '') {
        // Baris yang gagal dikembalikan ke kotak isian supaya tinggal diperbaiki.
        $_SESSION['undangan_sisa'] = ['tanggal' => $tanggal, 'teks' => implode("\n", $sisa)];
    }
    redirect('/admin/undangan#tambah');
}

/** Pindah tanggal. Bila instansi sudah registrasi, pesertanya ikut pindah. */
function admin_undangan_ubah(string $id): void
{
    require_admin();
    $u = row('SELECT * FROM undangan WHERE id = ?', [(int) $id]) ?? not_found();
    $tanggal = post('tanggal');
    if (!in_array($tanggal, event_dates(), true)) {
        throw new UserError('Pilih tanggal.');
    }
    if (!$u['instansi_id']) {
        q('UPDATE undangan SET tanggal = ? WHERE id = ?', [$tanggal, $u['id']]);
        flash('ok', $u['nama'] . ' dipindah ke ' . tgl($tanggal) . '.');
        redirect('/admin/undangan');
    }
    if ($tanggal === $u['tanggal']) {
        redirect('/admin/undangan');
    }
    // Sudah registrasi: peserta ikut pindah tanggal, selama belum ada foto kegiatan.
    $instId = (int) $u['instansi_id'];
    if ((int) row('SELECT COUNT(*) n FROM evidence WHERE instansi_id = ?', [$instId])['n'] > 0) {
        throw new UserError($u['nama'] . ' sudah mengunggah foto kegiatan, tanggal tidak bisa dipindah.');
    }
    $n = (int) row('SELECT COUNT(*) n FROM peserta WHERE instansi_id = ? AND aktif = 1', [$instId])['n'];
    $perHari = (int) setting('kuota_per_hari');
    if ($perHari > 0 && (int) row('SELECT COUNT(*) n FROM peserta WHERE tanggal = ? AND aktif = 1', [$tanggal])['n'] + $n > $perHari) {
        throw new UserError('Kuota peserta tanggal ' . tgl($tanggal) . ' tidak cukup untuk ' . $n . ' peserta ' . $u['nama'] . '.');
    }
    db()->beginTransaction();
    q('UPDATE undangan SET tanggal = ? WHERE id = ?', [$tanggal, $u['id']]);
    // Kamar dan shuttle per malam/hari, jadi dilepas dan perlu dibagi ulang.
    q('UPDATE peserta SET tanggal = ?, kamar_id = NULL, shuttle_id = NULL WHERE instansi_id = ?', [$tanggal, $instId]);
    log_riwayat($instId, 'pindah_tanggal', 'Panitia memindah tanggal dari ' . tgl($u['tanggal']) . ' ke ' . tgl($tanggal) . '; kamar & shuttle perlu dibagi ulang');
    db()->commit();
    flash('ok', $u['nama'] . ' dan ' . $n . ' pesertanya dipindah ke ' . tgl($tanggal) . '. Kamar & shuttle mereka dilepas, bagi ulang di menu Kamar dan Shuttle.');
    redirect('/admin/undangan');
}

function admin_undangan_hapus(string $id): void
{
    require_admin();
    $u = row('SELECT * FROM undangan WHERE id = ?', [(int) $id]) ?? not_found();
    if ($u['instansi_id']) {
        throw new UserError($u['nama'] . ' sudah registrasi. Hapus instansinya dulu di menu Instansi bila memang batal.');
    }
    q('DELETE FROM undangan WHERE id = ?', [$u['id']]);
    flash('ok', $u['nama'] . ' dihapus dari daftar undangan.');
    redirect('/admin/undangan');
}
