<?php
// Halaman instansi (diakses lewat link token): peserta, foto evidence, reimburse.
declare(strict_types=1);

function can_edit_peserta(): bool
{
    return is_panitia() || before('batas_ganti');
}

function can_upload(): bool
{
    return is_panitia() || before('batas_upload');
}

function guard_peserta_edit(): void
{
    if (!can_edit_peserta()) {
        throw new UserError('Batas perubahan peserta sudah lewat (' . tgl_jam(setting('batas_ganti')) . '). Hubungi panitia untuk perubahan.');
    }
}

function own_peserta(array $inst, string $id): array
{
    $p = row('SELECT * FROM peserta WHERE id = ? AND instansi_id = ? AND aktif = 1', [(int) $id, $inst['id']]);
    if (!$p) {
        throw new UserError('Peserta tidak ditemukan.');
    }
    return $p;
}

/** Satu instansi hanya ikut satu tanggal: tanggal yang dipilih saat mendaftar. */
function instansi_tanggal(int $instansiId): ?string
{
    $r = row('SELECT tanggal FROM peserta WHERE instansi_id = ? ORDER BY id LIMIT 1', [$instansiId]);
    return $r ? $r['tanggal'] : null;
}

function peserta_by_date(int $instansiId): array
{
    $out = [];
    if ($t = instansi_tanggal($instansiId)) {
        $out[$t] = [];
    }
    foreach (rows('SELECT * FROM peserta WHERE instansi_id = ? ORDER BY tanggal, aktif DESC, id', [$instansiId]) as $p) {
        $out[$p['tanggal']][] = $p;
    }
    return $out;
}

function inst_peserta(): void
{
    $inst = require_instansi();
    render('instansi/peserta', 'Peserta', [
        'inst' => $inst,
        'byDate' => peserta_by_date((int) $inst['id']),
        'canEdit' => can_edit_peserta(),
    ], ['inst' => $inst, 'tab' => 'peserta']);
}

/** Ringkasan instansi: alur instansi + status tiap langkah, akomodasi, info, riwayat. */
function inst_ringkasan(): void
{
    $inst = require_instansi();
    $id = (int) $inst['id'];
    $tanggal = instansi_tanggal($id);
    $peserta = rows('SELECT * FROM peserta WHERE instansi_id = ? AND aktif = 1 ORDER BY id', [$id]);
    $inap = array_values(array_filter($peserta, static fn ($p) => $p['menginap']));
    $akomodasi = [];
    foreach ($inap as $p) {
        $akomodasi[$p['id']] = ['kamar' => kamar_peserta($p), 'shuttle' => shuttle_peserta($p)];
    }
    $tahapAda = $tanggal ? array_column(rows('SELECT DISTINCT tahap FROM evidence WHERE instansi_id = ? AND tanggal = ?', [$id, $tanggal]), 'tahap') : [];
    render('instansi/ringkasan', 'Ringkasan', [
        'inst' => $inst,
        'tanggal' => $tanggal,
        'peserta' => $peserta,
        'inap' => $inap,
        'akomodasi' => $akomodasi,
        'tahapAda' => array_intersect(array_keys(TAHAP), $tahapAda),
        'reimburse' => reimburse_totals($id),
        'riwayat' => rows('SELECT * FROM riwayat WHERE instansi_id = ? ORDER BY id DESC LIMIT 100', [$id]),
    ], ['inst' => $inst, 'tab' => 'ringkasan']);
}

function inst_update_instansi(): void
{
    $inst = require_instansi();
    $d = [
        'koordinator' => post('koordinator'),
        'hp' => normalize_hp(post('hp')),
        'email' => post('email'),
    ];
    if ($d['koordinator'] === '' || valid_hp($d['hp']) === null) {
        throw new UserError('Isi nama PIC dan No. HP/WA (hanya angka, 10–15 digit, diawali 0 atau 62).');
    }
    $d['hp'] = valid_hp($d['hp']);
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        throw new UserError('Format email tidak valid.');
    }
    q('UPDATE instansi SET koordinator = ?, hp = ?, email = ? WHERE id = ?', [$d['koordinator'], $d['hp'], $d['email'] ?: null, $inst['id']]);
    log_riwayat((int) $inst['id'], 'ubah_instansi', 'Mengubah data PIC instansi: ' . $d['koordinator'] . ' (' . $d['hp'] . ')');
    flash('ok', 'Data PIC instansi disimpan.');
    redirect('/saya/peserta');
}

