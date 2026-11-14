<?php
// Pembagian kamar hotel per malam. Penghuni = peserta yang memilih menginap + tamu
// (panitia/pejabat yang tidak registrasi). Peserta melihat kamarnya setelah dipublikasikan.
declare(strict_types=1);

function kamar_tanggal(): string
{
    $t = (string) ($_GET['tanggal'] ?? post('tanggal'));
    $dates = event_dates();
    return in_array($t, $dates, true) ? $t : ($dates[0] ?? '');
}

/** Semua orang yang menginap pada malam sebelum $tanggal. Kunci: "p<id>" (peserta) / "t<id>" (tamu). */
function penghuni_malam(string $tanggal): array
{
    $out = [];
    foreach (rows('SELECT p.id, p.nama, p.jk, p.hp, p.kamar_id, p.instansi_id, i.nama instansi
                   FROM peserta p JOIN instansi i ON i.id = p.instansi_id
                   WHERE p.aktif = 1 AND p.menginap = 1 AND p.tanggal = ? ORDER BY i.nama, p.id', [$tanggal]) as $p) {
        $out['p' . $p['id']] = $p + ['key' => 'p' . $p['id'], 'tipe' => 'peserta', 'grup' => 'i' . $p['instansi_id']];
    }
    foreach (rows('SELECT * FROM tamu WHERE tanggal = ? ORDER BY id', [$tanggal]) as $t) {
        $ket = $t['keterangan'] ?: 'Tamu panitia';
        $out['t' . $t['id']] = $t + ['key' => 't' . $t['id'], 'tipe' => 'tamu', 'instansi' => $ket, 'instansi_id' => null, 'grup' => 't' . mb_strtolower($ket)];
    }
    return $out;
}

function kamar_list(string $tanggal, array $orang): array
{
    $rooms = [];
    foreach (rows('SELECT * FROM kamar WHERE tanggal = ? ORDER BY nomor IS NULL, LENGTH(nomor), nomor, id', [$tanggal]) as $k) {
        $rooms[$k['id']] = $k + ['penghuni' => []];
    }
    foreach ($orang as $o) {
        if ($o['kamar_id'] && isset($rooms[$o['kamar_id']])) {
            $rooms[$o['kamar_id']]['penghuni'][] = $o;
        }
    }
    foreach ($rooms as &$k) {
        $jk = array_unique(array_column($k['penghuni'], 'jk'));
        $k['jk'] = count($jk) === 1 ? $jk[0] : (count($jk) > 1 ? 'campur' : null);
    }
    return $rooms;
}

/** Label kamar untuk isian tabel: nomornya, atau "#id" untuk kamar yang belum bernomor. */
function kamar_label(array $k): string
{
    return $k['nomor'] !== null && $k['nomor'] !== '' ? $k['nomor'] : '#' . $k['id'];
}

function admin_kamar(array $pending = [], array $errors = []): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $rooms = kamar_list($tanggal, $orang);
    $belum = array_filter($orang, static fn ($o) => !$o['kamar_id'] || !isset($rooms[$o['kamar_id']]));
    // Belum dapat kamar di atas, sisanya urut kamar lalu nama.
    uasort($orang, static function ($a, $b) use ($rooms) {
        $ka = isset($rooms[$a['kamar_id']]) ? kamar_label($rooms[$a['kamar_id']]) : '';
        $kb = isset($rooms[$b['kamar_id']]) ? kamar_label($rooms[$b['kamar_id']]) : '';
        return [$ka !== '', strnatcmp($ka, $kb), $a['nama']] <=> [$kb !== '', 0, $b['nama']];
    });
    render('admin/kamar', 'Pembagian kamar', compact('tanggal', 'orang', 'rooms', 'belum', 'pending', 'errors'), ['admin' => true]);
}

function kamar_back(string $tanggal): void
{
    redirect('/admin/kamar?tanggal=' . $tanggal);
}

function set_kamar(array $o, ?int $kamarId): void
{
    $table = $o['tipe'] === 'tamu' ? 'tamu' : 'peserta';
    q("UPDATE $table SET kamar_id = ? WHERE id = ?", [$kamarId, $o['id']]);
}

/**
 * Rekomendasi: orang yang belum punya kamar dikelompokkan per jenis kelamin; rekan satu
 * instansi (atau satu kelompok tamu) ditempatkan bersama dulu, sisanya digabung dengan
 * instansi lain yang jenis kelaminnya sama. Kamar yang sudah ada tidak diubah.
 */
