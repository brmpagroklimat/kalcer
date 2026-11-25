<?php /** @var array $list @var string $cari @var string $tanggal @var string $status */ ?>
<h1>Instansi</h1>
<form class="filters" method="get">
  <label class="field"><span>Cari</span><input type="text" name="q" value="<?= h($cari) ?>" placeholder="Nama, kab/kota, PIC, atau kode"></label>
  <label class="field"><span>Tanggal</span><select name="tanggal"><option value="">Semua</option>
    <?php foreach (event_dates() as $d): ?><option value="<?= h($d) ?>" <?= $tanggal === $d ? 'selected' : '' ?>><?= h(tgl($d)) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Reimburse</span><select name="status"><option value="">Semua</option>
    <?php foreach (STATUS_REIMBURSE as $k => $l): ?><option value="<?= h($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <button class="btn secondary">Terapkan</button>
</form>
<p class="muted small"><?= count($list) ?> instansi.</p>
<form method="post" action="<?= h(url('/admin/instansi/hapus-banyak')) ?>" id="hapus-banyak" autocomplete="off"><?= csrf_field() ?></form>
<div class="table-wrap"><table>
  <thead><tr><th style="width:36px"><label class="sr-only" for="cek-semua">Centang semua</label><input type="checkbox" id="cek-semua" data-cek-semua="ids[]"></th><th>Kode</th><th>Instansi</th><th>PIC instansi</th><th>Peserta</th><th class="num">Menginap</th><th class="num">Foto</th><th>Reimburse</th></tr></thead>
  <tbody>
  <?php foreach ($list as $i): ?>
    <tr>
      <td><label class="sr-only" for="cek-<?= (int) $i['id'] ?>">Pilih <?= h($i['nama']) ?></label><input type="checkbox" id="cek-<?= (int) $i['id'] ?>" name="ids[]" value="<?= (int) $i['id'] ?>" form="hapus-banyak"></td>
      <td><a href="<?= h(url('/admin/instansi/' . $i['id'])) ?>"><?= h(kode_instansi((int) $i['id'])) ?></a></td>
      <td><a href="<?= h(url('/admin/instansi/' . $i['id'])) ?>"><b><?= h($i['nama']) ?></b></a><br><span class="muted"><?= h(wilayah($i)) ?></span></td>
      <td><?= h($i['koordinator']) ?><br><a class="small" href="https://wa.me/<?= h(preg_replace('/^0/', '62', ltrim($i['hp'], '+'))) ?>" target="_blank" rel="noopener"><?= h($i['hp']) ?></a></td>
      <td><?php foreach (array_filter(explode(',', (string) $i['hari'])) as $hc): [$d, $c] = explode(':', $hc); ?><span class="chip"><?= h(tgl_pendek($d)) ?>: <?= (int) $c ?></span> <?php endforeach; ?></td>
      <td class="num"><?= (int) $i['menginap'] ?></td>
      <td class="num"><?= (int) $i['foto'] ?></td>
      <td><span class="badge <?= h($i['reimburse_status']) ?>"><?= h(STATUS_REIMBURSE[$i['reimburse_status']]) ?></span><?= $i['total_biaya'] > 0 ? '<br><span class="small">' . h(rupiah($i['total_biaya'])) . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="8" class="muted">Tidak ada instansi yang cocok.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($list): ?>
<div class="panel hapus-banyak" style="margin-top:16px">
  <h2 style="font-size:17px">Hapus instansi yang dicentang</h2>
  <p class="small muted" style="margin-top:0">Untuk membersihkan data uji coba: cari (mis. "uji"), centang, lalu hapus. Instansi terhapus beserta peserta, foto, bukti biaya, dan pembayarannya. Tidak bisa dibatalkan.</p>
  <div class="actions" style="margin-top:6px">
    <label class="field" style="flex:0 1 200px"><span class="small">Ketik HAPUS untuk konfirmasi</span><input type="text" name="konfirmasi" form="hapus-banyak" autocomplete="off"></label>
    <button class="btn small danger" form="hapus-banyak" style="align-self:flex-end" data-hapus-banyak><span>Hapus yang dicentang (<span data-jml-cek>0</span>)</span></button>
  </div>
</div>
<?php endif; ?>