function inst_tambah_peserta(): void
{
    $inst = require_instansi();
    guard_peserta_edit();
    $tanggal = post('tanggal');
    $milik = instansi_tanggal((int) $inst['id']);
    if (!in_array($tanggal, event_dates(), true) || ($milik !== null && $tanggal !== $milik)) {
        throw new UserError('Satu instansi hanya ikut satu tanggal' . ($milik ? ' (' . tgl($milik) . ')' : '') . '.');
    }
    $p = clean_peserta($_POST, 'Peserta baru');
    check_kuota((int) $inst['id'], $tanggal, 1);
    q('INSERT INTO peserta (instansi_id, tanggal, nama, nip, jabatan, hp, jk, menginap, bagikan_kontak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $inst['id'], $tanggal, $p['nama'], $p['nip'] ?: null, $p['jabatan'] ?: null, $p['hp'], $p['jk'], $p['menginap'], $p['bagikan_kontak'],
    ]);
    log_riwayat((int) $inst['id'], 'tambah', 'Menambah peserta ' . $p['nama'] . ' (' . tgl($tanggal) . ')');
    flash('ok', $p['nama'] . ' ditambahkan.');
    redirect('/saya/peserta');
}

function inst_ubah_peserta(string $id): void
{
    $inst = require_instansi();
    guard_peserta_edit();
    $old = own_peserta($inst, $id);
    $p = clean_peserta($_POST, 'Peserta');
    if (mb_strtolower($p['nama']) !== mb_strtolower($old['nama'])) {
        throw new UserError('Untuk mengganti orang, gunakan tombol "Ganti peserta". Ubah data hanya untuk memperbaiki penulisan.');
    }
    // Batal menginap atau jenis kelamin berubah: lepas dari kamar, panitia atur ulang.
    $lepasKamar = !$p['menginap'] || $p['jk'] !== $old['jk'];
    q('UPDATE peserta SET nama = ?, nip = ?, jabatan = ?, hp = ?, jk = ?, menginap = ?, bagikan_kontak = ?, kamar_id = ' . ($lepasKamar ? 'NULL' : 'kamar_id') . ', shuttle_id = ' . ($p['menginap'] ? 'shuttle_id' : 'NULL') . ' WHERE id = ?', [
        $p['nama'], $p['nip'] ?: null, $p['jabatan'] ?: null, $p['hp'], $p['jk'], $p['menginap'], $p['bagikan_kontak'], $old['id'],
    ]);
    $changes = [];
    foreach (['nama' => 'nama', 'nip' => 'NIP', 'jabatan' => 'jabatan', 'hp' => 'HP', 'jk' => 'jenis kelamin', 'menginap' => 'menginap'] as $k => $label) {
        if ((string) $old[$k] !== (string) ($p[$k] ?? '')) {
            $changes[] = $label;
        }
    }
    if ($changes) {
        log_riwayat((int) $inst['id'], 'ubah', 'Mengubah data ' . $p['nama'] . ': ' . implode(', ', $changes));
    }
    flash('ok', 'Data ' . $p['nama'] . ' disimpan.');
    redirect('/saya/peserta');
}

function inst_ganti_peserta(string $id): void
{
    $inst = require_instansi();
    guard_peserta_edit();
    $old = own_peserta($inst, $id);
    $p = clean_peserta($_POST, 'Peserta pengganti');
    db()->beginTransaction();
    q('INSERT INTO peserta (instansi_id, tanggal, nama, nip, jabatan, hp, jk, menginap, bagikan_kontak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $inst['id'], $old['tanggal'], $p['nama'], $p['nip'] ?: null, $p['jabatan'] ?: null, $p['hp'], $p['jk'], $p['menginap'], $p['bagikan_kontak'],
    ]);
    $newId = (int) db()->lastInsertId();
    q('UPDATE peserta SET aktif = 0, diganti_oleh = ? WHERE id = ?', [$newId, $old['id']]);
    log_riwayat((int) $inst['id'], 'ganti', $old['nama'] . ' diganti oleh ' . $p['nama'] . ' (' . tgl($old['tanggal']) . ')');
    db()->commit();
    flash('ok', $old['nama'] . ' diganti oleh ' . $p['nama'] . '.');
    redirect('/saya/peserta');
}

