<?php /** @var array $inst @var array $dates @var array $foto @var bool $canUpload */ ?>
<h1>Foto kegiatan</h1>
<p class="lead">Foto ini menjadi bukti sah perjalanan dan kehadiran di laporan kegiatan. Unggah minimal satu foto untuk setiap tahap.</p>
<p class="note">Ambil foto langsung dengan kamera HP agar <b>waktu dan lokasi foto</b> terbaca otomatis. Foto dari WhatsApp atau hasil screenshot biasanya tidak menyimpan waktu kamera, sehingga ditandai "tanpa timestamp kamera".</p>
<?php if (!$canUpload): ?><p class="note warn">Batas upload sudah lewat (<?= h(tgl_jam(setting('batas_upload'))) ?>).</p><?php endif; ?>
<?php if (!$dates): ?><p class="muted">Belum ada peserta aktif, jadi belum ada tanggal kegiatan.</p><?php endif; ?>

<?php foreach ($dates as $d): ?>
  <section class="panel">
    <div class="panel-head"><h2><?= h(tgl($d, true)) ?></h2>
      <?php $done = count(array_intersect(array_keys(TAHAP), array_keys($foto[$d] ?? []))); ?>
      <span class="badge <?= $done === count(TAHAP) ? 'ok' : 'warn' ?>"><?= $done ?> dari <?= count(TAHAP) ?> tahap</span>
    </div>
    <ol class="stages">
      <?php foreach (TAHAP as $key => $label): $list = $foto[$d][$key] ?? []; ?>
        <li class="stage">
          <div class="stage-head">
            <h3><?= h($label) ?></h3>
            <?= $list ? '<span class="badge ok">' . count($list) . ' foto</span>' : '<span class="badge draft">Belum ada</span>' ?>
          </div>
          <?php if ($list): ?>
            <div class="thumbs">
              <?php foreach ($list as $f): ?>
                <figure class="thumb">
                  <a href="<?= h(url('/file/e/' . $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= h(url('/file/e/' . $f['id'])) ?>" alt="Foto <?= h($label) ?>" loading="lazy"></a>
                  <figcaption>
                    <?= $f['exif_ok'] ? 'Foto: ' . h(tgl_jam($f['taken_at'])) : '<span class="badge warn">tanpa timestamp kamera</span>' ?><br>
                    Diunggah: <?= h(tgl_jam($f['uploaded_at'])) ?>
                    <?php if ($f['lat'] !== null): ?><br><a href="https://maps.google.com/?q=<?= h($f['lat'] . ',' . $f['lon']) ?>" target="_blank" rel="noopener">Lihat lokasi</a><?php endif; ?>
                    <?php if ($canUpload): ?>
                      <form method="post" action="<?= h(url('/saya/foto/' . $f['id'] . '/hapus')) ?>" data-confirm="Hapus foto ini?"><?= csrf_field() ?><button class="linkbtn">Hapus</button></form>
                    <?php endif; ?>
                  </figcaption>
                </figure>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($canUpload): ?>
            <form class="uploader js-photo" method="post" enctype="multipart/form-data" action="<?= h(url('/saya/foto')) ?>"
                  data-instansi="<?= h($inst['nama']) ?>" data-tahap="<?= h($label) ?>" data-tanggal="<?= h(tgl($d)) ?>" style="margin-top:<?= $list ? '12px' : '0' ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="tanggal" value="<?= h($d) ?>">
              <input type="hidden" name="tahap" value="<?= h($key) ?>">
              <label class="btn small deep">Ambil foto<input type="file" name="foto" accept="image/*" capture="environment"></label>
              <label class="btn small secondary">Pilih dari galeri<input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/heic" multiple></label>
              <noscript><button class="btn small">Unggah</button></noscript>
              <span class="upload-status" aria-live="polite"></span>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endforeach; ?>
