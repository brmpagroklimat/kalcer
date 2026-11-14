<?php /** @var array $inst @var ?string $tanggal @var array $peserta @var array $inap @var array $akomodasi @var array $tahapAda @var array $reimburse @var array $riwayat */
$st = $inst['reimburse_status'];
$nKamar = count(array_filter($akomodasi, static fn ($a) => $a['kamar']));
$nShuttle = count(array_filter($akomodasi, static fn ($a) => $a['shuttle']));
$nFoto = count($tahapAda);
/*
 * Alur KHUSUS instansi. Bila proses bisnis berubah, perbarui juga langkah ini
 * (dan diagram panitia di app/views/admin/_alur.php).
 */
$langkah = [
    ['Registrasi', 'Kode ' . kode_instansi((int) $inst['id']) . ($tanggal ? ', ' . tgl($tanggal, true) : ''), true, null],
    ['Data peserta', count($peserta) . ' peserta, ' . count($inap) . ' menginap. Bisa diubah/diganti sampai ' . tgl_jam(setting('batas_ganti')) . '.', count($peserta) > 0, '/saya/peserta'],
    ['Kamar & shuttle', $inap ? "Info kamar: $nKamar dari " . count($inap) . " peserta; shuttle: $nShuttle dari " . count($inap) . ' (diumumkan panitia).' : 'Tidak ada peserta yang menginap.', !$inap || ($nKamar === count($inap) && $nShuttle === count($inap)), $inap ? '#akomodasi' : null],
    ['Foto kegiatan (Hari H)', "$nFoto dari " . count(TAHAP) . ' tahap terunggah. Foto wajib ber-watermark tanggal, jam, lokasi.', $nFoto === count(TAHAP), '/saya/foto'],
    ['Reimburse', STATUS_REIMBURSE[$st] . ($reimburse['diajukan'] ? ': diajukan ' . rupiah($reimburse['diajukan']) . ', disetujui ' . rupiah($reimburse['disetujui']) . ', dibayar ' . rupiah($reimburse['dibayar']) : ': unggah struk setelah acara') . '.', $st === 'dibayar', '/saya/biaya'],
];
$sekarang = null;
foreach ($langkah as $i => $l) {
    if (!$l[2]) { $sekarang = $i; break; }
}
$mobil = [];
foreach ($akomodasi as $a) {
    if ($a['shuttle']) { $mobil[$a['shuttle']['id']] = $a['shuttle']; }
}
$wa = static fn (?string $hp) => $hp ? '<a class="wa-link" href="https://wa.me/' . h(wa_digits($hp)) . '" target="_blank" rel="noopener">WhatsApp</a>' : '';
?>
<h1>Ringkasan</h1>
<p class="lead"><?= h(setting('nama_kegiatan')) ?><?= $tanggal ? ', ' . h(tgl($tanggal, true)) : '' ?><?= setting('lokasi') !== '' ? ', ' . h(setting('lokasi')) : '' ?>. <a href="<?= h(url('/jadwal')) ?>">Lihat jadwal acara</a></p>

<section class="panel">
  <h2>Langkah instansi</h2>
  <ol class="inst-steps">
    <?php foreach ($langkah as $i => [$judul, $ket, $done, $href]): ?>
      <li class="<?= $done ? 'done' : ($i === $sekarang ? 'now' : '') ?>">
        <span class="num" aria-hidden="true"><?= $done ? '✓' : $i + 1 ?></span>
        <span class="isi"><b><?= h($judul) ?></b><?= $i === $sekarang ? ' <span class="badge warn">Langkah berikutnya</span>' : '' ?><span class="small"><?= h($ket) ?></span>
          <?php if ($href): ?><a class="small" href="<?= h(str_starts_with($href, '#') ? $href : url($href)) ?>"><?= str_starts_with($href, '#') ? 'Lihat di bawah' : 'Buka' ?></a><?php endif; ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<?php if ($inap): ?>
<section class="panel" id="akomodasi">
  <h2>Kamar & shuttle</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Peserta</th><th>Kamar (malam <?= $tanggal ? h(malam_menginap($tanggal)) : '' ?>)</th><th>Shuttle hotel → lokasi</th></tr></thead>
    <tbody>
    <?php foreach ($inap as $p): $a = $akomodasi[$p['id']]; ?>
      <tr><td><b><?= h($p['nama']) ?></b></td>
        <td><?php if ($a['kamar']): ?><b>Kamar <?= h($a['kamar']['nomor']) ?></b><?= $a['kamar']['teman'] ? '<br><span class="small">Sekamar: ' . h(implode(', ', array_map(static fn ($t) => $t['nama'] . ' (' . $t['instansi'] . ')', $a['kamar']['teman']))) . '</span>' : '<br><span class="small muted">Sendiri</span>' ?>
          <?php else: ?><span class="muted">Menunggu pembagian panitia</span><?php endif; ?></td>
        <td><?php if ($a['shuttle']): $k = $a['shuttle']; ?><b><?= h($k['nama']) ?></b><?= $k['plat'] ? ' (' . h($k['plat']) . ')' : '' ?><?= $k['jam'] ? ', ' . h(jam($k['jam'])) : '' ?><br><span class="small"><?= h($k['titik_jemput'] ?: '') ?></span>
          <?php else: ?><span class="muted">Menunggu pembagian panitia</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($mobil): ?>
    <div class="cards-mini">
      <?php foreach ($mobil as $k): ?>
        <div class="mini"><b><?= h($k['nama']) ?></b><?= $k['plat'] ? ' <span class="chip">' . h($k['plat']) . '</span>' : '' ?>
          <span class="small">Berangkat <?= $k['jam'] ? h(jam($k['jam'])) : '(jam menyusul)' ?><?= $k['titik_jemput'] ? ' dari ' . h($k['titik_jemput']) : '' ?></span>
          <?php if ($k['supir_nama'] || $k['supir_hp']): ?><span class="small">Supir: <?= h($k['supir_nama'] ?: '-') ?> <?= $wa($k['supir_hp']) ?></span><?php endif; ?>
          <?php if ($k['pic_nama'] || $k['pic_hp']): ?><span class="small">PIC Shuttle: <?= h($k['pic_nama'] ?: '-') ?> <?= $wa($k['pic_hp']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid2">
  <section class="panel">
    <h2>Info penting</h2>
    <?php if ($items = info_items()): ?>
      <dl class="info-list"><?php foreach ($items as $it): ?><dt><?= h($it['judul']) ?></dt><dd class="prewrap"><?= h($it['isi']) ?></dd><?php endforeach; ?></dl>
    <?php else: ?><p class="muted small" style="margin:0">Info hotel, shuttle, dan lainnya akan diumumkan panitia di sini.</p><?php endif; ?>
    <p class="small" style="margin:12px 0 0">Ada pertanyaan? <?= hubungi_panitia('Halo panitia, kami dari ' . $inst['nama'] . ' (' . kode_instansi((int) $inst['id']) . ') ingin bertanya.') ?>.</p>
  </section>
  <section class="panel">
    <h2>Riwayat</h2>
    <?php if (!$riwayat): ?><p class="muted">Belum ada.</p><?php else: ?>
    <ul class="activity" tabindex="0" aria-label="Riwayat, gulir untuk melihat lebih banyak">
      <?php foreach ($riwayat as $r): ?><li><?= h($r['keterangan']) ?><span class="meta"><?= h(tgl_jam($r['created_at'])) ?> · <?= h($r['oleh']) ?></span></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
</div>
