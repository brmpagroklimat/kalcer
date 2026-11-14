<?php
/** @var array $old @var array $errors @var array $dates @var bool $open @var int $kuota @var string $stamp */
$o = static fn (string $k) => (string) ($old[$k] ?? '');
$ikut = is_string($old['ikut'] ?? null) ? $old['ikut'] : '';
?>
<h1>Pendaftaran</h1>
<p class="lead">Setiap instansi memilih satu tanggal dan mendaftarkan paling banyak <?= (int) $kuota ?> peserta. Setelah mendaftar, Anda mendapat link khusus instansi beserta panduan kegiatan.</p>
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
