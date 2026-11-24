<?php
// Masukan (feedback) pengguna untuk 6 modul KALCER: form publik, rekap panitia, dan API 👍/👎 untuk Tanya GITA.
declare(strict_types=1);

const PERAN = ['penyuluh' => 'Penyuluh', 'petani' => 'Petani', 'popt' => 'POPT', 'dinas' => 'Dinas', 'peneliti' => 'Peneliti', 'lainnya' => 'Lainnya'];
const SUMBER = ['sesi' => 'Sesi sosialisasi', 'tombol' => 'Tombol di modul', 'langsung' => 'Langsung'];
const SESUAI = ['sesuai' => 'Sesuai', 'sebagian' => 'Sebagian', 'tidak' => 'Tidak sesuai', 'belum' => 'Belum bisa menilai'];
const PAKAI_LAGI = ['ya' => 'Ya', 'mungkin' => 'Mungkin', 'tidak' => 'Tidak'];
/** Butir interface (skala 1–5, boleh "tidak dicoba"). */
const UI_BUTIR = ['ui_menu' => 'Kemudahan menemukan menu', 'ui_peta' => 'Kejelasan peta & legenda', 'ui_cepat' => 'Kecepatan memuat', 'ui_hp' => 'Tampilan di HP'];
/** Butir UMUX-Lite (skala 1–7, setuju). */
const UMUX = ['umux1' => 'KALCER memenuhi kebutuhan saya.', 'umux2' => 'KALCER mudah digunakan.'];

/** URL absolut app event (untuk QR & snippet yang dipakai di situs lain). */
function event_url_abs(string $path = ''): string
{
    $base = (string) config('base_url');
    if (!preg_match('#^https?://#', $base)) {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'kalcer.agroklimat.id') . $base;
    }
    return $base . $path;
}

/** Alamat pendek form masukan di root situs: kalcer.agroklimat.id/masukan (dialihkan ke /event/masukan). */
function masukan_url_pendek(string $query = ''): string
{
    return preg_replace('#/event$#', '', event_url_abs()) . '/masukan' . ($query !== '' ? '?' . $query : '');
}

function ip_hash(): string
{
    return hash('sha256', 'kalcer-ip|' . client_ip() . '|' . (string) config('setup_key'));
}

/** Modul dari ?modul=banjir,opt (hanya slug yang dikenal). */
function modul_dari_query(string $q): array
{
    return array_values(array_intersect(array_keys(MODUL), array_map('trim', explode(',', strtolower($q)))));
}

function skala(string $v, int $max): ?int
{
    return ctype_digit($v) && (int) $v >= 1 && (int) $v <= $max ? (int) $v : null;
}

function masukan_form(array $old = [], array $errors = []): void
{
    $inst = current_instansi();
    $src = (string) ($old['sumber'] ?? $_GET['src'] ?? 'langsung');
    render('publik/masukan', 'Beri masukan', [
        'old' => $old,
        'errors' => $errors,
        'pilih' => $old ? array_values(array_intersect(array_keys(MODUL), (array) ($old['modul'] ?? []))) : modul_dari_query((string) ($_GET['modul'] ?? '')),
        'sumber' => isset(SUMBER[$src]) ? $src : 'langsung',
        'inst' => $inst,
        'stamp' => form_stamp(),
    ], ['bare' => true, 'narrow' => true]);
}

