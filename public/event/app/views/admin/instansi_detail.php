<?php /** @var array $inst @var array $byDate @var array $foto @var array $biaya @var array $riwayat @var ?array $newLink */
$kode = kode_instansi((int) $inst['id']);
$names = [];
foreach ($byDate as $list) { foreach ($list as $p) { $names[$p['id']] = $p['nama']; } }
$total = array_sum(array_column($biaya, 'nominal'));
$disetujui = 0;
foreach ($biaya as $b) { if ($b['status'] === 'disetujui') { $disetujui += (int) ($b['nominal_disetujui'] ?? $b['nominal']); } }
$waHp = preg_replace('/^0/', '62', ltrim($inst['hp'], '+'));
?>
<p class="small"><a href="<?= h(url('/admin/instansi')) ?>">Semua instansi</a></p>
<div class="panel-head">
  <h1 style="margin:0"><?= h($inst['nama']) ?> <span class="kode"><?= h($kode) ?></span></h1>
  <div class="actions" style="margin:0">
    <form method="post" action="<?= h(url('/admin/instansi/' . $inst['id'] . '/buka')) ?>"><?= csrf_field() ?><button class="btn small secondary">Buka sebagai instansi</button></form>
    <a class="btn small secondary" href="<?= h(url('/admin/instansi/' . $inst['id'] . '/evidence.zip')) ?>">Unduh ZIP bukti</a>
  </div>
</div>
<p class="muted"><?= h($inst['kabkota']) ?>. Koordinator <?= h($inst['koordinator']) ?>, <a href="https://wa.me/<?= h($waHp) ?>" target="_blank" rel="noopener"><?= h($inst['hp']) ?></a><?= $inst['email'] ? ', ' . h($inst['email']) : '' ?>. Daftar <?= h(tgl_jam($inst['created_at'])) ?>.</p>

<?php if ($newLink): ?>
  <div class="panel" style="border-color:var(--deep)">
    <h2>Link baru untuk <?= h($inst['nama']) ?></h2>
    <p>Link lama sudah tidak berlaku. <?= $newLink['mailed'] ? 'Link juga sudah dikirim ke ' . h($inst['email']) . '.' : 'Kirimkan link ini ke koordinator.' ?></p>
    <div class="linkbox"><input id="newlink" type="text" readonly value="<?= h($newLink['link']) ?>"><button class="btn deep" type="button" data-copy="#newlink">Salin link</button></div>
    <a class="btn secondary" href="https://wa.me/<?= h($waHp) ?>?text=<?= h(rawurlencode('Link instansi ' . $inst['nama'] . ' untuk ' . setting('nama_kegiatan') . ': ' . $newLink['link'])) ?>" target="_blank" rel="noopener">Kirim ke WA koordinator</a>
  </div>
<?php endif; ?>

<div class="grid2">
  <?php foreach ($byDate as $d => $list): if (!$list) { continue; } ?>
    <section class="panel">
      <h2><?= h(tgl($d, true)) ?></h2>
      <ul class="people">
        <?php foreach ($list as $p): ?>
          <li class="<?= $p['aktif'] ? '' : 'replaced' ?>">
            <div class="who"><b><?= h($p['nama']) ?></b>
              <?php if ($p['aktif']): ?><?= $p['menginap'] ? '<span class="badge inap">Menginap</span>' : '<span class="badge muted">Tidak menginap</span>' ?>
              <?php else: ?><span class="badge muted"><?= $p['diganti_oleh'] ? 'Diganti oleh ' . h($names[$p['diganti_oleh']] ?? '-') : 'Batal ikut' ?></span><?php endif; ?>
            </div>
            <div class="meta"><?= h(implode(' · ', array_filter([$p['jabatan'], $p['nip'] ? 'NIP ' . $p['nip'] : null, $p['jk'] === 'L' ? 'Laki-laki' : 'Perempuan', $p['hp']]))) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>

<section class="panel">
  <h2>Foto kegiatan</h2>
  <?php if (!$foto): ?><p class="muted">Belum ada foto.</p><?php endif; ?>
  <?php foreach ($foto as $d => $tahapList): ?>
    <h3 style="margin-top:14px"><?= h(tgl($d, true)) ?></h3>
    <?php foreach (TAHAP as $key => $label): if (empty($tahapList[$key])) { continue; } ?>
      <p class="small" style="margin:10px 0 6px"><b><?= h($label) ?></b></p>
      <div class="thumbs">
        <?php foreach ($tahapList[$key] as $f): ?>
          <figure class="thumb">
            <a href="<?= h(url('/file/e/' . $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= h(url('/file/e/' . $f['id'])) ?>" alt="Foto <?= h($label) ?>" loading="lazy"></a>
            <figcaption><?= $f['exif_ok'] ? 'Foto: ' . h(tgl_jam($f['taken_at'])) : '<span class="badge warn">tanpa timestamp kamera</span>' ?><br>Diunggah: <?= h(tgl_jam($f['uploaded_at'])) ?>
              <?php if ($f['lat'] !== null): ?><br><a href="https://maps.google.com/?q=<?= h($f['lat'] . ',' . $f['lon']) ?>" target="_blank" rel="noopener">Lokasi</a><?php endif; ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</section>

