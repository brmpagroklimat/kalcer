<?php /** @var array $inst @var array $items @var array $dates @var bool $editable @var bool $rekeningEditable @var int $belumDiajukan */
$tot = reimburse_totals((int) $inst['id']);
$total = $tot['diajukan'];
$disetujui = $tot['disetujui'];
$bayar = rows('SELECT * FROM pembayaran WHERE instansi_id = ? ORDER BY tanggal, id', [$inst['id']]);
$st = $inst['reimburse_status'];
?>
<div class="panel-head">
  <h1 style="margin:0">Reimburse perjalanan</h1>
  <span class="badge <?= h($st) ?>" style="font-size:14px"><?= h(STATUS_REIMBURSE[$st]) ?></span>
</div>
<p class="lead">Biaya BBM, tol, dan kendaraan umum diganti <i>at cost</i> sesuai struk, dikumpulkan per instansi dan ditransfer ke satu rekening.</p>

<?php if ($st === 'diajukan'): ?>
  <p class="note">Diajukan pada <?= h(tgl_jam($inst['diajukan_at'])) ?>. Panitia sedang memeriksa bukti. Anda masih bisa menambah bukti yang terlewat.</p>
<?php elseif ($st === 'diverifikasi'): ?>
  <p class="note">Bukti sudah diverifikasi panitia. Total yang akan ditransfer: <b><?= h(rupiah($disetujui)) ?></b>.</p>
<?php elseif ($st === 'sebagian'): ?>
  <p class="note">Sudah ditransfer <b><?= h(rupiah($tot['dibayar'])) ?></b> dari <?= h(rupiah($disetujui)) ?>. Sisa <b><?= h(rupiah($tot['sisa'])) ?></b> akan ditransfer menyusul.</p>
<?php elseif ($st === 'dibayar'): ?>
  <p class="note">Lunas: <b><?= h(rupiah($tot['dibayar'])) ?></b> sudah ditransfer ke rekening instansi.<?= $editable ? ' Ada struk yang terlewat? Tambahkan di bawah, lalu ajukan sebagai bukti tambahan.' : '' ?></p>
