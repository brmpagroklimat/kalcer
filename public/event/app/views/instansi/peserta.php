<?php /** @var array $inst @var array $byDate @var bool $canEdit */
$kuota = (int) setting('kuota_per_instansi');
$names = [];
foreach ($byDate as $list) { foreach ($list as $p) { $names[$p['id']] = $p['nama']; } }
?>
<?php if (!$canEdit): ?>
  <p class="note warn">Batas perubahan peserta sudah lewat (<?= h(tgl_jam(setting('batas_ganti'))) ?>). Untuk perubahan mendesak, <?= hubungi_panitia('Halo panitia, kami perlu mengubah peserta ' . $inst['nama'] . ' (' . kode_instansi((int) $inst['id']) . ').', 'hubungi panitia via WhatsApp') ?>.</p>
<?php else: ?>
  <p class="note">Perubahan peserta bisa dilakukan sampai <b><?= h(tgl_jam(setting('batas_ganti'))) ?></b>. Gunakan <b>Ganti peserta</b> kalau orangnya berganti; nama lama tetap tercatat di riwayat.</p>
<?php endif; ?>



<?php foreach ($byDate as $d => $list):
    $aktif = array_values(array_filter($list, static fn ($p) => (int) $p['aktif'] === 1));
    $lama = array_values(array_filter($list, static fn ($p) => (int) $p['aktif'] === 0));
    if (!$list && !$canEdit) { continue; }
?>
  <section class="panel">
    <div class="panel-head">
      <h2><?= h(tgl($d, true)) ?></h2>
      <span class="muted small"><?= count($aktif) ?> dari <?= $kuota ?> peserta · menginap malam <?= h(malam_menginap($d)) ?></span>
    </div>
    <?php if (!$aktif): ?><p class="muted">Belum ada peserta untuk tanggal ini.</p><?php endif; ?>
    <ul class="people">
      <?php foreach ($aktif as $p): ?>
        <li>
          <div class="who"><b><?= h($p['nama']) ?></b>
            <?= $p['menginap'] ? '<span class="badge inap">Menginap</span>' : '<span class="badge muted">Tidak menginap</span>' ?>
          </div>
          <div class="meta"><?= h(implode(' · ', array_filter([$p['jabatan'], $p['nip'] ? 'NIP ' . $p['nip'] : null, $p['jk'] === 'L' ? 'Laki-laki' : 'Perempuan', $p['hp']]))) ?></div>
          <?php if ($p['menginap']): $km = kamar_peserta($p); ?>
            <div class="kamar-info"><?php if (!$km): ?>Kamar: menunggu pembagian panitia<?php else: ?>
              <b>Kamar <?= h($km['nomor']) ?></b><?= $km['catatan'] ? ', ' . h($km['catatan']) : '' ?>.
              <?php if (!$km['teman']): ?>Sendiri.<?php else: ?>Sekamar dengan
                <?php foreach ($km['teman'] as $ti => $t): ?><?= $ti ? ', ' : '' ?><?= h($t['nama']) ?> (<?= h($t['instansi']) ?>)<?php if ($t['wa']): ?> <a class="wa-link" href="https://wa.me/<?= h($t['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><?php endforeach; ?>.
              <?php endif; ?><?php endif; ?></div>
            <?php $sh = shuttle_peserta($p); ?>
            <div class="kamar-info"><?= $sh ? '<b>Shuttle: ' . h($sh['nama']) . '</b>' . ($sh['jam'] ? ', berangkat ' . h(jam($sh['jam'])) : '') . ($sh['titik_jemput'] ? ' dari ' . h($sh['titik_jemput']) : '') : 'Shuttle: menunggu pembagian panitia' ?></div>
          <?php endif; ?>
          <?php if ($canEdit): ?>
            <div class="inline-actions">
              <details class="edit"><summary>Ubah data</summary>
                <form method="post" action="<?= h(url('/saya/peserta/' . $p['id'])) ?>"><?= csrf_field() ?>
                  <p class="hint">Untuk memperbaiki penulisan. Mengganti orang lain? Pakai "Ganti peserta".</p>
                  <?= view('_person_fields', ['prefix' => '', 'val' => $p, 'idp' => 'u' . $p['id'], 'required' => true]) ?>
                  <div class="actions"><button class="btn small deep">Simpan</button></div>
                </form>
              </details>
              <details class="edit"><summary>Ganti peserta</summary>
                <form method="post" action="<?= h(url('/saya/peserta/' . $p['id'] . '/ganti')) ?>"><?= csrf_field() ?>
                  <p class="hint">Isi data orang yang menggantikan <?= h($p['nama']) ?>.</p>
                  <?= view('_person_fields', ['prefix' => '', 'val' => ['menginap' => $p['menginap']], 'idp' => 'g' . $p['id'], 'required' => true]) ?>
                  <div class="actions"><button class="btn small deep">Ganti peserta</button></div>
                </form>
              </details>
              <form method="post" action="<?= h(url('/saya/peserta/' . $p['id'] . '/hapus')) ?>" data-confirm="<?= h($p['nama']) ?> batal ikut kegiatan tanggal <?= h(tgl($d)) ?>?"><?= csrf_field() ?>
                <button class="linkbtn danger-link">Batal ikut</button>
              </form>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php foreach ($lama as $p): ?>
        <li class="replaced"><div class="who"><b><?= h($p['nama']) ?></b>
          <span class="badge muted"><?= $p['diganti_oleh'] ? 'Diganti oleh ' . h($names[$p['diganti_oleh']] ?? '-') : 'Batal ikut' ?></span></div></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($canEdit && count($aktif) < $kuota): ?>
      <details class="edit"><summary>+ Tambah peserta <?= h(tgl_pendek($d)) ?></summary>
        <form method="post" action="<?= h(url('/saya/peserta')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="tanggal" value="<?= h($d) ?>">
          <?= view('_person_fields', ['prefix' => '', 'val' => [], 'idp' => 'n' . $d, 'required' => true, 'malam' => malam_menginap($d)]) ?>
          <div class="actions"><button class="btn small deep">Tambah peserta</button></div>
        </form>
      </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<div class="grid2">
  <section class="panel">
    <h2>PIC instansi</h2>
    <form method="post" action="<?= h(url('/saya/instansi')) ?>"><?= csrf_field() ?>
      <div class="fields">
        <label class="field"><span>Nama PIC</span><input type="text" name="koordinator" value="<?= h($inst['koordinator']) ?>" required></label>
        <label class="field"><span>No. HP/WA <small>(angka saja)</small></span><input type="tel" name="hp" value="<?= h($inst['hp']) ?>" inputmode="numeric" required data-validate="hp"></label>
        <label class="field"><span>Email</span><input type="email" name="email" value="<?= h($inst['email']) ?>" data-validate="email"></label>
      </div>
      <div class="actions"><button class="btn small secondary">Simpan PIC</button></div>
    </form>
    <p class="small muted" style="margin-top:12px">Nama instansi: <?= h($inst['nama']) ?>, <?= h($inst['kabkota']) ?>. Untuk mengubahnya, <?= hubungi_panitia('Halo panitia, kami ingin mengubah nama instansi ' . $inst['nama'] . ' (' . kode_instansi((int) $inst['id']) . ').', 'hubungi panitia via WhatsApp') ?>.</p>
  </section>

</div>
