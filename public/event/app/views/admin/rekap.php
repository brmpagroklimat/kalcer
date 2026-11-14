<?php /** @var string $jenis @var string $tanggal @var array $rows */
?>
<h1>Rekap</h1>
<nav class="tabs" aria-label="Jenis rekap" style="margin-bottom:18px">
  <?php foreach (REKAP_TABS as $k => $label): ?>
    <a href="<?= h(url('/admin/rekap/' . $k) . ($tanggal !== '' && $k !== 'reimburse' ? '?tanggal=' . $tanggal : '')) ?>" <?= $jenis === $k ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($jenis !== 'reimburse'): ?>
  <div class="filters">
    <div class="choices" role="group" aria-label="Tanggal">
      <a class="btn small <?= $tanggal === '' ? 'deep' : 'secondary' ?>" href="<?= h(url('/admin/rekap/' . $jenis)) ?>">Semua tanggal</a>
      <?php foreach (event_dates() as $d): ?>
        <a class="btn small <?= $tanggal === $d ? 'deep' : 'secondary' ?>" href="<?= h(url('/admin/rekap/' . $jenis . '?tanggal=' . $d)) ?>"><?= h(tgl($d, true)) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($jenis === 'peserta'):
  $l = count(array_filter($rows, static fn ($p) => $p['jk'] === 'L')); $inap = count(array_filter($rows, static fn ($p) => $p['menginap']));
?>
  <div class="panel-head"><p class="muted" style="margin:0"><?= count($rows) ?> peserta (<?= $l ?> laki-laki, <?= count($rows) - $l ?> perempuan), <?= $inap ?> menginap, dari <?= count(array_unique(array_column($rows, 'instansi_id'))) ?> instansi.</p>
    <a class="btn small secondary" href="<?= h(url('/admin/ekspor/peserta.csv') . ($tanggal ? '?tanggal=' . $tanggal : '')) ?>">Unduh CSV</a></div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Tanggal</th><th>Instansi</th><th>Nama</th><th>Jabatan</th><th>Jenis kelamin</th><th>HP</th><th>Menginap</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $n => $p): ?>
      <tr><td class="num"><?= $n + 1 ?></td><td><?= h(tgl_pendek($p['tanggal'])) ?></td>
        <td><a href="<?= h(url('/admin/instansi/' . $p['instansi_id'])) ?>"><?= h($p['instansi']) ?></a><br><span class="muted small"><?= h(kode_instansi((int) $p['instansi_id'])) ?>, <?= h($p['kabkota']) ?></span></td>
        <td><b><?= h($p['nama']) ?></b><?= $p['nip'] ? '<br><span class="muted small">NIP ' . h($p['nip']) . '</span>' : '' ?></td>
        <td><?= h($p['jabatan']) ?></td><td><?= h(jk_label($p['jk'])) ?></td><td><?= h($p['hp']) ?></td>
        <td><?= $p['menginap'] ? '<span class="badge inap">Ya</span>' : '<span class="muted">Tidak</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted">Belum ada peserta.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

<?php elseif ($jenis === 'menginap'):
  $perMalam = [];
  foreach ($rows as $p) { $perMalam[$p['tanggal']][] = $p; }
