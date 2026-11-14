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

function admin_kamar(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $rooms = kamar_list($tanggal, $orang);
    $belum = array_filter($orang, static fn ($o) => !$o['kamar_id'] || !isset($rooms[$o['kamar_id']]));
    render('admin/kamar', 'Pembagian kamar', compact('tanggal', 'orang', 'rooms', 'belum'), ['admin' => true]);
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

function admin_kamar_tambah(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    q('INSERT INTO kamar (tanggal, nomor, kapasitas) VALUES (?, ?, ?)', [$tanggal, post('nomor') ?: null, max(1, (int) post('kapasitas', setting('kapasitas_kamar')))]);
    flash('ok', 'Kamar ditambahkan.');
    kamar_back($tanggal);
}

function admin_kamar_ubah(string $id): void
{
    require_admin();
    $k = row('SELECT * FROM kamar WHERE id = ?', [(int) $id]) ?? not_found();
    $nomor = post('nomor');
    if ($nomor !== '' && row('SELECT id FROM kamar WHERE tanggal = ? AND nomor = ? AND id <> ?', [$k['tanggal'], $nomor, $k['id']])) {
        throw new UserError("Nomor kamar $nomor sudah dipakai di malam yang sama.");
    }
    $isi = count(array_filter(penghuni_malam($k['tanggal']), static fn ($o) => (int) $o['kamar_id'] === (int) $k['id']));
    $cap = max(1, (int) post('kapasitas', (string) $k['kapasitas']));
    if ($cap < $isi) {
        throw new UserError("Kapasitas tidak boleh kurang dari jumlah penghuni ($isi).");
    }
    q('UPDATE kamar SET nomor = ?, kapasitas = ?, catatan = ? WHERE id = ?', [$nomor ?: null, $cap, post('catatan') ?: null, $k['id']]);
    kamar_back($k['tanggal']);
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

function admin_kamar_tempatkan(): void
{
    require_admin();
    $tanggal = kamar_tanggal();
    $orang = penghuni_malam($tanggal);
    $o = $orang[post('orang')] ?? null;
    if (!$o) {
        throw new UserError('Orang tidak ditemukan.');
    }
    $kamarId = (int) post('kamar_id');
    if ($kamarId === 0) {
        set_kamar($o, null);
        kamar_back($tanggal);
    }
    $rooms = kamar_list($tanggal, $orang);
    $k = $rooms[$kamarId] ?? null;
    if (!$k) {
        throw new UserError('Kamar tidak ditemukan.');
    }
    $isi = array_filter($k['penghuni'], static fn ($p) => $p['key'] !== $o['key']);
    if (count($isi) >= (int) $k['kapasitas']) {
        throw new UserError('Kamar ' . ($k['nomor'] ?: 'ini') . ' sudah penuh (' . $k['kapasitas'] . ' orang).');
    }
    if ($isi && $k['jk'] !== null && $k['jk'] !== $o['jk']) {
        throw new UserError('Kamar ' . ($k['nomor'] ?: 'ini') . ' untuk ' . ($k['jk'] === 'L' ? 'laki-laki' : 'perempuan') . '.');
    }
    set_kamar($o, $kamarId);
    kamar_back($tanggal);
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