function inst_hapus_peserta(string $id): void
{
    $inst = require_instansi();
    guard_peserta_edit();
    $old = own_peserta($inst, $id);
    q('UPDATE peserta SET aktif = 0 WHERE id = ?', [$old['id']]);
    log_riwayat((int) $inst['id'], 'batal', $old['nama'] . ' batal ikut (' . tgl($old['tanggal']) . ')');
    flash('ok', $old['nama'] . ' dibatalkan.');
    redirect('/saya/peserta');
}

// ---------- foto evidence ----------

function inst_dates(int $instansiId): array
{
    $t = instansi_tanggal($instansiId);
    return $t ? [$t] : [];
}

function inst_foto(): void
{
    $inst = require_instansi();
    $foto = [];
    foreach (rows('SELECT * FROM evidence WHERE instansi_id = ? ORDER BY tanggal, uploaded_at', [$inst['id']]) as $f) {
        $foto[$f['tanggal']][$f['tahap']][] = $f;
    }
    render('instansi/foto', 'Foto kegiatan', [
        'inst' => $inst,
        'dates' => inst_dates((int) $inst['id']),
        'foto' => $foto,
        'canUpload' => can_upload(),
    ], ['inst' => $inst, 'tab' => 'foto', 'scripts' => ['upload.js']]);
}

function inst_upload_foto(): void
{
    $inst = require_instansi();
    if (!can_upload()) {
        throw new UserError('Batas upload sudah lewat.');
    }
    $tanggal = post('tanggal');
    $tahap = post('tahap');
    if ($tanggal !== instansi_tanggal((int) $inst['id']) || !isset(TAHAP[$tahap])) {
        throw new UserError('Pilih tanggal dan tahap foto.');
    }
    if (post('watermark') !== '1') {
        throw new UserError('Centang konfirmasi bahwa foto sudah ber-watermark tanggal, jam, dan lokasi.');
    }
    $file = $_FILES['foto'] ?? [];
    if ((int) ($file['size'] ?? 0) > MAX_EVIDENCE) {
        throw new UserError('Foto maksimal 1 MB (file ini ' . ukuran($file['size']) . '). Kecilkan foto dulu lalu unggah lagi.');
    }
    // Watermark dibuat peserta (aplikasi kamera). Sistem hanya mencatat waktu & GPS dari EXIF
    // file asli sebagai data pendukung; file disimpan ulang tanpa diubah isinya selain kompresi.
    $meta = ['taken_at' => null, 'lat' => null, 'lon' => null];
    if (!empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $meta = array_merge($meta, read_exif_meta($file['tmp_name']));
    }
    $saved = store_upload($file, (int) $inst['id'], 'evidence', false);
    q('INSERT INTO evidence (instansi_id, tanggal, tahap, file, mime, size, taken_at, lat, lon, exif_ok) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $inst['id'], $tanggal, $tahap, $saved['file'], $saved['mime'], $saved['size'],
        $meta['taken_at'], $meta['lat'], $meta['lon'], $meta['taken_at'] !== null ? 1 : 0,
    ]);
    if (wants_json()) {
        json_ok(['exif' => $meta['taken_at'] !== null]);
    }
    flash('ok', 'Foto ' . TAHAP[$tahap] . ' terunggah.');
    redirect('/saya/foto');
}

function inst_hapus_foto(string $id): void
{
    $inst = require_instansi();
    if (!can_upload()) {
        throw new UserError('Batas upload sudah lewat.');
    }
    $f = row('SELECT * FROM evidence WHERE id = ? AND instansi_id = ?', [(int) $id, $inst['id']]);
    if (!$f) {
        throw new UserError('Foto tidak ditemukan.');
    }
    q('DELETE FROM evidence WHERE id = ?', [$f['id']]);
    delete_upload($f['file']);
    log_riwayat((int) $inst['id'], 'hapus_foto', 'Menghapus foto ' . TAHAP[$f['tahap']] . ' (' . tgl($f['tanggal']) . ')');
    flash('ok', 'Foto dihapus.');
    redirect('/saya/foto');
}

