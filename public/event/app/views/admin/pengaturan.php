<?php /** @var array $admins @var array $me */
$dt = static fn (string $k) => setting($k) !== '' ? date('Y-m-d\TH:i', strtotime(setting($k))) : '';
?>
<h1>Pengaturan</h1>
<form method="post" class="panel"><?= csrf_field() ?>
  <h2>Kegiatan</h2>
  <div class="fields two">
    <label class="field"><span>Nama kegiatan</span><input type="text" name="nama_kegiatan" value="<?= h(setting('nama_kegiatan')) ?>" required></label>
    <label class="field"><span>Lokasi kegiatan</span><input type="text" name="lokasi" value="<?= h(setting('lokasi')) ?>"></label>
    <label class="field"><span>Tanggal acara</span><input type="text" name="tanggal_acara" value="<?= h(setting('tanggal_acara')) ?>"><small>Format YYYY-MM-DD, dipisah koma. Hati-hati mengubahnya setelah ada pendaftar.</small></label>
    <label class="field" style="grid-column:1/-1"><span>Deskripsi kegiatan <small>(tampil di beranda)</small></span><textarea name="deskripsi" placeholder="Ringkasan kegiatan untuk calon peserta"><?= h(setting('deskripsi')) ?></textarea></label>
    <label class="field"><span>Kontak panitia</span><input type="text" name="kontak_panitia" value="<?= h(setting('kontak_panitia')) ?>" placeholder="Nama, 08xx (WA)"></label>
  </div>
  <h2 style="margin-top:22px">Pendaftaran & batas waktu</h2>
  <label class="check" style="margin-bottom:14px"><input type="checkbox" name="daftar_dibuka" value="1" <?= setting('daftar_dibuka') === '1' ? 'checked' : '' ?>> Pendaftaran dibuka</label>
  <div class="fields two">
    <label class="field"><span>Batas pendaftaran</span><input type="datetime-local" name="batas_daftar" value="<?= h($dt('batas_daftar')) ?>"></label>
    <label class="field"><span>Batas perubahan peserta</span><input type="datetime-local" name="batas_ganti" value="<?= h($dt('batas_ganti')) ?>"></label>
    <label class="field"><span>Batas upload foto & bukti biaya</span><input type="datetime-local" name="batas_upload" value="<?= h($dt('batas_upload')) ?>"></label>
    <label class="field"><span>Maks. peserta per instansi</span><input type="number" min="1" name="kuota_per_instansi" value="<?= h(setting('kuota_per_instansi')) ?>"></label>
    <label class="field"><span>Kuota peserta per hari <small>(0 = tanpa batas)</small></span><input type="number" min="0" name="kuota_per_hari" value="<?= h(setting('kuota_per_hari')) ?>"></label>
  </div>
  <h2 style="margin-top:22px">Info untuk instansi</h2>
  <div class="fields two">
    <label class="field"><span>Hotel</span><textarea name="info_hotel" placeholder="Nama hotel, alamat, jam check-in"><?= h(setting('info_hotel')) ?></textarea></label>
    <label class="field"><span>Shuttle</span><textarea name="info_shuttle" placeholder="Jam keberangkatan shuttle dari hotel, titik kumpul"><?= h(setting('info_shuttle')) ?></textarea></label>
  </div>
  <div class="actions"><button class="btn deep">Simpan pengaturan</button></div>
</form>

<div class="grid2" id="akun">
  <section class="panel">
    <h2>Akun panitia</h2>
    <ul class="people">
      <?php foreach ($admins as $a): ?>
        <li><div class="who"><b><?= h($a['nama']) ?></b> <span class="chip"><?= h($a['username']) ?></span></div>
          <?php if ((int) $a['id'] !== (int) $me['id']): ?>
            <form method="post" action="<?= h(url('/admin/akun')) ?>" data-confirm="Hapus akun <?= h($a['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="linkbtn" style="color:var(--err-ink)">Hapus</button></form>
          <?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <h3 style="margin-top:16px">Tambah akun</h3>
    <form method="post" action="<?= h(url('/admin/akun')) ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
      <div class="fields">
        <label class="field"><span>Nama</span><input type="text" name="nama" required></label>
        <label class="field"><span>Username</span><input type="text" name="username" autocapitalize="off" required></label>
        <label class="field"><span>Password <small>(minimal 10 karakter)</small></span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
      </div>
      <div class="actions"><button class="btn small secondary">Tambah akun</button></div>
    </form>
  </section>
  <section class="panel">
    <h2>Ganti password saya</h2>
    <form method="post" action="<?= h(url('/admin/akun')) ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="password">
      <div class="fields">
        <label class="field"><span>Password lama</span><input type="password" name="lama" autocomplete="current-password" required></label>
        <label class="field"><span>Password baru</span><input type="password" name="baru" minlength="10" autocomplete="new-password" required></label>
      </div>
      <div class="actions"><button class="btn small secondary">Ganti password</button></div>
    </form>
    <p class="small muted" style="margin-top:16px">Email pengiriman link: <?= mail_enabled() ? 'aktif' : 'tidak aktif (isi <code>smtp</code> di event-config.php untuk mengaktifkan)' ?>.</p>
  </section>
</div>