function masukan_kirim(): void
{
    $old = $_POST;
    try {
        if (post('website') !== '' || !form_stamp_ok(post('_stamp'))) {
            throw new UserError('Formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        if ((int) row('SELECT COUNT(*) n FROM masukan WHERE ip_hash = ? AND created_at > NOW() - INTERVAL 10 MINUTE', [ip_hash()])['n'] >= 5) {
            throw new UserError('Terlalu banyak kiriman dari perangkat ini. Coba lagi 10 menit lagi.');
        }
        $inst = current_instansi();
        $d = [
            'sumber' => isset(SUMBER[post('sumber')]) ? post('sumber') : 'langsung',
            'nama' => mb_substr(post('nama'), 0, 150),
            'instansi' => $inst ? $inst['nama'] : mb_substr(post('instansi'), 0, 200),
            'peran' => post('peran'),
            'provinsi' => post('provinsi'),
            'umux1' => skala(post('umux1'), 7),
            'umux2' => skala(post('umux2'), 7),
            'pakai_lagi' => isset(PAKAI_LAGI[post('pakai_lagi')]) ? post('pakai_lagi') : null,
            'saran' => mb_substr(post('saran'), 0, 3000),
            'bersedia' => post('bersedia') === '1' ? 1 : 0,
            'hp' => null,
        ];
        if ($d['instansi'] === '') {
            throw new UserError('Isi nama instansi.');
        }
        if (!isset(PERAN[$d['peran']])) {
            throw new UserError('Pilih peran Anda.');
        }
        if (!in_array($d['provinsi'], provinsi_list(), true)) {
            throw new UserError('Pilih provinsi.');
        }
        if ($d['bersedia']) {
            $d['hp'] = valid_hp(normalize_hp(post('hp')));
            if ($d['hp'] === null) {
                throw new UserError('Isi No. HP/WA (angka saja, 10–15 digit) agar tim bisa menghubungi Anda, atau hapus centang "Bersedia dihubungi".');
            }
        }
        $pilih = array_values(array_intersect(array_keys(MODUL), (array) ($_POST['modul'] ?? [])));
        if (!$pilih) {
            throw new UserError('Pilih minimal satu modul yang Anda coba.');
        }
        $nilai = [];
        foreach ($pilih as $slug) {
            $m = (array) ($_POST['m'][$slug] ?? []);
            $g = static fn (string $k) => trim((string) ($m[$k] ?? ''));
            $r = [
                'modul' => $slug,
                'sesuai' => isset(SESUAI[$g('sesuai')]) ? $g('sesuai') : null,
                'diterapkan' => skala($g('diterapkan'), 5),
                'data_kurang' => mb_substr($g('data_kurang'), 0, 3000),
                'bingung' => mb_substr($g('bingung'), 0, 3000),
                'gita_salah' => $slug === 'gita' ? mb_substr($g('gita_salah'), 0, 3000) : '',
            ];
            foreach (array_keys(UI_BUTIR) as $k) {
                $r[$k] = skala($g($k), 5);
            }
            if ($r['sesuai'] === null) {
                throw new UserError(MODUL[$slug]['nama'] . ': jawab pertanyaan "' . MODUL[$slug]['substansi'] . '"');
            }
            if (count(array_filter(array_keys(UI_BUTIR), static fn ($k) => $r[$k] !== null)) === 0) {
                throw new UserError(MODUL[$slug]['nama'] . ': nilai minimal satu butir tampilan (interface).');
            }
            $nilai[] = $r;
        }

        db()->beginTransaction();
        q('INSERT INTO masukan (sumber, nama, instansi, peran, provinsi, event_instansi_id, umux1, umux2, pakai_lagi, saran, bersedia_dihubungi, hp, ip_hash)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $d['sumber'], $d['nama'] ?: null, $d['instansi'], $d['peran'], $d['provinsi'], $inst['id'] ?? null,
            $d['umux1'], $d['umux2'], $d['pakai_lagi'], $d['saran'] ?: null, $d['bersedia'], $d['hp'], ip_hash(),
        ]);
        $id = (int) db()->lastInsertId();
        foreach ($nilai as $r) {
            q('INSERT INTO masukan_modul (masukan_id, modul, sesuai, diterapkan, data_kurang, ui_menu, ui_peta, ui_cepat, ui_hp, bingung, gita_salah)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $r['modul'], $r['sesuai'], $r['diterapkan'], $r['data_kurang'] ?: null,
                $r['ui_menu'], $r['ui_peta'], $r['ui_cepat'], $r['ui_hp'], $r['bingung'] ?: null, $r['gita_salah'] ?: null,
            ]);
        }
        if ($inst) {
            log_riwayat((int) $inst['id'], 'masukan', 'Mengisi masukan KALCER untuk ' . implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'], $pilih)));
        }
        db()->commit();
        $_SESSION['masukan_ok'] = ['modul' => $pilih, 'sumber' => $d['sumber']];
        redirect('/masukan/terima-kasih');
    } catch (UserError $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $old['sumber'] = post('sumber');
        masukan_form($old, [$e->getMessage()]);
    }
}

function masukan_terima(): void
{
    $ok = $_SESSION['masukan_ok'] ?? null;
    if (!$ok) {
        redirect('/masukan');
    }
    render('publik/masukan_terima', 'Terima kasih', ['ok' => $ok], ['bare' => true, 'narrow' => true]);
}