?>
  <div class="panel-head"><p class="muted" style="margin:0"><?= count($rows) ?> orang menginap (termasuk tamu panitia). Semua yang menginap ikut shuttle hotel → lokasi pada pagi hari acara. Atur kamar di menu <a href="<?= h(url('/admin/kamar')) ?>">Kamar</a>.</p>
    <a class="btn small secondary" href="<?= h(url('/admin/ekspor/menginap.csv') . ($tanggal ? '?tanggal=' . $tanggal : '')) ?>">Unduh CSV</a></div>
  <?php if (!$rows): ?><p class="muted">Belum ada peserta yang menginap.</p><?php endif; ?>
  <?php foreach ($perMalam as $d => $list): $l = count(array_filter($list, static fn ($p) => $p['jk'] === 'L')); ?>
    <section class="panel">
      <div class="panel-head"><h2>Malam <?= h(malam_menginap($d)) ?></h2><span class="muted small">untuk acara <?= h(tgl($d, true)) ?></span></div>
      <div class="stats">
        <div class="stat"><b><?= count($list) ?></b><span>peserta menginap</span></div>
        <div class="stat"><b><?= $l ?></b><span>laki-laki</span></div>
        <div class="stat"><b><?= count($list) - $l ?></b><span>perempuan</span></div>
        <div class="stat"><b><?= count(array_filter($list, static fn ($p) => $p['kamar'] === null)) ?></b><span>belum dapat kamar</span></div>
      </div>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Kamar</th><th>Nama</th><th>Jenis kelamin</th><th>Instansi / keterangan</th><th>HP</th></tr></thead>
        <tbody>
        <?php foreach ($list as $n => $p): ?>
          <tr><td class="num"><?= $n + 1 ?></td><td><?= $p['kamar'] !== null ? '<b>' . h($p['kamar']) . '</b>' : '<span class="badge warn">Belum</span>' ?></td>
            <td><b><?= h($p['nama']) ?></b><?= $p['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td><td><?= h(jk_label($p['jk'])) ?></td>
            <td><?= $p['instansi_id'] ? '<a href="' . h(url('/admin/instansi/' . $p['instansi_id'])) . '">' . h($p['instansi']) . '</a>' : h($p['instansi']) ?></td><td><?= h($p['hp']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  <?php endforeach; ?>

<?php elseif ($jenis === 'foto'):
  $lengkap = count(array_filter($rows, static fn ($r) => $r['lengkap']));
?>
  <div class="filters">
    <div class="choices" role="group" aria-label="Status foto">
      <?php foreach (['' => 'Semua', 'belum' => 'Belum lengkap', 'lengkap' => 'Lengkap'] as $k => $label): ?>
        <a class="btn small <?= $status === $k ? 'deep' : 'secondary' ?>" href="<?= h(url('/admin/rekap/foto') . '?' . http_build_query(array_filter(['tanggal' => $tanggal, 'status' => $k], 'strlen'))) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted"><?= $lengkap ?> dari <?= count($rows) ?> instansi sudah mengunggah foto di semua tahap. Angka = jumlah foto per tahap.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Instansi</th><th>Tanggal</th><?php foreach (TAHAP as $label): ?><th class="num"><?= h($label) ?></th><?php endforeach; ?><th class="num">Total</th><th>Terakhir unggah</th><th>PIC</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= h(url('/admin/instansi/' . $r['id'])) ?>"><b><?= h($r['nama']) ?></b></a><br><span class="muted small"><?= h(kode_instansi((int) $r['id'])) ?></span>
          <?= $r['lengkap'] ? '<span class="badge ok">Lengkap</span>' : '' ?></td>
        <td><?= h(tgl_pendek($r['tanggal'])) ?></td>
        <?php foreach ($r['tahap'] as $n): ?><td class="num"><?= $n ? '<span class="badge ok">' . $n . '</span>' : '<span class="badge err">0</span>' ?></td><?php endforeach; ?>
        <td class="num"><b><?= $r['total'] ?></b><?= $r['tanpa_exif'] ? '<br><span class="small muted">' . $r['tanpa_exif'] . ' tanpa EXIF</span>' : '' ?></td>
        <td class="small"><?= $r['terakhir'] ? h(tgl_jam($r['terakhir'])) : '<span class="muted">Belum ada</span>' ?></td>
        <td class="small"><?= h($r['koordinator']) ?><br><a href="https://wa.me/<?= h(preg_replace('/^0/', '62', ltrim(preg_replace('/\D/', '', $r['hp']), '+'))) ?>" target="_blank" rel="noopener">WhatsApp</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= count(TAHAP) + 5 ?>" class="muted">Tidak ada instansi.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

<?php else:
  $masuk = array_filter($rows, static fn ($r) => $r['reimburse_status'] !== 'draft');
  $draft = array_filter($rows, static fn ($r) => $r['reimburse_status'] === 'draft' && $r['n'] > 0);
  $sumTotal = array_sum(array_column($masuk, 'total')); $sumOk = array_sum(array_column($masuk, 'disetujui'));
?>
  <details class="panel" style="padding:14px 18px">
    <summary>Arti status reimburse</summary>
    <dl class="kv" style="margin-top:12px">
      <dt><span class="badge draft">Belum diajukan</span></dt><dd>Instansi sedang menyiapkan: struk & rekening bisa sudah diunggah, tetapi tombol <i>Ajukan reimburse</i> belum ditekan. Belum perlu diproses panitia.</dd>
      <dt><span class="badge diajukan">Diajukan</span></dt><dd>Instansi sudah mengajukan. Panitia memeriksa setiap struk: <i>setujui</i> (nominal bisa dikoreksi) atau <i>tolak</i> dengan alasan.</dd>
      <dt><span class="badge diverifikasi">Diverifikasi</span></dt><dd>Semua struk sudah diperiksa; total disetujui sudah final dan menunggu transfer.</dd>
      <dt><span class="badge dibayar">Dibayar</span></dt><dd>Sudah ditransfer ke rekening instansi.</dd>
    </dl>
    <p class="small muted" style="margin:10px 0 0">Status diubah panitia di halaman detail instansi (bagian Reimburse). Mengembalikan ke "Belum diajukan" membuka kembali pengajuan agar instansi bisa memperbaiki.</p>
  </details>
  <div class="filters">
    <div class="choices" role="group" aria-label="Status reimburse">
      <a class="btn small <?= $status === '' ? 'deep' : 'secondary' ?>" href="<?= h(url('/admin/rekap/reimburse')) ?>">Semua</a>
      <?php foreach (STATUS_REIMBURSE as $k => $label): ?>
        <a class="btn small <?= $status === $k ? 'deep' : 'secondary' ?>" href="<?= h(url('/admin/rekap/reimburse?status=' . $k)) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="stats">
    <div class="stat"><b><?= count($masuk) ?></b><span>instansi sudah mengajukan</span></div>
    <div class="stat"><b><?= h(rupiah($sumTotal)) ?></b><span>total diajukan</span></div>
    <div class="stat"><b><?= h(rupiah($sumOk)) ?></b><span>total disetujui</span></div>
    <div class="stat"><b><?= (int) array_sum(array_column($masuk, 'menunggu')) ?></b><span>struk menunggu diperiksa</span></div>
  </div>
  <?php if ($draft): ?><p class="small muted"><?= count($draft) ?> instansi masih menyiapkan (<?= h(rupiah(array_sum(array_column($draft, 'total')))) ?> dalam draft, belum dihitung di atas).</p><?php endif; ?>
  <div class="panel-head"><span></span><a class="btn small secondary" href="<?= h(url('/admin/ekspor/reimburse.csv')) ?>">Unduh CSV</a></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Instansi</th><th>Status</th><th class="num">Bukti</th><th class="num">Diajukan</th><th class="num">Disetujui</th><th>Rekening</th><th>Diajukan pada</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= h(url('/admin/instansi/' . $r['id'] . '#reimburse')) ?>"><b><?= h($r['nama']) ?></b></a><br><span class="muted small"><?= h(kode_instansi((int) $r['id'])) ?>, PIC <?= h($r['koordinator']) ?></span></td>
        <td><span class="badge <?= h($r['reimburse_status']) ?>"><?= h(STATUS_REIMBURSE[$r['reimburse_status']]) ?></span></td>
        <td class="num"><?= (int) $r['n'] ?><?php if ($r['n']): ?><br><span class="small muted"><?= (int) $r['ok'] ?> ok, <?= (int) $r['tolak'] ?> tolak, <?= (int) $r['menunggu'] ?> tunggu</span><?php endif; ?></td>
        <td class="num"><?= $r['reimburse_status'] === 'draft' ? ($r['n'] ? '<span class="muted">' . h(rupiah($r['total'])) . '<br><span class="small">draft</span></span>' : '<span class="muted">-</span>') : h(rupiah($r['total'])) ?></td>
        <td class="num"><?= $r['reimburse_status'] === 'draft' ? '<span class="muted">-</span>' : '<b>' . h(rupiah($r['disetujui'])) . '</b>' ?></td>
        <td class="small"><?= $r['no_rek'] ? h($r['bank'] . ' ' . $r['no_rek']) . '<br>a.n. ' . h($r['atas_nama']) : '<span class="muted">Belum diisi</span>' ?></td>
        <td class="small"><?= $r['diajukan_at'] ? h(tgl_jam($r['diajukan_at'])) : '<span class="muted">-</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">Tidak ada data.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows): ?><tfoot><tr><th colspan="3">Total (tanpa draft)</th><th class="num"><?= h(rupiah($sumTotal)) ?></th><th class="num"><?= h(rupiah($sumOk)) ?></th><th colspan="2"></th></tr></tfoot><?php endif; ?>
  </table></div>
<?php endif; ?>
