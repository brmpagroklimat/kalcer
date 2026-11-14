<?php /** Panduan kegiatan untuk instansi yang sudah mendaftar. @var ?string $tanggal */
$tanggal = $tanggal ?? null;
?>
<div class="grid2">
  <div class="panel">
    <h2>Alur kegiatan</h2>
    <p class="small"><a href="<?= h(url('/jadwal')) ?>">Lihat jadwal acara</a></p>
    <ol class="steps">
      <li><b>Sebelum acara</b>, lewat link instansi Anda bisa mengubah atau mengganti peserta sampai <?= h(tgl_jam(setting('batas_ganti'))) ?>.</li>
      <li><b>Hari H</b>, unggah foto saat berangkat, tiba, kegiatan, pulang, dan tiba kembali di menu <i>Foto kegiatan</i>. Setiap foto wajib ber-watermark tanggal, jam, dan lokasi (pakai aplikasi kamera ber-watermark, mis. <i>Timestamp Camera</i>), maksimal 1 MB per foto.</li>
      <li><b>Setelah acara</b>, unggah struk BBM, tol, atau tiket kendaraan umum, isi rekening instansi, lalu ajukan reimburse di menu <i>Reimburse</i> sebelum <?= h(tgl_jam(setting('batas_upload'))) ?>.</li>
    </ol>
  </div>
  <div class="panel">
    <h2>Menginap & transport</h2>
    <p>Hotel disediakan panitia untuk malam sebelum acara<?= $tanggal ? ': <b>malam ' . h(malam_menginap($tanggal)) . '</b>' : '' ?>. Peserta yang menginap diantar shuttle dari hotel ke lokasi.</p>
    <?php if (setting('info_hotel') !== ''): ?><p class="prewrap"><b>Hotel:</b> <?= h(setting('info_hotel')) ?></p><?php endif; ?>
    <?php if (setting('info_shuttle') !== ''): ?><p class="prewrap"><b>Shuttle:</b> <?= h(setting('info_shuttle')) ?></p><?php endif; ?>
    <?php if (setting('info_hotel') === '' && setting('info_shuttle') === ''): ?><p class="small muted">Detail hotel dan jadwal shuttle akan diumumkan panitia di halaman ini.</p><?php endif; ?>
    <p class="small muted">Biaya perjalanan (BBM, tol, kendaraan umum) diganti at cost sesuai struk dan ditransfer ke satu rekening per instansi.</p>
  </div>
</div>
