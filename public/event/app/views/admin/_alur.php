<?php
/*
 * Alur kerja panitia (diagram di dashboard).
 * PERBARUI DAFTAR INI setiap kali proses bisnis berubah: tiap fase punya langkah instansi
 * dan langkah panitia. Format langkah: [teks, tautan menu admin atau null, catatan cabang (opsional)].
 */
$fase = [
    [
        'judul' => 'Persiapan',
        'kapan' => 'Sebelum registrasi dibuka',
        'instansi' => [],
        'panitia' => [
            ['Isi nama & tanggal kegiatan, batas waktu, 2 kontak WhatsApp, info instansi (hotel, shuttle, dll.), kapasitas kamar', '/admin/pengaturan'],
            ['Isi link jadwal; unggah materi bila sudah ada', '/admin/konten'],
            ['Bagikan alamat kalcer.agroklimat.id/event ke instansi', null],
        ],
    ],
    [
        'judul' => 'Registrasi',
        'kapan' => 'Sampai batas registrasi',
        'instansi' => [
            ['Registrasi: pilih 1 tanggal, maks. 3 peserta, pilih menginap', null],
            ['Simpan link instansi (dikirim ke WA/email)', null],
        ],
        'panitia' => [
            ['Pantau pendaftar dan kuota', '/admin/rekap/peserta'],
            ['Link instansi hilang → Terbitkan link baru', '/admin/instansi', 'Link lama otomatis tidak berlaku'],
            ['Perlu ubah data instansi → Buka sebagai instansi', '/admin/instansi'],
        ],
    ],
    [
        'judul' => 'Menjelang acara',
        'kapan' => 'Sampai batas ganti peserta',
        'instansi' => [
            ['Ubah, ganti, atau batalkan peserta', null],
        ],
        'panitia' => [
            ['Kamar 1: masukkan nomor kamar dari hotel', '/admin/kamar'],
            ['Kamar 2: tambah tamu panitia/pejabat bila ada → Bagi otomatis → periksa', '/admin/kamar', 'Ubah lewat dropdown; ditolak bila penuh/beda jenis kelamin'],
            ['Kamar 3: publikasikan kamar yang sudah final', '/admin/kamar'],
            ['Kirim daftar menginap & shuttle ke hotel', '/admin/rekap/menginap'],
        ],
    ],
    [
        'judul' => 'Hari H',
        'kapan' => '2 & 3 Desember',
        'instansi' => [
            ['Unggah foto ber-watermark (tanggal, jam, lokasi) untuk 5 tahap: berangkat → tiba kembali', null],
        ],
        'panitia' => [
            ['Pantau foto per tahap; ingatkan instansi yang belum lengkap via WhatsApp', '/admin/rekap/foto'],
            ['Unggah dokumentasi ke galeri', '/admin/konten'],
        ],
    ],
    [
        'judul' => 'Reimburse',
        'kapan' => 'Sampai batas upload',
        'instansi' => [
            ['Unggah struk + isi rekening → Ajukan reimburse', null],
            ['Struk terlewat → tambah → Ajukan bukti tambahan', null],
        ],
        'panitia' => [
            ['Periksa tiap struk: setujui (nominal bisa dikoreksi) atau tolak + alasan', '/admin/rekap/reimburse', 'Perlu diperbaiki instansi → Kembalikan ke instansi'],
            ['Tandai diverifikasi', '/admin/rekap/reimburse'],
            ['Catat pembayaran + unggah bukti transfer (bisa dicicil)', '/admin/rekap/reimburse', 'Status otomatis: Dibayar sebagian → Dibayar'],
        ],
    ],
    [
        'judul' => 'Laporan',
        'kapan' => 'Setelah semua lunas',
        'instansi' => [],
        'panitia' => [
            ['Unduh rekap CSV: peserta, menginap, reimburse', '/admin/rekap'],
            ['Unduh ZIP foto & struk per instansi untuk laporan', '/admin/instansi'],
            ['Unggah materi & galeri akhir', '/admin/konten'],
        ],
    ],
];

$langkah = static function (array $s, string $peran): string {
    [$teks, $href] = $s;
    $isi = '<span class="alur-teks">' . h($teks) . ($href ? ' <span class="go" aria-hidden="true">›</span>' : '') . '</span>';
    if (!empty($s[2])) {
        $isi .= '<span class="alur-cabang">↳ ' . h($s[2]) . '</span>';
    }
    return '<li class="alur-step ' . $peran . '">' . ($href ? '<a href="' . h(url($href)) . '">' . $isi . '</a>' : '<div>' . $isi . '</div>') . '</li>';
};
?>
<section class="panel alur" id="alur">
  <h2>Alur kerja panitia</h2>
  <p class="small muted">Urutan proses dari persiapan sampai laporan. Klik langkah panitia untuk membuka menunya.</p>
  <div class="alur-legend small"><span class="chip-i">Instansi</span><span class="chip-p">Panitia</span><span>↳ cabang / pengecualian</span></div>
  <div class="alur-cols small" aria-hidden="true"><span>Fase</span><span>Instansi</span><span>Panitia</span></div>
  <ol class="alur-fase">
    <?php foreach ($fase as $i => $f): ?>
      <li class="fase">
        <div class="fase-head"><span class="fase-num"><?= $i + 1 ?></span><span><b><?= h($f['judul']) ?></b><span class="small muted"><?= h($f['kapan']) ?></span></span></div>
        <div class="lane lane-i" aria-label="Instansi">
          <?php if ($f['instansi']): ?><ol><?php foreach ($f['instansi'] as $s) { echo $langkah($s, 'i'); } ?></ol>
          <?php else: ?><span class="lane-kosong">—</span><?php endif; ?>
        </div>
        <div class="lane lane-p" aria-label="Panitia">
          <ol><?php foreach ($f['panitia'] as $s) { echo $langkah($s, 'p'); } ?></ol>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