function admin_kamar_rekomendasi(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $cap = max(1, (int) setting('kapasitas_kamar'));
    $orang = penghuni_malam($tanggal);
    $rooms = kamar_list($tanggal, $orang);
    $belum = array_filter($orang, static fn ($o) => !$o['kamar_id'] || !isset($rooms[$o['kamar_id']]));
    $dibuat = 0;
    db()->beginTransaction();
    foreach (['L', 'P'] as $jk) {
        $grup = [];
        $isi = [];
        foreach ($belum as $o) {
            if ($o['jk'] === $jk) {
                $grup[$o['grup']][] = $o;
            }
        }
        $sisa = [];
        foreach ($grup as $anggota) {
            foreach (array_chunk($anggota, $cap) as $chunk) {
                if (count($chunk) === $cap) {
                    $isi[] = $chunk;
                } else {
                    $sisa[] = $chunk;
                }
            }
        }
        // Sisa (kurang dari satu kamar) digabung lintas instansi, kelompok besar dulu.
        usort($sisa, static fn ($a, $b) => count($b) <=> count($a));
        $campur = [];
        foreach ($sisa as $chunk) {
            foreach ($campur as &$room) {
                if (count($room) + count($chunk) <= $cap) {
                    $room = array_merge($room, $chunk);
                    continue 2;
                }
            }
            unset($room);
            $campur[] = $chunk;
        }
        unset($room);
        foreach (array_merge($isi, $campur) as $penghuni) {
            q('INSERT INTO kamar (tanggal, kapasitas) VALUES (?, ?)', [$tanggal, $cap]);
            $id = (int) db()->lastInsertId();
            foreach ($penghuni as $o) {
                set_kamar($o, $id);
            }
            $dibuat++;
        }
    }
    db()->commit();
    flash('ok', $dibuat ? "$dibuat kamar direkomendasikan. Periksa, isi nomor kamar, lalu publikasikan." : 'Semua yang menginap sudah punya kamar.');
    kamar_back($tanggal);
}

/** Simpan tabel daftar kamar: nomor, kapasitas, catatan; plus satu baris kamar baru. */
function admin_kamar_daftar(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $rooms = kamar_list($tanggal, $orang);
    $nomor = (array) ($_POST['nomor'] ?? []);
    $seen = [];
    foreach ($rooms as $id => $k) {
        $n = trim((string) ($nomor[$id] ?? $k['nomor']));
        if ($n !== '' && isset($seen[$n])) {
            throw new UserError("Nomor kamar $n dipakai lebih dari sekali.");
        }
        $seen[$n] = true;
        $cap = max(1, (int) ($_POST['kapasitas'][$id] ?? $k['kapasitas']));
        if ($cap < count($k['penghuni'])) {
            throw new UserError('Kapasitas kamar ' . ($n ?: '#' . $id) . ' tidak boleh kurang dari jumlah penghuninya (' . count($k['penghuni']) . ').');
        }
    }
    $baru = post('baru_nomor');
    if ($baru !== '' && isset($seen[$baru])) {
        throw new UserError("Nomor kamar $baru sudah ada.");
    }
    db()->beginTransaction();
    foreach ($rooms as $id => $k) {
        q('UPDATE kamar SET nomor = ?, kapasitas = ?, catatan = ? WHERE id = ?', [
            trim((string) ($nomor[$id] ?? $k['nomor'])) ?: null,
            max(1, (int) ($_POST['kapasitas'][$id] ?? $k['kapasitas'])),
            trim((string) ($_POST['catatan'][$id] ?? $k['catatan'])) ?: null,
            $id,
        ]);
    }
    if ($baru !== '') {
        q('INSERT INTO kamar (tanggal, nomor, kapasitas) VALUES (?, ?, ?)', [$tanggal, $baru, max(1, (int) post('baru_kapasitas', setting('kapasitas_kamar')))]);
    }
    db()->commit();
    flash('ok', 'Daftar kamar disimpan.');
    kamar_back($tanggal);
}

/**
 * Simpan tabel penempatan: tiap orang diisi nomor kamar (atau "#id" untuk kamar tanpa nomor).
 * Nomor yang belum ada otomatis menjadi kamar baru. Semua dicek dulu (kapasitas & jenis
 * kelamin); kalau ada yang salah, tidak ada yang disimpan dan isian dikembalikan.
 */
