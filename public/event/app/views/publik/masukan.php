<?php
/** @var array $old @var array $errors @var array $pilih @var string $sumber @var ?array $inst @var array $daftarInstansi @var string $stamp */
$o = static fn (string $k) => (string) ($old[$k] ?? '');
// null = belum pernah diisi (supaya "Tidak dicoba" tidak terpilih otomatis).
$om = static fn (string $slug, string $k): ?string => isset($old['m'][$slug][$k]) ? (string) $old['m'][$slug][$k] : null;
/** Skala radio (pill) 1..$max; $na = label pilihan "tidak dicoba" (nilai kosong). */
$skala = static function (string $name, int $max, ?string $val, string $idp, ?string $na = null, array $ujung = []): string {
    $out = '<div class="skala" role="radiogroup" aria-labelledby="' . h($idp) . '"><div class="skala-angka"><div class="skala-row">';
    for ($i = 1; $i <= $max; $i++) {
        $out .= '<label class="choice"><input type="radio" name="' . h($name) . '" value="' . $i . '"' . ($val === (string) $i ? ' checked' : '') . '><span>' . $i . '</span></label>';
    }
    $out .= '</div>';
    if ($ujung) {
        $out .= '<div class="skala-garis" aria-hidden="true"></div><div class="skala-ujung small muted"><span>' . h($ujung[0]) . '</span><span>' . h($ujung[1]) . '</span></div>';
    }
    $out .= '</div>';
    if ($na !== null) {
        $out .= '<label class="choice na"><input type="radio" name="' . h($name) . '" value=""' . ($val === '' ? ' checked' : '') . '><span>' . h($na) . '</span></label>';
    }
    return $out . '</div>';
};
$cek = static fn (array $list, string $name, string $val) => implode('', array_map(
    static fn ($k, $l) => '<label class="choice"><input type="radio" name="' . h($name) . '" value="' . h($k) . '"' . ($val === $k ? ' checked' : '') . '><span>' . h($l) . '</span></label>',
    array_keys($list), $list
));
?>
<?php $semua = count($pilih) === count(MODUL); $nm = count($pilih); ?>
<?php if ($semua): ?>
<h1>Uji coba &amp; masukan KALCER</h1>
<p class="lead">Mohon coba keenam modul KALCER dengan memasukkan wilayah Anda sendiri, lalu nilai isi dan tampilannya. Satu modul satu halaman, sekitar 2 menit per modul. Bagian yang belum sempat dicoba cukup ditandai <b>Belum bisa menilai</b> atau <b>Tidak dicoba</b>.</p>
<?php else: ?>
<h1>Beri masukan: <?= h(implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'], $pilih))) ?></h1>
<p class="lead">Nilai apakah isinya sesuai kondisi lapangan dan tampilannya mudah dipakai. Sekitar 2 menit.</p>
<?php endif; ?>

<form method="post" action="<?= h(url('/masukan')) ?>" class="masukan" id="form-masukan" novalidate data-langkah-form>
  <?php if ($errors): ?>
    <div class="errors" role="alert" tabindex="-1" id="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="_stamp" value="<?= h($stamp) ?>">
  <input type="hidden" name="sumber" value="<?= h($sumber) ?>">
  <label class="hp-field" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>

  <section class="panel" data-langkah data-judul="Tentang Anda">
    <h2>Tentang Anda</h2>
    <div class="fields two">
      <label class="field"><span>Nama <small>(opsional)</small></span><input type="text" name="nama" value="<?= h($o('nama')) ?>" maxlength="150" autocomplete="name"></label>
      <?php if ($inst): ?>
        <div class="field"><span>Instansi</span><p class="terisi"><b><?= h($inst['nama']) ?></b> <span class="kode"><?= h(kode_instansi((int) $inst['id'])) ?></span><br><span class="small muted">Terisi dari link instansi peserta sosialisasi.</span></p></div>
      <?php else: ?>
        <?php if ($daftarInstansi): $ip = $o('instansi_pilih'); ?>
        <div><label class="field"><span>Instansi</span>
          <select id="instansi-pilih" name="instansi_pilih" required autocomplete="off" data-instansi-pilih>
            <option value="">Pilih instansi…</option>
            <?php foreach ($daftarInstansi as $di): ?><option value="<?= h($di['nama']) ?>" data-provinsi="<?= h($di['provinsi']) ?>" <?= $ip === $di['nama'] ? 'selected' : '' ?>><?= h($di['nama']) ?></option><?php endforeach; ?>
            <option value="__lain" <?= $ip === '__lain' ? 'selected' : '' ?>>Lainnya (ketik nama instansi)</option>
          </select></label>
          <input type="text" name="instansi" value="<?= h($o('instansi')) ?>" maxlength="200" placeholder="Nama instansi Anda" aria-label="Nama instansi lainnya" data-instansi-lain <?= $ip === '__lain' ? '' : 'hidden' ?> style="margin-top:8px;width:100%"></div>
        <?php else: ?>
        <label class="field"><span>Instansi</span><input type="text" name="instansi" value="<?= h($o('instansi')) ?>" maxlength="200" required placeholder="mis. BPP Cibinong"></label>
        <?php endif; ?>
      <?php endif; ?>
      <div class="field full"><span class="field-label" id="lbl-peran">Peran</span>
        <div class="choices" role="radiogroup" aria-labelledby="lbl-peran"><?= $cek(PERAN, 'peran', $o('peran')) ?></div></div>
      <label class="field"><span>Provinsi</span><select name="provinsi" required autocomplete="off">
        <option value="">Pilih provinsi…</option>
        <?php $pv0 = $o('provinsi') ?: (string) ($inst['provinsi'] ?? ''); foreach (provinsi_list() as $pv): ?><option <?= $pv === $pv0 ? 'selected' : '' ?>><?= h($pv) ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <?php if ($semua): ?>
      <p class="small muted" style="margin:18px 0 8px">Setelah ini, Anda menilai satu modul per halaman:</p>
      <ol class="modul-urut"><?php foreach (MODUL as $m): ?><li><?= h($m['nama']) ?></li><?php endforeach; ?></ol>
    <?php else: ?>
      <p class="note small" style="margin-top:16px">Anda membuka form ini dari modul <?= h(implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'], $pilih))) ?>. Ingin menguji semua modul? <a href="<?= h(url('/masukan?src=langsung')) ?>">Nilai keenam modul KALCER</a>.</p>
    <?php endif; ?>
  </section>

  <?php foreach ($pilih as $slug): ?><input type="hidden" name="modul[]" value="<?= h($slug) ?>"><?php endforeach; ?>

  <?php foreach ($pilih as $no => $slug): $m = MODUL[$slug]; $p = "m[$slug]"; $id = "m-$slug"; ?>
  <section class="panel modul-blok" data-modul="<?= h($slug) ?>" id="<?= h($id) ?>" data-langkah data-judul="<?= h($m['nama']) ?>">
    <h2><?= $nm > 1 ? '<span class="modul-ke"><b>Modul ' . ($no + 1) . '</b> dari ' . $nm . '<span class="modul-titik" aria-hidden="true">' . str_repeat('<i class="isi"></i>', $no + 1) . str_repeat('<i></i>', $nm - $no - 1) . '</span></span>' : '' ?><?= h($m['nama']) ?></h2>
    <div class="note tugas"><span class="small muted">Sebelum menjawab</span><p><?= h($m['tugas']) ?> Setelah itu, mohon jawab pertanyaan di bawah ini.</p>
      <a class="btn secondary small" href="<?= h($m['url']) ?>" target="_blank" rel="noopener">Buka <?= h($m['nama']) ?><span class="sr-only"> (tab baru)</span> ↗</a></div>

    <h3>Isi (substansi)</h3>
    <p class="small muted">Pertanyaan bertanda <span class="wajib">*</span> wajib dijawab.</p>
    <div class="field"><span class="field-label" id="<?= $id ?>-sesuai"><?= h($m['substansi']) ?> <span class="wajib" aria-hidden="true">*</span></span>
      <div class="choices" role="radiogroup" aria-labelledby="<?= $id ?>-sesuai"><?= $cek(SESUAI, $p . '[sesuai]', (string) $om($slug, 'sesuai')) ?></div></div>
    <div class="field"><span class="field-label" id="<?= $id ?>-terap"><?= $slug === 'gita' ? 'Menurut Bapak/Ibu, apakah jawabannya bisa langsung dipakai?' : 'Menurut Bapak/Ibu, apakah hasil atau rekomendasinya bisa diterapkan di lapangan?' ?></span>
      <?= $skala($p . '[diterapkan]', 5, $om($slug, 'diterapkan'), "$id-terap", null, ['Tidak bisa', 'Sangat bisa']) ?></div>

    <h3>Tampilan (interface)</h3>
    <?php foreach (UI_BUTIR as $k => $label):
      if ($k === 'ui_peta' && !$m['peta']) { continue; }
      $lbl = $k === 'ui_peta' ? $label : ($k === 'ui_menu' && $slug === 'gita' ? 'Kemudahan bertanya & membaca jawaban' : $label); ?>
      <div class="field ui-butir"><span class="field-label" id="<?= $id . '-' . $k ?>"><?= h($lbl) ?></span>
        <?= $skala($p . "[$k]", 5, $om($slug, $k), "$id-$k", 'Tidak dicoba', ['Sangat sulit', 'Sangat mudah']) ?></div>
    <?php endforeach; ?>
    <h3>Catatan <small class="muted" style="font-weight:400">(opsional)</small></h3>
    <label class="field"><span><?= $slug === 'gita' ? 'Topik atau dokumen apa yang belum dijangkau GITA?' : 'Data atau parameter apa yang masih kurang?' ?></span>
      <textarea name="<?= h($p) ?>[data_kurang]" rows="2" maxlength="3000"><?= h((string) $om($slug, 'data_kurang')) ?></textarea></label>
    <label class="field"><span>Bagian mana yang membingungkan?</span>
      <textarea name="<?= h($p) ?>[bingung]" rows="2" maxlength="3000"><?= h((string) $om($slug, 'bingung')) ?></textarea></label>
  </section>
  <?php endforeach; ?>

  <section class="panel" data-langkah data-judul="Kesan keseluruhan">
    <h2>Kesan keseluruhan</h2>
    <?php foreach (UMUX as $k => $label): ?>
      <div class="field"><span class="field-label" id="lbl-<?= $k ?>"><?= h($label) ?></span>
        <?= $skala($k, 7, $o($k) === '' ? null : $o($k), "lbl-$k", null, ['Sangat tidak setuju', 'Sangat setuju']) ?></div>
    <?php endforeach; ?>
    <div class="field"><span class="field-label" id="lbl-lagi">Apakah akan Anda gunakan lagi?</span>
      <div class="choices" role="radiogroup" aria-labelledby="lbl-lagi"><?= $cek(PAKAI_LAGI, 'pakai_lagi', $o('pakai_lagi')) ?></div></div>
    <label class="field"><span>Saran fitur atau perbaikan <small>(opsional)</small></span>
      <textarea name="saran" rows="3" maxlength="3000"><?= h($o('saran')) ?></textarea></label>
    <label class="check" style="margin-top:12px"><input type="checkbox" name="bersedia" value="1" data-bersedia <?= $o('bersedia') === '1' ? 'checked' : '' ?>>
      <span>Saya bersedia dihubungi untuk uji coba lanjutan.</span></label>
    <label class="field" data-hp-bersedia <?= $o('bersedia') === '1' ? '' : 'hidden' ?> style="margin-top:10px;max-width:320px"><span>No. HP/WA <small>(angka saja)</small></span>
      <input type="tel" name="hp" value="<?= h($o('hp')) ?>" inputmode="numeric" data-validate="hp" placeholder="081234567890"></label>
  </section>

  <div class="actions">
    <button class="btn" type="submit">Kirim masukan</button>
    <span class="hint">Data Anda hanya dipakai tim BRMP Agroklimat untuk menyempurnakan KALCER.</span>
  </div>
</form>