<?php endif; ?>
<?php if ($inst['catatan_panitia']): ?><p class="note warn"><b>Catatan panitia:</b> <?= h($inst['catatan_panitia']) ?></p><?php endif; ?>
<?php if (!can_upload() && $st === 'draft'): ?><p class="note warn">Batas pengajuan sudah lewat (<?= h(tgl_jam(setting('batas_upload'))) ?>). <?= hubungi_panitia('Halo panitia, kami dari ' . $inst['nama'] . ' (' . kode_instansi((int) $inst['id']) . ') ingin bertanya soal reimburse.', 'Hubungi panitia via WhatsApp') ?>.</p><?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>Bukti biaya</h2>
    <div class="totals" style="margin:0"><span>Diajukan <b><?= h(rupiah($total)) ?></b></span><?php if ($st !== 'draft'): ?><span>Disetujui <b><?= h(rupiah($disetujui)) ?></b></span><?php endif; ?></div>
  </div>
  <?php if (!$items): ?><p class="muted">Belum ada bukti. Tambahkan struk satu per satu di bawah.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Tanggal</th><th>Jenis</th><th class="num">Nominal</th><th>Struk</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $b): ?>
      <tr>
        <td><?= h(tgl_pendek($b['tanggal'])) ?></td>
        <td><?= h(JENIS_BIAYA[$b['jenis']]) ?><?= $b['keterangan'] ? '<br><span class="muted small">' . h($b['keterangan']) . '</span>' : '' ?></td>
        <td class="num"><?= h(rupiah($b['nominal'])) ?><?php if ($b['status'] === 'disetujui' && $b['nominal_disetujui'] !== null && (int) $b['nominal_disetujui'] !== (int) $b['nominal']): ?><br><span class="small">disetujui <?= h(rupiah($b['nominal_disetujui'])) ?></span><?php endif; ?></td>
        <td><a href="<?= h(url('/file/b/' . $b['id'])) ?>" target="_blank" rel="noopener">Lihat</a></td>
        <td><?php $baru = $b['status'] === 'menunggu' && $st !== 'diajukan'; ?><span class="badge <?= $baru ? 'draft' : h($b['status']) ?>"><?= $baru ? 'Belum diajukan' : h(ucfirst($b['status'])) ?></span><?= $b['catatan'] ? '<br><span class="small">' . h($b['catatan']) . '</span>' : '' ?></td>
        <td><?php if ($editable && $b['status'] === 'menunggu'): ?>
          <form method="post" action="<?= h(url('/saya/biaya/' . $b['id'] . '/hapus')) ?>" data-confirm="Hapus bukti ini?"><?= csrf_field() ?><button class="linkbtn" style="color:var(--err-ink)">Hapus</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if ($editable && $dates): ?>
    <h3 style="margin-top:22px">Tambah bukti</h3>
    <form class="js-receipt" method="post" enctype="multipart/form-data" action="<?= h(url('/saya/biaya')) ?>"><?= csrf_field() ?>
      <div class="fields three">
        <label class="field"><span>Jenis biaya</span>
          <select name="jenis" required><option value="">Pilih…</option>
            <?php foreach (JENIS_BIAYA as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?>
          </select></label>
        <label class="field"><span>Tanggal kegiatan</span>
          <select name="tanggal" required>
            <?php foreach ($dates as $d): ?><option value="<?= h($d) ?>"><?= h(tgl($d)) ?></option><?php endforeach; ?>
          </select></label>
        <label class="field"><span>Nominal (Rp)</span><input type="text" name="nominal" inputmode="numeric" placeholder="150000" required></label>
      </div>
      <div class="fields two" style="margin-top:14px">
        <label class="field"><span>Keterangan <small>(mis. "Tol Cipularang pergi")</small></span><input type="text" name="keterangan" maxlength="255"></label>
        <label class="field"><span>Foto struk atau PDF</span><input type="file" name="struk" accept="image/*,application/pdf" required></label>
      </div>
      <div class="actions"><button class="btn deep">Simpan bukti</button><span class="upload-status" aria-live="polite"></span></div>
    </form>
  <?php endif; ?>
</section>

<?php if ($bayar): ?>
<section class="panel">
  <h2>Pembayaran dari panitia</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Tanggal transfer</th><th class="num">Nominal</th><th>Bukti transfer</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php foreach ($bayar as $p): ?>
      <tr><td><?= h(tgl($p['tanggal'])) ?></td><td class="num"><b><?= h(rupiah($p['nominal'])) ?></b></td>
        <td><a href="<?= h(url('/file/t/' . $p['id'])) ?>" target="_blank" rel="noopener">Lihat bukti</a></td><td class="small"><?= h($p['catatan']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th>Total dibayar</th><th class="num"><?= h(rupiah($tot['dibayar'])) ?></th><th colspan="2"><?= $tot['sisa'] > 0 ? 'Sisa ' . h(rupiah($tot['sisa'])) : 'Lunas' ?></th></tr></tfoot>
  </table></div>
</section>
<?php endif; ?>

<section class="panel">
  <h2>Rekening tujuan transfer</h2>
  <?php if ($rekeningEditable): ?>
    <form method="post" action="<?= h(url('/saya/rekening')) ?>"><?= csrf_field() ?>
      <div class="fields three">
        <label class="field"><span>Nama pemilik rekening</span><input type="text" name="atas_nama" value="<?= h($inst['atas_nama']) ?>" required></label>
        <label class="field"><span>Bank</span><input type="text" name="bank" value="<?= h($inst['bank']) ?>" placeholder="BRI" required></label>
        <label class="field"><span>Nomor rekening</span><input type="text" name="no_rek" value="<?= h($inst['no_rek']) ?>" inputmode="numeric" required></label>
      </div>
      <div class="actions"><button class="btn small secondary">Simpan rekening</button></div>
    </form>
  <?php else: ?>
    <dl class="kv"><dt>Atas nama</dt><dd><?= h($inst['atas_nama']) ?></dd><dt>Bank</dt><dd><?= h($inst['bank']) ?></dd><dt>Nomor</dt><dd><?= h($inst['no_rek']) ?></dd></dl>
    <p class="small muted" style="margin:10px 0 0">Rekening terkunci setelah diverifikasi. Perlu diganti? <?= hubungi_panitia('Halo panitia, kami dari ' . $inst['nama'] . ' (' . kode_instansi((int) $inst['id']) . ') perlu mengganti rekening reimburse.', 'Hubungi panitia via WhatsApp') ?>.</p>
  <?php endif; ?>
</section>

<?php if ($editable && $st !== 'diajukan' && ($st === 'draft' || $belumDiajukan > 0)):
  $baruTotal = array_sum(array_map(static fn ($b) => $b['status'] === 'menunggu' ? (int) $b['nominal'] : 0, $items));
  $pertama = $st === 'draft'; ?>
  <section class="panel">
    <h2><?= $pertama ? 'Ajukan reimburse' : 'Ajukan bukti tambahan' ?></h2>
    <p><?= $pertama ? 'Pastikan semua struk sudah diunggah dan rekening sudah benar. Setelah diajukan, panitia memeriksa setiap bukti; status dan catatan panitia tampil di halaman ini.' : 'Ada ' . $belumDiajukan . ' bukti baru yang belum diajukan. Setelah diajukan, panitia memeriksanya dan mentransfer kekurangannya ke rekening yang sama.' ?></p>
    <form method="post" action="<?= h(url('/saya/ajukan')) ?>" data-confirm="<?= $pertama ? 'Ajukan reimburse sebesar ' . h(rupiah($total)) : 'Ajukan bukti tambahan sebesar ' . h(rupiah($baruTotal)) ?>?"><?= csrf_field() ?>
      <button class="btn" <?= (!$belumDiajukan || !$inst['no_rek']) ? 'disabled' : '' ?>><?= $pertama ? 'Ajukan reimburse ' . h(rupiah($total)) : 'Ajukan bukti tambahan ' . h(rupiah($baruTotal)) ?></button>
      <?php if (!$belumDiajukan || !$inst['no_rek']): ?><p class="hint" style="margin-top:8px">Tambahkan minimal satu bukti dan isi rekening terlebih dahulu.</p><?php endif; ?>
    </form>
  </section>
<?php endif; ?>
