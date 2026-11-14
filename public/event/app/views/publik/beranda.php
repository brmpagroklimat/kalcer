<?php /** @var array $dates @var bool $open @var int $nMateri @var int $nFoto */
$lokasi = setting('lokasi');
?>
<section class="hero">
  <div>
    <h1><?= h(setting('nama_kegiatan')) ?></h1>
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
    <span class="shape" aria-hidden="true"><span style="inset:0;background:var(--deep);border-radius:100% 0 0 0"></span><span style="right:0;bottom:0;width:18px;height:18px;background:var(--sun);border-radius:50%"></span></span>
    <h2>Registrasi</h2>
    <p><?= $open ? 'Dibuka sampai ' . h(tgl_jam(setting('batas_daftar'))) . '. Setiap instansi memilih satu tanggal, maksimal ' . (int) setting('kuota_per_instansi') . ' peserta.' : 'Registrasi sudah ditutup.' ?></p>
    <span class="go"><?= $open ? 'Registrasi' : 'Lihat info' ?></span>
  </a>
  <a class="card" href="<?= h(url('/materi')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;bottom:0;width:40px;height:20px;background:var(--sand);border-radius:20px 20px 0 0"></span><span style="left:10px;top:0;width:20px;height:20px;background:var(--leaf);border-radius:100% 0 100% 0"></span></span>
    <h2>Materi</h2>
    <p><?= $nMateri ? $nMateri . ' file materi kegiatan siap diunduh.' : 'Materi kegiatan akan tersedia di sini.' ?></p>
    <span class="go">Lihat materi</span>
  </a>
  <a class="card" href="<?= h(url('/galeri')) ?>">
    <span class="shape" aria-hidden="true"><span style="left:0;top:0;width:26px;height:26px;background:var(--sky);border-radius:0 50% 50% 50%"></span><span style="right:0;bottom:0;width:22px;height:22px;background:var(--coral);border-radius:50%"></span></span>
    <h2>Galeri</h2>
    <p><?= $nFoto ? $nFoto . ' foto dokumentasi kegiatan.' : 'Foto dokumentasi akan tersedia setelah kegiatan.' ?></p>
    <span class="go">Lihat galeri</span>
  </a>
</div>

<p class="note">Sudah registrasi? Buka <b>link instansi</b> yang Anda terima setelah registrasi untuk mengelola peserta, mengunggah foto kegiatan, dan mengajukan reimburse. Link hilang? <?= hubungi_panitia('Halo panitia ' . setting('nama_kegiatan') . ', link instansi kami hilang. Nama instansi: ') ?>.</p>