// ---------- reimburse ----------

/** Total reimburse instansi: diajukan (semua struk), disetujui, sudah dibayar, sisa. */
function reimburse_totals(int $instansiId): array
{
    $b = row("SELECT COALESCE(SUM(nominal), 0) diajukan,
                     COALESCE(SUM(CASE WHEN status = 'disetujui' THEN COALESCE(nominal_disetujui, nominal) ELSE 0 END), 0) disetujui
              FROM biaya WHERE instansi_id = ?", [$instansiId]);
    $dibayar = (int) row('SELECT COALESCE(SUM(nominal), 0) n FROM pembayaran WHERE instansi_id = ?', [$instansiId])['n'];
    return ['diajukan' => (int) $b['diajukan'], 'disetujui' => (int) $b['disetujui'], 'dibayar' => $dibayar, 'sisa' => max(0, (int) $b['disetujui'] - $dibayar)];
}

/** Setelah diverifikasi, status mengikuti pembayaran: belum ada → Diverifikasi, kurang → Dibayar sebagian, lunas → Dibayar. */
function reimburse_recalc(int $instansiId): void
{
    $inst = row('SELECT reimburse_status FROM instansi WHERE id = ?', [$instansiId]);
    if (!$inst || !in_array($inst['reimburse_status'], ['diverifikasi', 'sebagian', 'dibayar'], true)) {
        return;
    }
    $t = reimburse_totals($instansiId);
    $status = $t['dibayar'] <= 0 ? 'diverifikasi' : ($t['dibayar'] < $t['disetujui'] ? 'sebagian' : 'dibayar');
    q('UPDATE instansi SET reimburse_status = ?, dibayar_at = ? WHERE id = ?', [
        $status, $status === 'dibayar' ? date('Y-m-d H:i:s') : null, $instansiId,
    ]);
}

/** Struk boleh ditambah kapan saja sebelum batas upload; setelah diverifikasi/dibayar diajukan sebagai bukti tambahan. */
function biaya_editable(array $inst): bool
{
    return can_upload();
}

/** Rekening hanya bisa diubah sebelum diverifikasi (setelah itu dipakai untuk transfer). */
function rekening_editable(array $inst): bool
{
    return can_upload() && in_array($inst['reimburse_status'], ['draft', 'diajukan'], true);
}

/** Struk yang belum diajukan: semua struk menunggu saat draft, atau struk baru setelah verifikasi. */
function biaya_belum_diajukan(array $inst): int
{
    if ($inst['reimburse_status'] === 'diajukan') {
        return 0;
    }
    return (int) row("SELECT COUNT(*) n FROM biaya WHERE instansi_id = ? AND status = 'menunggu'", [$inst['id']])['n'];
}

function inst_biaya(): void
{
    $inst = require_instansi();
    $items = rows('SELECT * FROM biaya WHERE instansi_id = ? ORDER BY tanggal, id', [$inst['id']]);
    render('instansi/biaya', 'Reimburse', [
        'inst' => $inst,
        'items' => $items,
        'dates' => inst_dates((int) $inst['id']),
        'editable' => biaya_editable($inst),
        'rekeningEditable' => rekening_editable($inst),
        'belumDiajukan' => biaya_belum_diajukan($inst),
    ], ['inst' => $inst, 'tab' => 'biaya', 'scripts' => ['upload.js']]);
}

function inst_rekening(): void
{
    $inst = require_instansi();
    if (!rekening_editable($inst)) {
        throw new UserError('Reimburse sudah diverifikasi; rekening tidak bisa diubah. Hubungi panitia.');
    }
    $d = ['bank' => post('bank'), 'no_rek' => preg_replace('/[^0-9]/', '', post('no_rek')), 'atas_nama' => post('atas_nama')];
    if (in_array('', $d, true)) {
        throw new UserError('Lengkapi nama bank, nomor rekening, dan nama pemilik rekening.');
    }
    q('UPDATE instansi SET bank = ?, no_rek = ?, atas_nama = ? WHERE id = ?', [$d['bank'], $d['no_rek'], $d['atas_nama'], $inst['id']]);
    log_riwayat((int) $inst['id'], 'rekening', 'Rekening: ' . $d['bank'] . ' ' . $d['no_rek'] . ' a.n. ' . $d['atas_nama']);
    flash('ok', 'Rekening disimpan.');
    redirect('/saya/biaya');
}

function inst_tambah_biaya(): void
{
    $inst = require_instansi();
    if (!biaya_editable($inst)) {
        throw new UserError('Batas upload bukti biaya sudah lewat.');
    }
    $tanggal = post('tanggal');
    $jenis = post('jenis');
    $nominal = (int) preg_replace('/[^0-9]/', '', post('nominal'));
    if ($tanggal !== instansi_tanggal((int) $inst['id']) || !isset(JENIS_BIAYA[$jenis])) {
        throw new UserError('Pilih tanggal dan jenis biaya.');
    }
    if ($nominal <= 0) {
        throw new UserError('Isi nominal sesuai struk.');
    }
    if ($jenis === 'lainnya' && post('keterangan') === '') {
        throw new UserError('Untuk biaya "Lainnya", jelaskan di keterangan.');
    }
    $saved = store_upload($_FILES['struk'] ?? [], (int) $inst['id'], 'biaya', true);
    q('INSERT INTO biaya (instansi_id, tanggal, jenis, keterangan, nominal, file, mime, size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $inst['id'], $tanggal, $jenis, post('keterangan') ?: null, $nominal, $saved['file'], $saved['mime'], $saved['size'],
    ]);
    log_riwayat((int) $inst['id'], 'biaya', 'Menambah bukti ' . JENIS_BIAYA[$jenis] . ' ' . rupiah($nominal));
    flash('ok', 'Bukti ' . JENIS_BIAYA[$jenis] . ' ' . rupiah($nominal) . ' ditambahkan.');
    if (wants_json()) {
        json_ok();
    }
    redirect('/saya/biaya');
}

