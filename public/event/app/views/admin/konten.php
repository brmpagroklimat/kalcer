<?php /** @var array $materi @var array $albums @var array $jadwal */ ?>
<h1>Jadwal, materi & galeri</h1>
<p class="lead">Konten ini tampil publik di menu <a href="<?= h(url('/materi')) ?>">Materi</a> dan <a href="<?= h(url('/galeri')) ?>">Galeri</a>.</p>

<section class="panel" id="jadwal">
  <h2>Jadwal acara</h2>
  <?php foreach ($jadwal as $d => $list): ?>
    <h3 style="margin-top:14px"><?= h(tgl($d, true)) ?></h3>
    <ol class="agenda">
      <?php foreach ($list as $j): ?>
        <li><span class="agenda-time"><?= h(jam($j['jam_mulai'])) ?><?= $j['jam_selesai'] ? '–' . h(jam($j['jam_selesai'])) : '' ?></span>
          <span><b><?= h($j['acara']) ?></b><?= $j['keterangan'] ? ' <span class="small muted">' . h($j['keterangan']) . '</span>' : '' ?>
            <form method="post" action="<?= h(url('/admin/jadwal/' . $j['id'] . '/hapus')) ?>" data-confirm="Hapus acara ini dari jadwal?" style="display:inline"><?= csrf_field() ?> <button class="linkbtn danger-link small">Hapus</button></form></span></li>
      <?php endforeach; ?>
    </ol>
  <?php endforeach; ?>
  <?php if (!$jadwal): ?><p class="muted">Belum ada jadwal.</p><?php endif; ?>
  <h3 style="margin-top:20px">Tambah acara</h3>
  <form method="post" action="<?= h(url('/admin/jadwal')) ?>"><?= csrf_field() ?>
    <div class="fields three">
      <label class="field"><span>Tanggal</span><select name="tanggal" required><?php foreach (event_dates() as $d): ?><option value="<?= h($d) ?>"><?= h(tgl($d, true)) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Jam mulai</span><input type="time" name="jam_mulai" required></label>
      <label class="field"><span>Jam selesai <small>(opsional)</small></span><input type="time" name="jam_selesai"></label>
    </div>
    <div class="fields two" style="margin-top:14px">
      <label class="field"><span>Acara</span><input type="text" name="acara" maxlength="200" required placeholder="Registrasi ulang peserta"></label>
      <label class="field"><span>Keterangan <small>(opsional)</small></span><input type="text" name="keterangan" maxlength="255" placeholder="Lobi utama"></label>
    </div>
    <div class="actions"><button class="btn small deep">Tambah ke jadwal</button></div>
  </form>
</section>

<section class="panel" id="materi">
  <h2>Materi</h2>
  <ul class="files">
    <?php foreach ($materi as $m): ?>
      <li>
        <span class="ftype <?= h($m['ext']) ?>" aria-hidden="true"><?= h(strtoupper($m['ext'])) ?></span>
        <span><b><?= h($m['judul']) ?></b><span class="meta"><?= h($m['nama_file']) ?>, <?= h(ukuran($m['size'])) ?>, diunggah <?= h(tgl_jam($m['created_at'])) ?></span></span>
        <span class="inline-actions">
          <a class="small" href="<?= h(url('/media/m/' . $m['id'])) ?>">Unduh</a>
          <form method="post" action="<?= h(url('/admin/materi/' . $m['id'] . '/hapus')) ?>" data-confirm="Hapus materi &quot;<?= h($m['judul']) ?>&quot;?"><?= csrf_field() ?><button class="linkbtn" style="color:var(--err-ink)">Hapus</button></form>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$materi): ?><p class="muted">Belum ada materi.</p><?php endif; ?>
  <h3 style="margin-top:20px">Tambah materi</h3>
  <form method="post" enctype="multipart/form-data" action="<?= h(url('/admin/materi')) ?>"><?= csrf_field() ?>
    <div class="fields two">
      <label class="field"><span>Judul</span><input type="text" name="judul" maxlength="200" required placeholder="Paparan Kepala BRMP Agroklimat"></label>
      <label class="field"><span>File</span><input type="file" name="file" required accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.zip,.jpg,.jpeg,.png,.mp4" data-max="<?= MAX_KONTEN ?>">
        <small>PDF, PowerPoint, Word, Excel, ZIP, JPG/PNG, atau MP4. Maksimal 2 MB per file.</small></label>
      <label class="field" style="grid-column:1/-1"><span>Keterangan <small>(opsional)</small></span><input type="text" name="deskripsi" maxlength="500"></label>
    </div>
    <div class="actions"><button class="btn deep">Unggah materi</button></div>
  </form>
</section>

<section class="panel" id="galeri">
  <h2>Galeri foto</h2>
  <form class="js-gallery" method="post" enctype="multipart/form-data" action="<?= h(url('/admin/galeri')) ?>"><?= csrf_field() ?>
    <div class="fields two">
      <label class="field"><span>Album</span><input type="text" name="album" list="album-list" maxlength="120" required placeholder="Hari 1, 2 Desember 2026">
        <datalist id="album-list"><?php foreach (array_keys($albums) as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?></datalist>
        <small>Ketik nama album baru, atau pilih album yang sudah ada.</small></label>
      <label class="field"><span>Foto</span><input type="file" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple required>
        <small>Boleh pilih banyak sekaligus. Foto dikompres otomatis sebelum diunggah, maksimal 2 MB per foto.</small></label>
    </div>
    <div class="actions"><button class="btn deep">Unggah foto</button><span class="upload-status" aria-live="polite"></span></div>
  </form>

  <?php foreach ($albums as $album => $fotos): ?>
    <div class="album" style="margin-top:24px">
      <div class="panel-head"><h3><?= h($album) ?></h3><span class="muted small"><?= count($fotos) ?> foto</span></div>
      <div class="thumbs">
        <?php foreach ($fotos as $g): ?>
          <figure class="thumb">
            <a href="<?= h(url('/media/g/' . $g['id'])) ?>" target="_blank" rel="noopener"><img src="<?= h(url('/media/g/' . $g['id'] . '/thumb')) ?>" alt="" loading="lazy"></a>
            <figcaption><form method="post" action="<?= h(url('/admin/galeri/' . $g['id'] . '/hapus')) ?>" data-confirm="Hapus foto ini dari galeri?"><?= csrf_field() ?><button class="linkbtn">Hapus</button></form></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
