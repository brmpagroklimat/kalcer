<?php
// Shuttle hotel → lokasi acara. Penumpang = yang menginap malam sebelumnya (peserta + tamu).
// Satu orang hanya bisa di satu kendaraan per hari (kolom shuttle_id), jadi tidak bisa dobel.
declare(strict_types=1);

function kendaraan_list(string $tanggal, array $orang): array
{
    $list = [];
    foreach (rows('SELECT * FROM kendaraan WHERE tanggal = ? ORDER BY jam IS NULL, jam, nama, id', [$tanggal]) as $k) {
        $list[$k['id']] = $k + ['penumpang' => []];
    }
    foreach ($orang as $o) {
        if ($o['shuttle_id'] && isset($list[$o['shuttle_id']])) {
            $list[$o['shuttle_id']]['penumpang'][] = $o;
        }
    }
    return $list;
}

function set_shuttle(array $o, ?int $id): void
{
    $table = $o['tipe'] === 'tamu' ? 'tamu' : 'peserta';
    q("UPDATE $table SET shuttle_id = ? WHERE id = ?", [$id, $o['id']]);
}

function kendaraan_label(array $k): string
{
    return $k['nama'] . ($k['jam'] ? ' ' . jam($k['jam']) : '');
}

function admin_shuttle(array $pending = [], array $errors = []): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $mobil = kendaraan_list($tanggal, $orang);
    $belum = array_filter($orang, static fn ($o) => !$o['shuttle_id'] || !isset($mobil[$o['shuttle_id']]));
    render('admin/shuttle', 'Shuttle', compact('tanggal', 'orang', 'mobil', 'belum', 'pending', 'errors'), ['admin' => true]);
}

function shuttle_back(string $tanggal, string $anchor = ''): void
{
    redirect('/admin/shuttle?tanggal=' . $tanggal . $anchor);
}

