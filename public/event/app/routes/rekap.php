<?php
// Rekap untuk panitia (dilihat langsung di web): peserta, menginap & shuttle, foto, reimburse.
declare(strict_types=1);

const REKAP_TABS = [
    'peserta' => 'Peserta',
    'menginap' => 'Menginap & shuttle',
    'foto' => 'Foto dokumentasi',
    'reimburse' => 'Reimburse',
];

function rekap_tanggal(): string
{
    $t = (string) ($_GET['tanggal'] ?? '');
    return in_array($t, event_dates(), true) ? $t : '';
}

function admin_rekap(string $jenis = 'peserta'): void
{
    require_admin();
    if (!isset(REKAP_TABS[$jenis])) {
        not_found();
    }
    $tanggal = rekap_tanggal();
    $vars = ['jenis' => $jenis, 'tanggal' => $tanggal];
    $tglSql = $tanggal !== '' ? ' AND p.tanggal = ?' : '';
    $tglArg = $tanggal !== '' ? [$tanggal] : [];

    if ($jenis === 'menginap') {
        $vars['rows'] = [];
        foreach ($tanggal !== '' ? [$tanggal] : event_dates() as $d) {
            $orang = penghuni_malam($d);
            $rooms = kamar_list($d, $orang);
            foreach ($orang as $o) {
                $k = $rooms[$o['kamar_id']] ?? null;
                $vars['rows'][] = $o + ['tanggal' => $d, 'kamar' => $k ? ($k['nomor'] ?: 'tanpa nomor') : null];
            }
        }
        usort($vars['rows'], static fn ($a, $b) => [$a['tanggal'], $a['kamar'] === null, $a['kamar'], $a['jk']] <=> [$b['tanggal'], $b['kamar'] === null, $b['kamar'], $b['jk']]);
    } elseif ($jenis === 'peserta') {
        $vars['rows'] = rows(
            'SELECT p.*, i.nama instansi, i.kabkota FROM peserta p JOIN instansi i ON i.id = p.instansi_id
             WHERE p.aktif = 1' . ($jenis === 'menginap' ? ' AND p.menginap = 1' : '') . $tglSql .
            ' ORDER BY p.tanggal, ' . ($jenis === 'menginap' ? 'p.jk, ' : '') . 'i.nama, p.id',
            $tglArg
        );
    } elseif ($jenis === 'foto') {
        $list = rows(
            'SELECT i.id, i.nama, i.koordinator, i.hp, p.tanggal FROM instansi i
             JOIN (SELECT instansi_id, MIN(tanggal) tanggal FROM peserta WHERE aktif = 1 GROUP BY instansi_id) p ON p.instansi_id = i.id
             WHERE 1=1' . $tglSql . ' ORDER BY p.tanggal, i.nama',
            $tglArg
        );
        $counts = [];
        foreach (rows('SELECT instansi_id, tahap, COUNT(*) n, SUM(exif_ok = 0) tanpa_exif, MAX(uploaded_at) terakhir FROM evidence GROUP BY instansi_id, tahap') as $r) {
            $counts[$r['instansi_id']][$r['tahap']] = $r;
        }
        $status = (string) ($_GET['status'] ?? '');
        $vars['rows'] = array_values(array_filter(array_map(static function ($i) use ($counts) {
            $c = $counts[$i['id']] ?? [];
            $i['tahap'] = array_map(static fn ($k) => (int) ($c[$k]['n'] ?? 0), array_combine(array_keys(TAHAP), array_keys(TAHAP)));
            $i['lengkap'] = count(array_filter($i['tahap'])) === count(TAHAP);
            $i['total'] = array_sum($i['tahap']);
            $i['tanpa_exif'] = array_sum(array_map(static fn ($r) => (int) $r['tanpa_exif'], $c));
            $i['terakhir'] = $c ? max(array_column($c, 'terakhir')) : null;
            return $i;
        }, $list), static fn ($i) => $status === '' || ($status === 'lengkap') === $i['lengkap']));
        $vars['status'] = $status;
    } else {
        $status = (string) ($_GET['status'] ?? '');
        $vars['status'] = isset(STATUS_REIMBURSE[$status]) ? $status : '';
        $vars['rows'] = rows(
            "SELECT i.*, COUNT(b.id) n,
                    SUM(b.status = 'menunggu') menunggu, SUM(b.status = 'disetujui') ok, SUM(b.status = 'ditolak') tolak,
                    COALESCE(SUM(b.nominal), 0) total,
                    COALESCE(SUM(CASE WHEN b.status = 'disetujui' THEN COALESCE(b.nominal_disetujui, b.nominal) ELSE 0 END), 0) disetujui
             FROM instansi i LEFT JOIN biaya b ON b.instansi_id = i.id" .
            ($vars['status'] !== '' ? ' WHERE i.reimburse_status = ?' : '') .
            " GROUP BY i.id ORDER BY FIELD(i.reimburse_status, 'diajukan', 'diverifikasi', 'draft', 'dibayar'), i.nama",
            $vars['status'] !== '' ? [$vars['status']] : []
        );
    }
    render('admin/rekap', 'Rekap ' . strtolower(REKAP_TABS[$jenis]), $vars, ['admin' => true]);
}
