<?php /** @var array $items */ ?>
<h1>Materi kegiatan</h1>
<p class="lead">Bahan paparan dan dokumen <?= h(setting('nama_kegiatan')) ?>.</p>
<section class="panel">
  <?php if (!$items): ?><p class="muted" style="margin:0">Belum ada materi. Materi akan diunggah panitia menjelang atau setelah kegiatan.</p><?php endif; ?>
  <ul class="files">
    <?php foreach ($items as $m): ?>
      <li>
        <span class="ftype <?= h($m['ext']) ?>" aria-hidden="true"><?= h(strtoupper($m['ext'])) ?></span>
        <span><b><?= h($m['judul']) ?></b>
          <?php if ($m['deskripsi']): ?><span class="small"><?= h($m['deskripsi']) ?></span><br><?php endif; ?>
          <span class="meta"><?= h(strtoupper($m['ext'])) ?>, <?= h(ukuran($m['size'])) ?></span></span>
        <a class="btn small secondary" href="<?= h(url('/media/m/' . $m['id'])) ?>" download>Unduh<span class="sr-only"> <?= h($m['judul']) ?></span></a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
