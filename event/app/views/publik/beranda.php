<?php /** @var array $dates @var bool $open @var int $nMateri @var int $nFoto @var bool $jadwal */
$lokasi = setting('lokasi');
?>
<section class="hero">
  <div>
    <?php // Kata pertama dimiringkan di baris sendiri + tunas kecil, sisanya di baris berikutnya.
      [$kata1, $sisa] = array_pad(explode(' ', setting('nama_kegiatan'), 2), 2, ''); ?>
    <h1 class="title"><em><?= h($kata1) ?></em><?php if ($sisa !== ''): ?><?= view('_tunas') ?><br><?= h($sisa) ?><?php endif; ?></h1>
    <p class="when"><?= implode(' dan ', array_map(static fn ($d) => h(tgl($d, true)), $dates)) ?><?= $lokasi !== '' ? '<br>' . h($lokasi) : '' ?></p>
    <?php if (setting('deskripsi') !== ''): ?><p class="desc prewrap"><?= h(setting('deskripsi')) ?></p><?php endif; ?>
    <div class="actions">
      <?php if ($open): ?><a class="btn" href="<?= h(url('/daftar')) ?>">Registrasi</a><?php endif; ?>
      <?php if ($nMateri): ?><a class="btn secondary" href="<?= h(url('/materi')) ?>">Unduh materi</a><?php endif; ?>
    </div>
  </div>
  <?= view('_geo') ?>
</section>

<div class="cards">
  <a class="card" href="<?= h(url('/daftar')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;top:0;width:26px;height:26px;background:var(--coral);border-radius:100% 0 0 0"></span><span style="right:0;bottom:0;width:22px;height:22px;background:var(--sky);border-radius:50%"></span><span style="left:4px;bottom:2px;width:12px;height:12px;background:var(--leaf);border-radius:0 100% 0 100%"></span></span>
    <h2>Registrasi</h2>
    <p><?= $open ? 'Dibuka sampai ' . h(tgl_jam(setting('batas_daftar'))) . '. Setiap instansi memilih satu tanggal, maksimal ' . (int) setting('kuota_per_instansi') . ' peserta.' : 'Registrasi sudah ditutup.' ?></p>
    <span class="go"><?= $open ? 'Registrasi' : 'Lihat info' ?></span>
  </a>
  <a class="card" href="<?= h(url('/jadwal')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;top:4px;width:34px;height:34px;background:var(--sun);border-radius:50%"></span><span style="left:0;top:4px;width:17px;height:17px;background:var(--leaf);border-radius:100% 0 0 0"></span><span style="right:0;top:0;width:12px;height:12px;background:var(--sand);border-radius:50%"></span></span>
    <h2>Jadwal</h2>
    <p><?= $jadwal ? 'Susunan acara ' . h(implode(' dan ', array_map('tgl_pendek', $dates))) . ' sudah tersedia.' : 'Jadwal acara akan diumumkan di sini.' ?></p>
    <span class="go">Lihat jadwal</span>
  </a>
  <a class="card" href="<?= h(url('/materi')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;bottom:0;width:40px;height:20px;background:var(--deep);border-radius:20px 20px 0 0"></span><span style="left:12px;top:0;width:18px;height:18px;background:var(--coral);border-radius:100% 0 100% 0"></span><span style="right:2px;top:4px;width:9px;height:9px;background:var(--sun);border-radius:50%"></span></span>
    <h2>Materi</h2>
    <p><?= $nMateri ? $nMateri . ' file materi kegiatan siap diunduh.' : 'Materi kegiatan akan tersedia di sini.' ?></p>
    <span class="go">Lihat materi</span>
  </a>
  <a class="card" href="<?= h(url('/galeri')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;top:0;width:24px;height:24px;background:var(--leaf);border-radius:0 50% 50% 50%"></span><span style="right:0;top:6px;width:16px;height:32px;background:var(--sky);border-radius:0 16px 16px 0"></span><span style="left:6px;bottom:0;width:14px;height:14px;background:var(--coral);border-radius:50%"></span></span>
    <h2>Galeri</h2>
    <p><?= $nFoto ? $nFoto . ' foto dokumentasi kegiatan.' : 'Foto dokumentasi akan tersedia setelah kegiatan.' ?></p>
    <span class="go">Lihat galeri</span>
  </a>
</div>

<p class="note">Sudah registrasi? Buka <b>link instansi</b> yang Anda terima setelah registrasi untuk mengelola peserta, mengunggah foto kegiatan, dan mengajukan reimburse. Link hilang? <?= hubungi_panitia('Halo panitia ' . setting('nama_kegiatan') . ', link instansi kami hilang. Nama instansi: ') ?>.</p>