// ---------- panitia ----------

function admin_masukan(): void
{
    require_admin();
    $modul = (string) ($_GET['modul'] ?? '');
    $sumber = (string) ($_GET['sumber'] ?? '');
    $w = ['1=1'];
    $p = [];
    if (isset(SUMBER[$sumber])) {
        $w[] = 'm.sumber = ?';
        $p[] = $sumber;
    }
    $where = implode(' AND ', $w);
    $total = row("SELECT COUNT(*) n, AVG(m.umux1) u1, AVG(m.umux2) u2, SUM(m.pakai_lagi = 'ya') lagi, SUM(m.pakai_lagi IS NOT NULL) lagi_n, SUM(m.bersedia_dihubungi) bersedia FROM masukan m WHERE $where", $p);
    $perSumber = array_column(rows("SELECT m.sumber k, COUNT(*) n FROM masukan m WHERE $where GROUP BY m.sumber", $p), 'n', 'k');
    $perPeran = array_column(rows("SELECT m.peran k, COUNT(*) n FROM masukan m WHERE $where GROUP BY m.peran ORDER BY n DESC", $p), 'n', 'k');
    $perModul = [];
    foreach (array_keys(MODUL) as $slug) {
        $perModul[$slug] = row(
            "SELECT COUNT(*) n, SUM(mm.sesuai = 'sesuai') sesuai, SUM(mm.sesuai = 'sebagian') sebagian, SUM(mm.sesuai = 'tidak') tidak, SUM(mm.sesuai = 'belum') belum,
                    AVG(mm.diterapkan) diterapkan, AVG(mm.ui_menu) ui_menu, AVG(mm.ui_peta) ui_peta, AVG(mm.ui_cepat) ui_cepat, AVG(mm.ui_hp) ui_hp
             FROM masukan_modul mm JOIN masukan m ON m.id = mm.masukan_id WHERE mm.modul = ? AND $where",
            array_merge([$slug], $p)
        );
    }
    // Jawaban teks per penilaian modul (terbaru dulu), bisa disaring per modul.
    $wt = $where;
    $pt = $p;
    if (isset(MODUL[$modul])) {
        $wt .= ' AND mm.modul = ?';
        $pt[] = $modul;
    }
    $teks = rows(
        "SELECT mm.*, m.id mid, m.created_at, m.instansi, m.peran, m.provinsi, m.nama, m.sumber, m.saran, m.bersedia_dihubungi, m.hp
         FROM masukan_modul mm JOIN masukan m ON m.id = mm.masukan_id
         WHERE $wt AND (mm.data_kurang IS NOT NULL OR mm.bingung IS NOT NULL OR mm.gita_salah IS NOT NULL OR m.saran IS NOT NULL)
         ORDER BY m.id DESC, mm.id LIMIT 300",
        $pt
    );
    $respons = rows("SELECT m.*, (SELECT GROUP_CONCAT(modul ORDER BY id) FROM masukan_modul WHERE masukan_id = m.id) modul FROM masukan m WHERE $where ORDER BY m.id DESC LIMIT 200", $p);
    $gita = row('SELECT COUNT(*) n, SUM(nilai = 1) suka, SUM(nilai = -1) tidak FROM gita_nilai');
    $gitaBuruk = rows('SELECT * FROM gita_nilai WHERE nilai = -1 ORDER BY id DESC LIMIT 100');
    render('admin/masukan', 'Rekap masukan', compact('total', 'perSumber', 'perPeran', 'perModul', 'teks', 'respons', 'gita', 'gitaBuruk', 'modul', 'sumber'), ['admin' => true]);
}

function admin_masukan_hapus(string $id): void
{
    require_admin();
    q('DELETE FROM masukan WHERE id = ?', [(int) $id]);
    flash('ok', 'Respons dihapus.');
    redirect('/admin/masukan');
}

function admin_gita_hapus(string $id): void
{
    require_admin();
    q('DELETE FROM gita_nilai WHERE id = ?', [(int) $id]);
    flash('ok', 'Penilaian GITA dihapus.');
    redirect('/admin/masukan#gita');
}

