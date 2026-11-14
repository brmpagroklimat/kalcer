<?php /** @var array $perHari @var array $reimburse @var array $foto @var array $terbaru */ ?>
<h1>Dashboard panitia</h1>
<p class="muted"><?= h(setting('nama_kegiatan')) ?>. Pendaftaran <?= setting('daftar_dibuka') === '1' && before('batas_daftar') ? 'dibuka sampai ' . h(tgl_jam(setting('batas_daftar'))) : '<b>ditutup</b>' ?>.</p>

<?php foreach ($perHari as $d => $s): ?>
  <section class="panel">
    <div class="panel-head"><h2><?= h(tgl($d, true)) ?></h2>
      <div class="inline-actions small">
        <a href="<?= h(url('/admin/instansi?tanggal=' . $d)) ?>">Lihat instansi</a>
        <a href="<?= h(url('/admin/ekspor/peserta.csv?tanggal=' . $d)) ?>">Unduh peserta (CSV)</a>
        <a href="<?= h(url('/admin/ekspor/menginap.csv?tanggal=' . $d)) ?>">Unduh daftar menginap & shuttle (CSV)</a>
      </div>
    </div>
    <div class="stats" style="margin:0">
      <div class="stat"><b><?= (int) $s['instansi'] ?></b><span>instansi</span></div>
      <div class="stat"><b><?= (int) $s['peserta'] ?></b><span>peserta</span></div>
      <div class="stat"><b><?= (int) $s['menginap'] ?></b><span>menginap malam <?= h(malam_menginap($d)) ?></span></div>
      <div class="stat"><b><?= (int) $s['menginap_l'] ?> / <?= (int) $s['menginap_p'] ?></b><span>menginap laki-laki / perempuan</span></div>
    </div>
  </section>
<?php endforeach; ?>

<div class="grid2">
  <section class="panel">
    <div class="panel-head"><h2>Reimburse</h2><a class="small" href="<?= h(url('/admin/ekspor/reimburse.csv')) ?>">Unduh rekap (CSV)</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Status</th><th class="num">Instansi</th><th class="num">Diajukan</th><th class="num">Disetujui</th></tr></thead>
      <tbody>
      <?php foreach ($reimburse as $k => $r): ?>
        <tr><td><a href="<?= h(url('/admin/instansi?status=' . $k)) ?>"><span class="badge <?= h($k) ?>"><?= h(STATUS_REIMBURSE[$k]) ?></span></a></td>
          <td class="num"><?= (int) $r['n'] ?></td><td class="num"><?= h(rupiah($r['total'])) ?></td><td class="num"><?= h(rupiah($r['disetujui'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="small muted" style="margin-top:12px">Foto evidence: <?= (int) $foto['n'] ?> foto, <?= (int) $foto['tanpa_exif'] ?> tanpa timestamp kamera.</p>
  </section>
  <section class="panel">
    <h2>Aktivitas terbaru</h2>
    <?php if (!$terbaru): ?><p class="muted">Belum ada pendaftar.</p><?php endif; ?>
    <ul class="people small">
      <?php foreach ($terbaru as $r): ?>
        <li><a href="<?= h(url('/admin/instansi/' . $r['instansi_id'])) ?>"><?= h($r['nama']) ?></a>: <?= h($r['keterangan']) ?>
          <div class="meta"><?= h(tgl_jam($r['created_at'])) ?> · <?= h($r['oleh']) ?></div></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
