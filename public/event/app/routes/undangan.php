<?php
// Daftar undangan: panitia menetapkan instansi mana ikut tanggal berapa, sebelum registrasi.
// Saat daftar terisi, formulir registrasi berubah jadi pilihan nama instansi (tanggal ikut otomatis).
declare(strict_types=1);

/** Singkatan provinsi yang lazim dipakai. */
const PROVINSI_ALIAS = [
    'nad' => 'Aceh', 'sumut' => 'Sumatera Utara', 'sumbar' => 'Sumatera Barat', 'sumsel' => 'Sumatera Selatan',
    'kepri' => 'Kepulauan Riau', 'babel' => 'Kepulauan Bangka Belitung', 'bangka belitung' => 'Kepulauan Bangka Belitung',
    'dki' => 'DKI Jakarta', 'jabar' => 'Jawa Barat', 'jateng' => 'Jawa Tengah', 'jatim' => 'Jawa Timur',
    'diy' => 'Daerah Istimewa Yogyakarta', 'di yogyakarta' => 'Daerah Istimewa Yogyakarta', 'jogja' => 'Daerah Istimewa Yogyakarta', 'jogjakarta' => 'Daerah Istimewa Yogyakarta',
    'ntb' => 'Nusa Tenggara Barat', 'ntt' => 'Nusa Tenggara Timur',
    'kalbar' => 'Kalimantan Barat', 'kalsel' => 'Kalimantan Selatan', 'kalteng' => 'Kalimantan Tengah', 'kaltim' => 'Kalimantan Timur', 'kaltara' => 'Kalimantan Utara',
    'sulbar' => 'Sulawesi Barat', 'sulsel' => 'Sulawesi Selatan', 'sulteng' => 'Sulawesi Tengah', 'sultra' => 'Sulawesi Tenggara', 'sulut' => 'Sulawesi Utara',
    'malut' => 'Maluku Utara', 'pabar' => 'Papua Barat',
];

/**
 * Cocokkan teks bebas ke nama provinsi resmi. Menerima nama provinsi ("sumatera utara", "Prov. Jawa Barat"),
 * singkatan ("Sumut", "DIY"), atau nama kab/kota ("Banda Aceh" → Aceh, "Medan" → Sumatera Utara).
 * Mengembalikan null bila tidak ketemu atau ambigu.
 */
function cari_provinsi(string $v): ?string
{
    $q = mb_strtolower(trim(preg_replace('/\s+/', ' ', $v)));
    $q = trim(preg_replace('/^(provinsi|prov\.?)\s*/u', '', $q));
    if ($q === '') {
        return null;
    }
    if (isset(PROVINSI_ALIAS[$q])) {
        return PROVINSI_ALIAS[$q];
    }
    $sub = [];
    foreach (provinsi_list() as $p) {
        $lp = mb_strtolower($p);
        if ($lp === $q) {
            return $p;
        }
        if (mb_strlen($q) >= 4 && str_contains($lp, $q)) {
            $sub[] = $p;
        }
    }
    if (count($sub) === 1) {
        return $sub[0];   // mis. "jakarta" → DKI Jakarta
    }
    // Nama kab/kota → provinsinya (Kab. dan Kota bernama sama selalu satu provinsi).
    $nama = trim(preg_replace('/^(kabupaten|kab\.?|kota)\s+/u', '', $q));
    $hasil = [];
    foreach (kabkota_list() as $k) {
        [$kk, $prov] = explode(', ', $k, 2) + [1 => ''];
        if (mb_strtolower(trim(preg_replace('/^(Kab\.|Kota)\s+/u', '', $kk))) === $nama) {
            $hasil[$prov] = true;
        }
    }
    return count($hasil) === 1 ? array_key_first($hasil) : null;
}

function admin_undangan(): void
{
    require_admin();
    $list = rows(
        'SELECT u.*, i.id inst_id,
            (SELECT COUNT(*) FROM peserta p WHERE p.instansi_id = u.instansi_id AND p.aktif = 1) peserta,
            (SELECT COUNT(*) FROM evidence e WHERE e.instansi_id = u.instansi_id) foto
         FROM undangan u LEFT JOIN instansi i ON i.id = u.instansi_id ORDER BY u.tanggal, u.nama, u.provinsi'
    );
    $perTanggal = array_fill_keys(event_dates(), []);
    foreach ($list as $u) {
        $perTanggal[$u['tanggal']][] = $u;
    }
    // Instansi yang registrasi sebelum daftar undangan dipakai (tidak terhubung ke undangan).
    $lepas = rows('SELECT i.id, i.nama, i.kabkota, i.provinsi FROM instansi i LEFT JOIN undangan u ON u.instansi_id = i.id WHERE u.id IS NULL ORDER BY i.nama');
    render('admin/undangan', 'Undangan & tanggal', compact('perTanggal', 'lepas'), ['admin' => true]);
}