/** Simpan tabel kendaraan (semua baris) + satu baris kendaraan baru. */
function admin_kendaraan_simpan(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $mobil = kendaraan_list($tanggal, $orang);
    $f = static fn (string $k, $id, $def = '') => trim((string) ($_POST[$k][$id] ?? $def));
    $hp = static function (string $v, string $label): ?string {
        if ($v === '') {
            return null;
        }
        $d = valid_hp($v);
        if ($d === null) {
            throw new UserError("$label: No. HP hanya angka, 10–15 digit, diawali 0 atau 62.");
        }
        return $d;
    };
    $baris = [];
    foreach ($mobil as $id => $k) {
        $baris[$id] = $k;
    }
    $baris['baru'] = null;
    $simpan = [];
    foreach ($baris as $id => $k) {
        $nama = $f('nama', $id, $k['nama'] ?? '');
        if ($id === 'baru' && $nama === '') {
            continue;
        }
        if ($nama === '') {
            throw new UserError('Nama kendaraan wajib diisi.');
        }
        $cap = (int) $f('kapasitas', $id, (string) ($k['kapasitas'] ?? 10));
        if ($cap < 1 || $cap > 60) {
            throw new UserError("$nama: kapasitas 1–60 orang.");
        }
        if ($k && $cap < count($k['penumpang'])) {
            throw new UserError("$nama: kapasitas tidak boleh kurang dari jumlah penumpangnya (" . count($k['penumpang']) . ').');
        }
        $jam = $f('jam', $id, (string) ($k['jam'] ?? ''));
        if ($jam !== '' && !preg_match('/^\d{2}:\d{2}/', $jam)) {
            throw new UserError("$nama: format jam tidak valid.");
        }
        $simpan[$id] = [
            $nama, $f('plat', $id, $k['plat'] ?? '') ?: null, $cap, $jam !== '' ? substr($jam, 0, 5) : null,
            $f('titik_jemput', $id, $k['titik_jemput'] ?? '') ?: null,
            $f('supir_nama', $id, $k['supir_nama'] ?? '') ?: null, $hp($f('supir_hp', $id, $k['supir_hp'] ?? ''), "$nama (supir)"),
            $f('pic_nama', $id, $k['pic_nama'] ?? '') ?: null, $hp($f('pic_hp', $id, $k['pic_hp'] ?? ''), "$nama (PIC Shuttle)"),
        ];
    }
    db()->beginTransaction();
    foreach ($simpan as $id => $v) {
        if ($id === 'baru') {
            q('INSERT INTO kendaraan (nama, plat, kapasitas, jam, titik_jemput, supir_nama, supir_hp, pic_nama, pic_hp, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$v, $tanggal]);
        } else {
            q('UPDATE kendaraan SET nama = ?, plat = ?, kapasitas = ?, jam = ?, titik_jemput = ?, supir_nama = ?, supir_hp = ?, pic_nama = ?, pic_hp = ? WHERE id = ?', [...$v, $id]);
        }
    }
    db()->commit();
    flash('ok', isset($simpan['baru']) ? 'Kendaraan ditambahkan.' : 'Data kendaraan disimpan.');
    shuttle_back($tanggal, '#langkah-1');
}

function admin_kendaraan_hapus(string $id): void
{
    require_admin();
    $k = row('SELECT * FROM kendaraan WHERE id = ?', [(int) $id]) ?? not_found();
    q('UPDATE peserta SET shuttle_id = NULL WHERE shuttle_id = ?', [$k['id']]);
    q('UPDATE tamu SET shuttle_id = NULL WHERE shuttle_id = ?', [$k['id']]);
    q('DELETE FROM kendaraan WHERE id = ?', [$k['id']]);
    flash('ok', $k['nama'] . ' dihapus; penumpangnya kembali ke belum dapat kendaraan.');
    shuttle_back($k['tanggal'], '#langkah-1');
}

/**
 * Bagi otomatis penumpang yang belum dapat kendaraan: rombongan satu instansi (atau satu
 * kelompok tamu) diusahakan satu kendaraan; kelompok besar ditempatkan dulu, ke kendaraan
 * pertama (urut jam) yang masih muat. Kelompok yang tidak muat utuh dipecah.
 */
function admin_shuttle_bagi(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $mobil = kendaraan_list($tanggal, $orang);
    if (!$mobil) {
        throw new UserError('Tambahkan kendaraan dulu.');
    }
    $sisa = array_map(static fn ($k) => (int) $k['kapasitas'] - count($k['penumpang']), $mobil);
    $grup = [];
    foreach ($orang as $o) {
        if (!$o['shuttle_id'] || !isset($mobil[$o['shuttle_id']])) {
            $grup[$o['grup']][] = $o;
        }
    }
    uasort($grup, static fn ($a, $b) => count($b) <=> count($a));
    $n = 0;
    $tidakMuat = 0;
    db()->beginTransaction();
    foreach ($grup as $anggota) {
        $target = null;
        foreach ($sisa as $id => $s) {
            if ($s >= count($anggota)) {
                $target = $id;
                break;
            }
        }
        foreach ($anggota as $o) {
            $id = $target;
            if ($id === null || $sisa[$id] <= 0) {
                $id = null;
                foreach ($sisa as $kid => $s) {
                    if ($s > 0) {
                        $id = $kid;
                        break;
                    }
                }
            }
            if ($id === null) {
                $tidakMuat++;
                continue;
            }
            set_shuttle($o, $id);
            $sisa[$id]--;
            $n++;
        }
    }
    db()->commit();
    flash($tidakMuat ? 'error' : 'ok', "$n penumpang ditempatkan." . ($tidakMuat ? " $tidakMuat orang belum dapat kendaraan karena kapasitas kurang; tambah kendaraan atau kapasitas." : ' Periksa tiap kendaraan, lalu publikasikan.'));
    shuttle_back($tanggal, '#langkah-2');
}

/**
 * Isi satu kendaraan lewat checklist: yang dicentang masuk kendaraan ini, yang tidak dicentang
 * (dan sebelumnya di kendaraan ini) dikeluarkan. Orang di kendaraan lain tidak bisa dicentang,
 * jadi tidak ada penumpang dobel.
 */
function admin_shuttle_isi(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $mobil = kendaraan_list($tanggal, $orang);
    $kid = (int) post('kendaraan_id');
    $k = $mobil[$kid] ?? null;
    if (!$k) {
        throw new UserError('Pilih kendaraan.');
    }
    $pilih = array_values(array_intersect(array_keys($orang), (array) ($_POST['orang'] ?? [])));
    foreach ($pilih as $key) {
        $sid = $orang[$key]['shuttle_id'];
        if ($sid && $sid != $kid && isset($mobil[$sid])) {
            throw new UserError($orang[$key]['nama'] . ' sudah di ' . $mobil[$sid]['nama'] . '. Keluarkan dari sana dulu.');
        }
    }
    if (count($pilih) > (int) $k['kapasitas']) {
        throw new UserError($k['nama'] . ' hanya ' . $k['kapasitas'] . ' kursi; yang dipilih ' . count($pilih) . ' orang.');
    }
    db()->beginTransaction();
    foreach ($orang as $key => $o) {
        if (in_array($key, $pilih, true)) {
            set_shuttle($o, $kid);
        } elseif ((int) $o['shuttle_id'] === $kid) {
            set_shuttle($o, null);
        }
    }
    db()->commit();
    flash('ok', $k['nama'] . ': ' . count($pilih) . '/' . $k['kapasitas'] . ' penumpang disimpan.');
    redirect('/admin/shuttle?tanggal=' . $tanggal . '&k=' . $kid . '#langkah-2');
}

function admin_shuttle_publikasi(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $pilih = array_map('intval', (array) ($_POST['publik'] ?? []));
    $ids = array_map('intval', array_column(rows('SELECT id FROM kendaraan WHERE tanggal = ?', [$tanggal]), 'id'));
    foreach ($ids as $id) {
        q('UPDATE kendaraan SET publik = ? WHERE id = ?', [in_array($id, $pilih, true) ? 1 : 0, $id]);
    }
    $n = count(array_intersect($ids, $pilih));
    flash('ok', $n ? "$n kendaraan dipublikasikan ke peserta." : 'Tidak ada kendaraan yang dipublikasikan.');
    shuttle_back($tanggal, '#langkah-3');
}

/** Manifest penumpang per kendaraan (untuk supir & PIC Shuttle). */
function admin_shuttle_csv(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $mobil = kendaraan_list($tanggal, $orang);
    $data = [['Tanggal acara', 'Kendaraan', 'Plat', 'Jam berangkat', 'Titik jemput', 'Supir', 'HP supir', 'PIC Shuttle', 'HP PIC Shuttle', 'No.', 'Penumpang', 'Instansi / keterangan', 'HP penumpang']];
    foreach ($mobil as $k) {
        foreach ($k['penumpang'] ?: [null] as $i => $o) {
            $data[] = [$tanggal, $k['nama'], $k['plat'], jam($k['jam']), $k['titik_jemput'], $k['supir_nama'], $k['supir_hp'], $k['pic_nama'], $k['pic_hp'],
                $o ? $i + 1 : '', $o['nama'] ?? '(kosong)', $o['instansi'] ?? '', $o['hp'] ?? ''];
        }
    }
    foreach ($orang as $o) {
        if (!$o['shuttle_id'] || !isset($mobil[$o['shuttle_id']])) {
            $data[] = [$tanggal, 'BELUM DAPAT KENDARAAN', '', '', '', '', '', '', '', '', $o['nama'], $o['instansi'], $o['hp']];
        }
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kalcer-shuttle-' . $tanggal . '.csv"');
    echo csv_string($data);
}

/** Info shuttle untuk peserta (halaman instansi), hanya bila kendaraannya dipublikasikan. */
function shuttle_peserta(array $p): ?array
{
    if (!$p['menginap'] || empty($p['shuttle_id'])) {
        return null;
    }
    $k = row('SELECT * FROM kendaraan WHERE id = ? AND publik = 1', [$p['shuttle_id']]);
    return $k ?: null;
}