function inst_hapus_biaya(string $id): void
{
    $inst = require_instansi();
    $b = row('SELECT * FROM biaya WHERE id = ? AND instansi_id = ?', [(int) $id, $inst['id']]);
    if (!$b || !biaya_editable($inst) || $b['status'] !== 'menunggu') {
        throw new UserError('Bukti ini tidak bisa dihapus.');
    }
    q('DELETE FROM biaya WHERE id = ?', [$b['id']]);
    delete_upload($b['file']);
    log_riwayat((int) $inst['id'], 'hapus_biaya', 'Menghapus bukti ' . JENIS_BIAYA[$b['jenis']] . ' ' . rupiah($b['nominal']));
    flash('ok', 'Bukti dihapus.');
    redirect('/saya/biaya');
}

/** Ajukan reimburse: pertama kali (draft), atau bukti tambahan setelah diverifikasi/dibayar. */
function inst_ajukan(): void
{
    $inst = require_instansi();
    $st = $inst['reimburse_status'];
    if ($st === 'diajukan') {
        throw new UserError('Reimburse sedang diperiksa panitia. Bukti baru otomatis ikut diperiksa.');
    }
    if (!$inst['bank'] || !$inst['no_rek']) {
        throw new UserError('Isi rekening tujuan transfer terlebih dahulu.');
    }
    $n = biaya_belum_diajukan($inst);
    if ($n === 0) {
        throw new UserError($st === 'draft' ? 'Tambahkan minimal satu bukti biaya.' : 'Tidak ada bukti baru untuk diajukan.');
    }
    q("UPDATE instansi SET reimburse_status = 'diajukan', diajukan_at = NOW() WHERE id = ?", [$inst['id']]);
    $tambahan = $st !== 'draft';
    log_riwayat((int) $inst['id'], 'ajukan', ($tambahan ? 'Mengajukan bukti tambahan' : 'Mengajukan reimburse') . " ($n bukti)");
    flash('ok', ($tambahan ? 'Bukti tambahan diajukan.' : 'Reimburse diajukan.') . ' Panitia akan memverifikasi bukti Anda.');
    redirect('/saya/biaya');
}