function admin_undangan_tambah(): void
{
    require_admin();
    $tanggal = post('tanggal');
    if (!in_array($tanggal, event_dates(), true)) {
        throw new UserError('Pilih tanggal.');
    }
    // Satu per satu (nama + provinsi) atau banyak sekaligus (tempel dari Excel: Nama [Tab, ; atau ,] Provinsi).
    $baris = [];
    if (post('banyak') !== '') {
        foreach (preg_split('/\R/', post('banyak')) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/\t|;/', $line)) {
                $kol = array_map('trim', preg_split('/\t|;/', $line));
            } else {
                // Pemisah koma: koma terakhir memisahkan provinsi (nama instansi boleh berisi koma).
                $i = strrpos($line, ',');
                $kol = $i === false ? [trim($line), ''] : [trim(substr($line, 0, $i)), trim(substr($line, $i + 1))];
            }
            $kol = array_values(array_filter($kol, static fn ($k) => $k !== ''));
            if ($n === 0 && preg_match('/^nama\b/i', $kol[0] ?? '')) {
                continue;   // baris judul kolom dari Excel
            }
            $baris[] = [$n + 1, $kol[0] ?? '', $kol[1] ?? '', $line];
        }
    } else {
        $baris[] = [0, post('nama'), post('provinsi'), ''];
    }
    if (!$baris) {
        throw new UserError('Isi nama instansi.');
    }
    $masuk = 0;
    $gagal = [];
    $sisa = [];
    foreach ($baris as [$no, $nama, $prov, $asli]) {
        $label = $no ? "Baris $no ($nama)" : $nama;
        if ($nama === '' || mb_strlen($nama) > 200) {
            $gagal[] = ($no ? "Baris $no" : 'Nama') . ': nama instansi kosong atau terlalu panjang.';
            $sisa[] = $asli;
            continue;
        }
        $provResmi = cari_provinsi($prov);
        if ($provResmi === null) {
            $gagal[] = $prov === ''
                ? "$label: provinsi belum diisi. Tulis Nama instansi; Provinsi, mis. BRMP Aceh; Aceh."
                : "$label: provinsi \"$prov\" tidak dikenali. Tulis nama provinsi, mis. Sumatera Utara.";
            $sisa[] = $asli;
            continue;
        }
        if ($ada = row('SELECT tanggal FROM undangan WHERE nama = ? AND provinsi = ?', [$nama, $provResmi])) {
            $gagal[] = "$label: sudah ada di daftar (" . tgl($ada['tanggal']) . ').';
            $sisa[] = $asli;
            continue;
        }
        // Instansi yang sudah registrasi sebelumnya dengan nama & provinsi sama langsung dihubungkan.
        $inst = row('SELECT i.id, i.pp FROM instansi i LEFT JOIN undangan u ON u.instansi_id = i.id WHERE u.id IS NULL AND i.nama = ? AND i.provinsi = ?', [$nama, $provResmi]);
        if ($inst && ($t = instansi_tanggal((int) $inst['id'])) && $t !== $tanggal) {
            $gagal[] = "$label: sudah registrasi untuk " . tgl($t) . '. Pilih tanggal itu, atau pindahkan setelah masuk daftar.';
            $sisa[] = $asli;
            continue;
        }
        // Jabodetabek (pulang-pergi) ditebak dari provinsi + nama; panitia bisa mengubahnya di tabel.
        $pp = $inst ? (int) $inst['pp'] : (int) tebak_jabodetabek($nama, $provResmi);
        q('INSERT INTO undangan (nama, provinsi, tanggal, pp, instansi_id) VALUES (?, ?, ?, ?, ?)', [$nama, $provResmi, $tanggal, $pp, $inst['id'] ?? null]);
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

/** Tandai / batalkan Jabodetabek (pulang-pergi, tanpa menginap). */
function admin_undangan_pp(string $id): void
{
    require_admin();
    $u = row('SELECT * FROM undangan WHERE id = ?', [(int) $id]) ?? not_found();
    $pp = post('pp') === '1' ? 1 : 0;
    db()->beginTransaction();
    q('UPDATE undangan SET pp = ? WHERE id = ?', [$pp, $u['id']]);
    $lepas = 0;
    if ($u['instansi_id']) {
        $instId = (int) $u['instansi_id'];
        q('UPDATE instansi SET pp = ? WHERE id = ?', [$pp, $instId]);
        if ($pp) {
            // Peserta yang terlanjur memilih menginap dibatalkan menginapnya; kamar & shuttle dilepas.
            $lepas = q('UPDATE peserta SET menginap = 0, bagikan_kontak = 0, kamar_id = NULL, shuttle_id = NULL WHERE instansi_id = ? AND menginap = 1', [$instId])->rowCount();
        }
        log_riwayat($instId, 'pp', $pp
            ? 'Panitia menandai instansi Jabodetabek (pulang-pergi, tidak menginap)' . ($lepas ? "; $lepas peserta batal menginap" : '')
            : 'Panitia membuka pilihan menginap (bukan Jabodetabek)');
    }
    db()->commit();
    flash('ok', $u['nama'] . ($pp ? ' ditandai Jabodetabek: pulang-pergi, tanpa menginap.' : ' bisa memilih menginap.') . ($lepas ? " $lepas peserta batal menginap; kamar & shuttle mereka dilepas." : ''));
    redirect('/admin/undangan');
}
