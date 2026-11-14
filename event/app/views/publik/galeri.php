<?php /** @var array $albums */ ?>
<h1>Galeri kegiatan</h1>
<p class="lead">Dokumentasi <?= h(setting('nama_kegiatan')) ?>. Klik foto untuk melihat ukuran penuh.</p>
<?php if (!$albums): ?><section class="panel"><p class="muted" style="margin:0">Belum ada foto. Dokumentasi akan diunggah panitia setelah kegiatan.</p></section><?php endif; ?>
<?php foreach ($albums as $album => $fotos): ?>
  <section class="panel album">
    <div class="panel-head">
      <h2><?= h($album) ?></h2>
      <span class="inline-actions small"><span class="muted"><?= count($fotos) ?> foto</span>
        <a href="<?= h(url('/galeri/unduh?album=' . rawurlencode($album))) ?>">Unduh semua (ZIP)</a></span>
    </div>
    <div class="gallery">
      <?php foreach ($fotos as $g): ?>
        <a href="<?= h(url('/media/g/' . $g['id'])) ?>" target="_blank" rel="noopener"><img src="<?= h(url('/media/g/' . $g['id'] . '/thumb')) ?>" alt="<?= h($g['caption'] ?: $album) ?>" loading="lazy"></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