function admin_kamar_penempatan(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $rooms = kamar_list($tanggal, $orang);
    $byLabel = [];
    foreach ($rooms as $id => $k) {
        $byLabel[mb_strtolower(kamar_label($k))] = $id;
    }
    $input = array_map(static fn ($v) => trim((string) $v), (array) ($_POST['kamar'] ?? []));
    $target = [];   // key orang => id kamar | 'baru:<nomor>' | null
    foreach ($orang as $key => $o) {
        $v = $input[$key] ?? (isset($rooms[$o['kamar_id']]) ? kamar_label($rooms[$o['kamar_id']]) : '');
        $target[$key] = $v === '' ? null : ($byLabel[mb_strtolower($v)] ?? (str_starts_with($v, '#') ? false : 'baru:' . $v));
    }
    $errors = [];
    $isi = [];
    foreach ($target as $key => $t) {
        if ($t === false) {
            $errors[] = $orang[$key]['nama'] . ': kamar "' . $input[$key] . '" tidak ditemukan.';
        } elseif ($t !== null) {
            $isi[$t][] = $orang[$key];
        }
    }
    $cap = max(1, (int) setting('kapasitas_kamar'));
    foreach ($isi as $t => $list) {
        $label = is_int($t) ? kamar_label($rooms[$t]) : substr((string) $t, 5);
        $max = is_int($t) ? (int) $rooms[$t]['kapasitas'] : $cap;
        if (count($list) > $max) {
            $errors[] = "Kamar $label kelebihan penghuni (" . count($list) . "/$max): " . implode(', ', array_column($list, 'nama')) . '.';
        }
        if (count(array_unique(array_column($list, 'jk'))) > 1) {
            $errors[] = "Kamar $label mencampur laki-laki dan perempuan: " . implode(', ', array_column($list, 'nama')) . '.';
        }
    }
    if ($errors) {
        admin_kamar($input, $errors);
        return;
    }
    db()->beginTransaction();
    $baruId = [];
    foreach ($target as $key => $t) {
        if (is_string($t)) {
            if (!isset($baruId[$t])) {
                q('INSERT INTO kamar (tanggal, nomor, kapasitas) VALUES (?, ?, ?)', [$tanggal, substr($t, 5), $cap]);
                $baruId[$t] = (int) db()->lastInsertId();
            }
            $t = $baruId[$t];
        }
        set_kamar($orang[$key], $t);
    }
    // Kamar tanpa nomor yang jadi kosong dibuang supaya daftar tetap rapi.
    q('DELETE k FROM kamar k WHERE k.tanggal = ? AND k.nomor IS NULL
         AND NOT EXISTS (SELECT 1 FROM peserta p WHERE p.kamar_id = k.id AND p.aktif = 1)
         AND NOT EXISTS (SELECT 1 FROM tamu t WHERE t.kamar_id = k.id)', [$tanggal]);
    db()->commit();
    flash('ok', 'Penempatan kamar disimpan' . ($baruId ? ' (' . count($baruId) . ' kamar baru dibuat)' : '') . '.');
    kamar_back($tanggal);
}

function admin_kamar_hapus(string $id): void
{
    require_admin();
    $k = row('SELECT * FROM kamar WHERE id = ?', [(int) $id]) ?? not_found();
    q('UPDATE peserta SET kamar_id = NULL WHERE kamar_id = ?', [$k['id']]);
    q('UPDATE tamu SET kamar_id = NULL WHERE kamar_id = ?', [$k['id']]);
    q('DELETE FROM kamar WHERE id = ?', [$k['id']]);
    flash('ok', 'Kamar dihapus; penghuninya kembali ke daftar belum dapat kamar.');
    kamar_back($k['tanggal']);
}

function admin_kamar_reset(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    q('UPDATE peserta SET kamar_id = NULL WHERE tanggal = ?', [$tanggal]);
    q('UPDATE tamu SET kamar_id = NULL WHERE tanggal = ?', [$tanggal]);
    q('DELETE FROM kamar WHERE tanggal = ?', [$tanggal]);
    flash('ok', 'Pembagian kamar malam ' . malam_menginap($tanggal) . ' dikosongkan.');
    kamar_back($tanggal);
}

function admin_kamar_publikasi(): void
{
    require_admin();
    set_setting('kamar_dipublikasikan', post('publikasi') === '1' ? '1' : '0');
    flash('ok', post('publikasi') === '1' ? 'Pembagian kamar sekarang terlihat oleh peserta.' : 'Pembagian kamar disembunyikan dari peserta.');
    kamar_back(kamar_tanggal());
}

function admin_tamu_tambah(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $nama = post('nama');
    $jk = post('jk');
    if ($nama === '' || !in_array($jk, ['L', 'P'], true)) {
        throw new UserError('Isi nama dan jenis kelamin tamu.');
    }
    q('INSERT INTO tamu (tanggal, nama, jk, keterangan, hp) VALUES (?, ?, ?, ?, ?)', [$tanggal, $nama, $jk, post('keterangan') ?: null, normalize_hp(post('hp')) ?: null]);
    flash('ok', "$nama ditambahkan ke daftar menginap.");
    kamar_back($tanggal);
}

function admin_tamu_hapus(string $id): void
{
    require_admin();
    $t = row('SELECT * FROM tamu WHERE id = ?', [(int) $id]) ?? not_found();
    q('DELETE FROM tamu WHERE id = ?', [$t['id']]);
    flash('ok', $t['nama'] . ' dihapus dari daftar menginap.');
    kamar_back($t['tanggal']);
}

/** Info kamar untuk peserta (halaman instansi), hanya bila sudah dipublikasikan dan bernomor. */
function kamar_peserta(array $p): ?array
{
    if (setting('kamar_dipublikasikan') !== '1' || !$p['menginap'] || !$p['kamar_id']) {
        return null;
    }
    $k = row('SELECT * FROM kamar WHERE id = ?', [$p['kamar_id']]);
    if (!$k || !$k['nomor']) {
        return null;
    }
    $teman = [];
    foreach (penghuni_malam($p['tanggal']) as $o) {
        if ((int) $o['kamar_id'] === (int) $k['id'] && $o['key'] !== 'p' . $p['id']) {
            $teman[] = $o['nama'] . ' (' . $o['instansi'] . ')';
        }
    }
    return ['nomor' => $k['nomor'], 'catatan' => $k['catatan'], 'teman' => $teman];
}