function admin_masukan_csv(): void
{
    require_admin();
    $data = [['Waktu', 'ID respons', 'Sumber', 'Nama', 'Instansi', 'Peran', 'Provinsi', 'Kode instansi event', 'Modul', 'Substansi sesuai', 'Bisa diterapkan (1-5)', 'Data kurang',
        'Menu (1-5)', 'Peta & legenda (1-5)', 'Kecepatan (1-5)', 'Tampilan HP (1-5)', 'Membingungkan', 'GITA: jawaban salah',
        'UMUX memenuhi kebutuhan (1-7)', 'UMUX mudah digunakan (1-7)', 'Pakai lagi', 'Saran', 'Bersedia dihubungi', 'HP']];
    foreach (rows('SELECT mm.*, m.created_at, m.sumber, m.nama, m.instansi, m.peran, m.provinsi, m.event_instansi_id, m.umux1, m.umux2, m.pakai_lagi, m.saran, m.bersedia_dihubungi, m.hp FROM masukan_modul mm JOIN masukan m ON m.id = mm.masukan_id ORDER BY m.id, mm.id') as $r) {
        $data[] = [$r['created_at'], $r['masukan_id'], SUMBER[$r['sumber']] ?? $r['sumber'], $r['nama'], $r['instansi'], PERAN[$r['peran']] ?? $r['peran'], $r['provinsi'],
            $r['event_instansi_id'] ? kode_instansi((int) $r['event_instansi_id']) : '', MODUL[$r['modul']]['nama'] ?? $r['modul'], SESUAI[$r['sesuai']] ?? '', $r['diterapkan'], $r['data_kurang'],
            $r['ui_menu'], $r['ui_peta'], $r['ui_cepat'], $r['ui_hp'], $r['bingung'], $r['gita_salah'],
            $r['umux1'], $r['umux2'], PAKAI_LAGI[$r['pakai_lagi']] ?? '', $r['saran'], $r['bersedia_dihubungi'] ? 'Ya' : 'Tidak', $r['hp'] ? "'" . $r['hp'] : ''];
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="masukan-kalcer-' . date('Ymd') . '.csv"');
    echo csv_string($data);
}

function admin_masukan_bagikan(): void
{
    require_admin();
    render('admin/masukan_bagikan', 'QR & tombol modul', [], ['admin' => true, 'scripts' => ['vendor/qrcode.min.js']]);
}

/** Skor UMUX-Lite 0–100 dari rata-rata dua butir skala 1–7. */
function umux_skor(?float $u1, ?float $u2): ?int
{
    return $u1 === null || $u2 === null ? null : (int) round((($u1 - 1) + ($u2 - 1)) / 12 * 100);
}

// ---------- API untuk situs Tanya GITA ----------

const GITA_ORIGIN = 'https://gita.agroklimat.id';

function api_gita_nilai(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    header('Content-Type: application/json');
    header('Vary: Origin');
    if ($origin !== GITA_ORIGIN) {
        http_response_code(403);
        echo json_encode(['error' => 'Origin tidak diizinkan.']);
        return;
    }
    header('Access-Control-Allow-Origin: ' . GITA_ORIGIN);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        return;
    }
    $in = json_decode((string) file_get_contents('php://input'), true);
    $tanya = trim((string) ($in['pertanyaan'] ?? ''));
    $nilai = (int) ($in['nilai'] ?? 0);
    if (!is_array($in) || $tanya === '' || !in_array($nilai, [1, -1], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Wajib: pertanyaan (teks) dan nilai (1 atau -1).']);
        return;
    }
    if ((int) row('SELECT COUNT(*) n FROM gita_nilai WHERE ip_hash = ? AND created_at > NOW() - INTERVAL 10 MINUTE', [ip_hash()])['n'] >= 30) {
        http_response_code(429);
        echo json_encode(['error' => 'Terlalu banyak penilaian. Coba lagi nanti.']);
        return;
    }
    q('INSERT INTO gita_nilai (pertanyaan, jawaban, nilai, catatan, ip_hash) VALUES (?, ?, ?, ?, ?)', [
        mb_substr($tanya, 0, 2000), mb_substr(trim((string) ($in['jawaban'] ?? '')), 0, 20000) ?: null, $nilai,
        mb_substr(trim((string) ($in['catatan'] ?? '')), 0, 2000) ?: null, ip_hash(),
    ]);
    echo json_encode(['ok' => true]);
}
