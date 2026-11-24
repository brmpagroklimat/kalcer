<?php
/** @var array $old @var array $errors @var array $pilih @var string $sumber @var ?array $inst @var array $daftarInstansi @var string $stamp */
$o = static fn (string $k) => (string) ($old[$k] ?? '');
// null = belum pernah diisi (supaya "Tidak dicoba" tidak terpilih otomatis).
$om = static fn (string $slug, string $k): ?string => isset($old['m'][$slug][$k]) ? (string) $old['m'][$slug][$k] : null;
/** Skala radio (pill) 1..$max; $na = label pilihan "tidak dicoba" (nilai kosong). */
$skala = static function (string $name, int $max, ?string $val, string $idp, ?string $na = null, array $ujung = []): string {
    $out = '<div class="skala" role="radiogroup" aria-labelledby="' . h($idp) . '">';
    for ($i = 1; $i <= $max; $i++) {
        $out .= '<label class="choice"><input type="radio" name="' . h($name) . '" value="' . $i . '"' . ($val === (string) $i ? ' checked' : '') . '><span>' . $i . '</span></label>';
    }
    if ($na !== null) {
        $out .= '<label class="choice na"><input type="radio" name="' . h($name) . '" value=""' . ($val === '' ? ' checked' : '') . '><span>' . h($na) . '</span></label>';
    }
    $out .= '</div>';
    if ($ujung) {
        $out .= '<div class="skala-ujung small muted"><span>' . h($ujung[0]) . '</span><span>' . h($ujung[1]) . '</span></div>';
    }
    return $out;
};
$cek = static fn (array $list, string $name, string $val) => implode('', array_map(
    static fn ($k, $l) => '<label class="choice"><input type="radio" name="' . h($name) . '" value="' . h($k) . '"' . ($val === $k ? ' checked' : '') . '><span>' . h($l) . '</span></label>',
    array_keys($list), $list
));
?>
<h1>Beri masukan untuk KALCER</h1>
<p class="lead">Masukan Anda dipakai untuk menyempurnakan isi dan tampilan keenam modul KALCER. Pilih modul yang sudah Anda coba; cukup isi bagian modul itu saja. Sekitar 5 menit.</p>

<form method="post" action="<?= h(url('/masukan')) ?>" class="masukan" id="form-masukan" novalidate>
  <?php if ($errors): ?>
    <div class="errors" role="alert" tabindex="-1" id="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="_stamp" value="<?= h($stamp) ?>">
  <input type="hidden" name="sumber" value="<?= h($sumber) ?>">
  <label class="hp-field" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>

  <section class="panel">
    <h2>1. Tentang Anda</h2>
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
  </section>

  <section class="panel">
    <h2>2. Modul yang Anda coba</h2>
    <p class="small muted" style="margin-top:0">Boleh lebih dari satu. Bagian penilaian muncul untuk modul yang dicentang.</p>
    <div class="modul-pilih">
      <?php foreach (MODUL as $slug => $m): ?>
        <label class="modul-cek"><input type="checkbox" name="modul[]" value="<?= h($slug) ?>" data-modul-toggle="<?= h($slug) ?>" <?= in_array($slug, $pilih, true) ? 'checked' : '' ?>>
          <span><b><?= h($m['nama']) ?></b><span class="small muted"><?= h($m['isi']) ?></span></span></label>
      <?php endforeach; ?>
    </div>
  </section>

  <?php foreach (MODUL as $slug => $m): $p = "m[$slug]"; $id = "m-$slug"; ?>
  <section class="panel modul-blok" data-modul="<?= h($slug) ?>" id="<?= h($id) ?>">
    <div class="panel-head"><h2><?= h($m['nama']) ?></h2><a class="small" href="<?= h($m['url']) ?>" target="_blank" rel="noopener">Buka modul<span class="sr-only"> (tab baru)</span> ↗</a></div>
    <p class="note tugas"><span class="small muted">Tugas praktik</span><?= h($m['tugas']) ?></p>

    <h3>Isi (substansi)</h3>
    <div class="field"><span class="field-label" id="<?= $id ?>-sesuai"><?= h($m['substansi']) ?></span>
      <div class="choices" role="radiogroup" aria-labelledby="<?= $id ?>-sesuai"><?= $cek(SESUAI, $p . '[sesuai]', (string) $om($slug, 'sesuai')) ?></div></div>
    <div class="field"><span class="field-label" id="<?= $id ?>-terap"><?= $slug === 'gita' ? 'Apakah jawabannya bisa langsung dipakai?' : 'Apakah hasil atau rekomendasinya bisa diterapkan di lapangan?' ?> <small>(1 = tidak bisa, 5 = sangat bisa)</small></span>
      <?= $skala($p . '[diterapkan]', 5, $om($slug, 'diterapkan'), "$id-terap") ?></div>
    <label class="field"><span><?= $slug === 'gita' ? 'Topik atau dokumen apa yang belum dijangkau GITA?' : 'Data atau parameter apa yang masih kurang?' ?> <small>(opsional)</small></span>
      <textarea name="<?= h($p) ?>[data_kurang]" rows="2" maxlength="3000"><?= h((string) $om($slug, 'data_kurang')) ?></textarea></label>
    <?php if ($slug === 'gita'): ?>
      <label class="field"><span>Contoh pertanyaan yang jawabannya salah atau kurang, dan apa yang benar <small>(opsional, sangat membantu)</small></span>
        <textarea name="<?= h($p) ?>[gita_salah]" rows="3" maxlength="3000" placeholder="Pertanyaan: … / Jawaban GITA: … / Yang benar: …"><?= h((string) $om($slug, 'gita_salah')) ?></textarea></label>
    <?php endif; ?>

    <h3>Tampilan (interface)</h3>
    <p class="small muted" style="margin-top:0">1 = sangat sulit/buruk, 5 = sangat mudah/baik. Pilih "Tidak dicoba" bila tidak relevan.</p>
    <?php foreach (UI_BUTIR as $k => $label):
      if ($k === 'ui_peta' && !$m['peta']) { continue; }
      $lbl = $k === 'ui_peta' ? $label : ($k === 'ui_menu' && $slug === 'gita' ? 'Kemudahan bertanya & membaca jawaban' : $label); ?>
      <div class="field ui-butir"><span class="field-label" id="<?= $id . '-' . $k ?>"><?= h($lbl) ?></span>
        <?= $skala($p . "[$k]", 5, $om($slug, $k), "$id-$k", 'Tidak dicoba') ?></div>
    <?php endforeach; ?>
    <label class="field"><span>Bagian mana yang membingungkan? <small>(opsional)</small></span>
      <textarea name="<?= h($p) ?>[bingung]" rows="2" maxlength="3000"><?= h((string) $om($slug, 'bingung')) ?></textarea></label>
  </section>
  <?php endforeach; ?>

  <section class="panel">
    <h2>3. Kesan keseluruhan</h2>
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
