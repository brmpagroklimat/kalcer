<?php
/** @var array $old @var array $errors @var array $dates @var bool $open @var int $kuota @var string $stamp */
$o = static fn (string $k) => (string) ($old[$k] ?? '');
$ikut = is_string($old['ikut'] ?? null) ? $old['ikut'] : '';
$lokasi = setting('lokasi');
?>
<section class="intro">
  <h1><?= h(setting('nama_kegiatan')) ?></h1>
  <p class="lead">
    <?= implode(' dan ', array_map(static fn ($d) => h(tgl($d, true)), $dates)) ?><?= $lokasi !== '' ? ', ' . h($lokasi) : '' ?>.
    Setiap instansi memilih satu tanggal dan mengirim paling banyak <?= (int) $kuota ?> orang.
  </p>
</section>

<div class="grid2" style="margin:8px 0 24px">
  <div class="panel">
    <h2>Alur kegiatan</h2>
    <ol class="steps">
      <li><b>Daftar</b> instansi dan peserta di halaman ini. Anda akan mendapat <b>link khusus instansi</b>.</li>
      <li><b>Sebelum acara</b>, lewat link itu Anda bisa mengubah atau mengganti peserta sampai <?= h(tgl_jam(setting('batas_ganti'))) ?>.</li>
      <li><b>Hari H</b>, unggah foto saat berangkat, tiba, kegiatan, pulang, dan tiba kembali. Waktu foto dibaca otomatis dari kamera.</li>
      <li><b>Setelah acara</b>, unggah struk BBM, tol, atau tiket kendaraan umum, isi rekening instansi, lalu ajukan reimburse.</li>
    </ol>
  </div>
  <div class="panel">
    <h2>Menginap & transport</h2>
    <p>Hotel disediakan panitia untuk malam sebelum acara: peserta <?= h(tgl_pendek($dates[0] ?? '')) ?> menginap malam <?= h(malam_menginap($dates[0] ?? '')) ?><?php if (isset($dates[1])): ?>, peserta <?= h(tgl_pendek($dates[1])) ?> menginap malam <?= h(malam_menginap($dates[1])) ?><?php endif; ?>. Peserta yang menginap diantar shuttle dari hotel ke lokasi.</p>
    <?php if (setting('info_hotel') !== ''): ?><p class="prewrap"><?= h(setting('info_hotel')) ?></p><?php endif; ?>
    <p class="small muted">Biaya perjalanan (BBM, tol, kendaraan umum) diganti at cost sesuai struk, ditransfer ke satu rekening per instansi.</p>
  </div>
</div>

<p class="note">Sudah mendaftar? Buka <b>link instansi</b> yang Anda terima setelah mendaftar. Link hilang? Hubungi panitia<?= setting('kontak_panitia') !== '' ? ': ' . h(setting('kontak_panitia')) : '' ?>.</p>

<?php if (!$open): ?>
  <div class="panel"><h2>Pendaftaran ditutup</h2><p>Pendaftaran tidak dibuka saat ini. Hubungi panitia untuk informasi lebih lanjut.</p></div>
<?php else: ?>
<form method="post" action="<?= h(url('/daftar')) ?>" class="panel" id="daftar" novalidate>
  <h2>Formulir pendaftaran</h2>
  <p class="muted small">Batas pendaftaran: <?= h(tgl_jam(setting('batas_daftar'))) ?>.</p>
  <?php if ($errors): ?>
    <div class="errors" role="alert" tabindex="-1" id="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="_stamp" value="<?= h($stamp) ?>">
  <label class="hp-field" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>

  <fieldset>
    <legend>Instansi</legend>
    <div class="fields two">
      <label class="field"><span>Nama instansi</span><input type="text" name="nama" value="<?= h($o('nama')) ?>" maxlength="200" required></label>
      <label class="field"><span>Kabupaten/kota</span><input type="text" name="kabkota" value="<?= h($o('kabkota')) ?>" maxlength="120" required></label>
      <label class="field"><span>Nama koordinator</span><input type="text" name="koordinator" value="<?= h($o('koordinator')) ?>" maxlength="150" required></label>
      <label class="field"><span>No. HP/WA koordinator</span><input type="tel" name="hp" value="<?= h($o('hp')) ?>" inputmode="tel" maxlength="30" required></label>
      <label class="field"><span>Email koordinator <small>(opsional, link instansi dikirim ke sini)</small></span><input type="email" name="email" value="<?= h($o('email')) ?>" maxlength="190"></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Pilih tanggal</legend>
    <div class="choices">
      <?php foreach ($dates as $d): ?>
        <label class="choice"><input type="radio" name="ikut" value="<?= h($d) ?>" data-day-toggle="<?= h($d) ?>" <?= $ikut === $d ? 'checked' : '' ?>><span><?= h(tgl($d, true)) ?></span></label>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-top:8px">Satu instansi hanya ikut satu tanggal.</p>
  </fieldset>

  <?php foreach ($dates as $d): ?>
    <div class="day-block" data-day="<?= h($d) ?>">
      <h3>Peserta <?= h(tgl($d, true)) ?></h3>
      <?php for ($i = 0; $i < max(1, $kuota); $i++): ?>
        <div class="person<?= $i > 0 ? ' optional' : '' ?>">
          <p class="person-title">Peserta <?= $i + 1 ?></p>
          <?= view('_person_fields', ['prefix' => "peserta[$d][$i]", 'val' => $old['peserta'][$d][$i] ?? [], 'idp' => "p-$d-$i", 'malam' => malam_menginap($d)]) ?>
        </div>
      <?php endfor; ?>
    </div>
  <?php endforeach; ?>

  <div class="actions">
    <button class="btn" type="submit">Daftarkan instansi</button>
    <span class="hint">Setelah mendaftar, Anda mendapat link khusus untuk mengelola peserta dan mengunggah bukti.</span>
  </div>
</form>
<?php endif; ?>
