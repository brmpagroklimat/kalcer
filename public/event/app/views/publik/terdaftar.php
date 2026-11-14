<?php /** @var array $inst @var string $link @var bool $mailed */
$wa = 'Link instansi ' . $inst['nama'] . ' untuk ' . setting('nama_kegiatan') . ' (ubah peserta, upload foto & bukti biaya): ' . $link;
?>
<h1>Pendaftaran berhasil</h1>
<p class="lead"><?= h($inst['nama']) ?> terdaftar dengan kode <b><?= h(kode_instansi((int) $inst['id'])) ?></b>.</p>

<div class="panel">
  <h2>Simpan link instansi ini</h2>
  <p>Link ini adalah satu-satunya cara masuk untuk mengubah peserta, mengunggah foto kegiatan, dan mengajukan reimburse. Bagikan hanya ke rombongan instansi Anda.</p>
  <div class="linkbox">
    <label class="sr-only" for="link">Link instansi</label>
    <input id="link" type="text" readonly value="<?= h($link) ?>">
    <button class="btn deep" type="button" data-copy="#link">Salin link</button>
  </div>
  <div class="actions">
    <a class="btn secondary" href="<?= h(wa_link($wa)) ?>" target="_blank" rel="noopener">Kirim ke WhatsApp</a>
    <a class="btn" href="<?= h(url('/saya')) ?>">Lanjut ke halaman instansi</a>
  </div>
  <p class="small muted" style="margin-top:12px">
    <?= $mailed ? 'Link juga sudah dikirim ke ' . h($inst['email']) . '.' : 'Link tidak dikirim lewat email, jadi pastikan Anda menyalin atau mengirimkannya ke WhatsApp sekarang.' ?>
    Browser ini tetap masuk ke halaman instansi selama beberapa minggu.
  </p>
</div>
