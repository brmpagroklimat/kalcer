<?php /** @var array $j */
$img = in_array($j['ext'], ['jpg', 'jpeg', 'png'], true);
?>
<h1>Jadwal acara</h1>
<p class="lead"><?= h(setting('nama_kegiatan')) ?><?= setting('lokasi') !== '' ? ', ' . h(setting('lokasi')) : '' ?>.</p>
<?php if ($j['link'] === '' && $j['file'] === ''): ?>
  <section class="panel"><p class="muted" style="margin:0">Jadwal acara akan diumumkan panitia di sini.</p></section>
<?php else: ?>
  <section class="panel">
    <div class="actions" style="margin-top:0">
      <?php if ($j['link'] !== ''): ?><a class="btn" href="<?= h($j['link']) ?>" target="_blank" rel="noopener">Buka jadwal<?= str_contains($j['link'], 'docs.google.com') ? ' (Google Docs)' : '' ?></a><?php endif; ?>
      <?php if ($j['file'] !== ''): ?><a class="btn secondary" href="<?= h(url('/media/jadwal')) ?>" download>Unduh jadwal (<?= h(strtoupper($j['ext'])) ?>)</a><?php endif; ?>
    </div>
    <?php if ($j['diubah']): ?><p class="small muted" style="margin:10px 0 0">Diperbarui <?= h(tgl_jam($j['diubah'])) ?>.</p><?php endif; ?>
    <?php if ($j['file'] !== '' && $img): ?>
      <img class="jadwal-view" src="<?= h(url('/media/jadwal')) ?>" alt="Jadwal acara">
    <?php elseif ($j['file'] !== '' && $j['ext'] === 'pdf'): ?>
      <object class="jadwal-view pdf" data="<?= h(url('/media/jadwal')) ?>" type="application/pdf"><p class="small">Pratinjau PDF tidak tersedia di browser ini. Gunakan tombol unduh di atas.</p></object>
    <?php endif; ?>
  </section>
<?php endif; ?>
