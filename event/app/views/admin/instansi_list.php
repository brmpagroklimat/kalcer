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
<div class="table-wrap"><table>
  <thead><tr><th>Kode</th><th>Instansi</th><th>PIC instansi</th><th>Peserta</th><th class="num">Menginap</th><th class="num">Foto</th><th>Reimburse</th></tr></thead>
  <tbody>
  <?php foreach ($list as $i): ?>
    <tr>
      <td><a href="<?= h(url('/admin/instansi/' . $i['id'])) ?>"><?= h(kode_instansi((int) $i['id'])) ?></a></td>
      <td><a href="<?= h(url('/admin/instansi/' . $i['id'])) ?>"><b><?= h($i['nama']) ?></b></a><br><span class="muted"><?= h($i['kabkota']) ?></span></td>
      <td><?= h($i['koordinator']) ?><br><a class="small" href="https://wa.me/<?= h(preg_replace('/^0/', '62', ltrim($i['hp'], '+'))) ?>" target="_blank" rel="noopener"><?= h($i['hp']) ?></a></td>
      <td><?php foreach (array_filter(explode(',', (string) $i['hari'])) as $hc): [$d, $c] = explode(':', $hc); ?><span class="chip"><?= h(tgl_pendek($d)) ?>: <?= (int) $c ?></span> <?php endforeach; ?></td>
      <td class="num"><?= (int) $i['menginap'] ?></td>
      <td class="num"><?= (int) $i['foto'] ?></td>
      <td><span class="badge <?= h($i['reimburse_status']) ?>"><?= h(STATUS_REIMBURSE[$i['reimburse_status']]) ?></span><?= $i['total_biaya'] > 0 ? '<br><span class="small">' . h(rupiah($i['total_biaya'])) . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="7" class="muted">Tidak ada instansi yang cocok.</td></tr><?php endif; ?>
  </tbody>
</table></div>