<section class="panel" id="reimburse">
  <div class="panel-head"><h2>Reimburse</h2><span class="badge <?= h($inst['reimburse_status']) ?>"><?= h(STATUS_REIMBURSE[$inst['reimburse_status']]) ?></span></div>
  <div class="totals"><span>Diajukan <b><?= h(rupiah($total)) ?></b></span><span>Disetujui <b><?= h(rupiah($disetujui)) ?></b></span>
    <span>Rekening: <?= $inst['no_rek'] ? h($inst['bank'] . ' ' . $inst['no_rek'] . ' a.n. ' . $inst['atas_nama']) : '<span class="muted">belum diisi</span>' ?></span></div>
  <?php if (!$biaya): ?><p class="muted">Belum ada bukti biaya.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Bukti</th><th class="num">Nominal</th><th>Verifikasi</th></tr></thead>
    <tbody>
    <?php foreach ($biaya as $b): ?>
      <tr>
        <td><b><?= h(JENIS_BIAYA[$b['jenis']]) ?></b>, <?= h(tgl_pendek($b['tanggal'])) ?><?= $b['keterangan'] ? '<br>' . h($b['keterangan']) : '' ?><br>
          <a href="<?= h(url('/file/b/' . $b['id'])) ?>" target="_blank" rel="noopener">Lihat struk</a> <span class="muted small">(<?= h(tgl_jam($b['created_at'])) ?>)</span></td>
        <td class="num"><?= h(rupiah($b['nominal'])) ?></td>
        <td>
          <form method="post" action="<?= h(url('/admin/biaya/' . $b['id'])) ?>" class="fields" style="gap:8px"><?= csrf_field() ?>
            <div class="choices">
              <?php foreach (['menunggu' => 'Menunggu', 'disetujui' => 'Setujui', 'ditolak' => 'Tolak'] as $k => $l): ?>
                <label class="choice"><input type="radio" name="status" value="<?= h($k) ?>" <?= $b['status'] === $k ? 'checked' : '' ?>><span><?= h($l) ?></span></label>
              <?php endforeach; ?>
            </div>
            <label class="field"><span class="small">Nominal disetujui <small>(kosongkan = sesuai struk)</small></span><input type="text" name="nominal_disetujui" inputmode="numeric" value="<?= h($b['nominal_disetujui'] ?? '') ?>"></label>
            <label class="field"><span class="small">Catatan untuk instansi</span><input type="text" name="catatan" value="<?= h($b['catatan']) ?>" maxlength="255"></label>
            <div><button class="btn small deep">Simpan</button></div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('/admin/instansi/' . $inst['id'] . '/reimburse')) ?>" style="margin-top:18px"><?= csrf_field() ?>
    <div class="fields two">
      <label class="field"><span>Status reimburse instansi</span><select name="status">
        <?php foreach (STATUS_REIMBURSE as $k => $l): ?><option value="<?= h($k) ?>" <?= $inst['reimburse_status'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select><small>"Belum diajukan" mengembalikan ke instansi untuk diperbaiki.</small></label>
      <label class="field"><span>Catatan panitia <small>(tampil di halaman instansi)</small></span><input type="text" name="catatan" value="<?= h($inst['catatan_panitia']) ?>"></label>
    </div>
    <div class="actions"><button class="btn small deep">Simpan status</button></div>
  </form>
</section>

<div class="grid2">
  <section class="panel">
    <h2>Link instansi</h2>
    <p>Link lama tidak bisa ditampilkan ulang karena hanya disimpan dalam bentuk acak (hash). Kalau instansi kehilangan link, terbitkan link baru; link lama otomatis tidak berlaku.</p>
    <form method="post" action="<?= h(url('/admin/instansi/' . $inst['id'] . '/link')) ?>" data-confirm="Terbitkan link baru? Link lama tidak akan berlaku lagi."><?= csrf_field() ?><button class="btn small secondary">Terbitkan link baru</button></form>
    <h3 style="margin-top:22px">Hapus instansi</h3>
    <p class="small muted">Menghapus instansi beserta peserta, foto, dan bukti biayanya. Tidak bisa dibatalkan.</p>
    <form method="post" action="<?= h(url('/admin/instansi/' . $inst['id'] . '/hapus')) ?>" class="actions"><?= csrf_field() ?>
      <label class="field" style="flex:1 1 180px"><span class="small">Ketik <?= h($kode) ?> untuk konfirmasi</span><input type="text" name="konfirmasi" autocomplete="off"></label>
      <button class="btn small danger" style="align-self:flex-end">Hapus instansi</button>
    </form>
  </section>
  <section class="panel">
    <h2>Riwayat</h2>
    <ul class="people small">
      <?php foreach ($riwayat as $r): ?><li><?= h($r['keterangan']) ?><div class="meta"><?= h(tgl_jam($r['created_at'])) ?> · <?= h($r['oleh']) ?></div></li><?php endforeach; ?>
    </ul>
  </section>
</div>
