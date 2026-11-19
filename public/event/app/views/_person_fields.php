<?php
/** Field data satu peserta. @var string $prefix nama input (mis. "peserta[2026-12-02][0]") atau '' @var array $val @var string $idp */
$n = static fn (string $k) => $prefix === '' ? $k : $prefix . '[' . $k . ']';
$v = static fn (string $k) => (string) ($val[$k] ?? '');
$inap = $v('menginap');
if ($inap === '1') { $inap = 'ya'; } elseif ($inap === '0') { $inap = 'tidak'; }
?>
<div class="fields two">
  <label class="field"><span>Nama lengkap (dengan gelar)</span>
    <input type="text" data-role="nama" name="<?= h($n('nama')) ?>" value="<?= h($v('nama')) ?>" maxlength="150" autocomplete="off" <?= !empty($required) ? 'required' : '' ?>></label>
  <label class="field"><span>NIP <small>(opsional, 18 digit)</small></span>
    <input type="text" name="<?= h($n('nip')) ?>" value="<?= h($v('nip')) ?>" inputmode="numeric" maxlength="18" autocomplete="off" data-validate="nip"></label>
  <label class="field"><span>Jabatan</span>
    <input type="text" name="<?= h($n('jabatan')) ?>" value="<?= h($v('jabatan')) ?>" maxlength="150" autocomplete="off"></label>
  <label class="field"><span>No. HP/WA <small>(angka saja)</small></span>
    <input type="tel" name="<?= h($n('hp')) ?>" value="<?= h($v('hp')) ?>" inputmode="numeric" autocomplete="off" data-validate="hp" placeholder="081234567890"></label>
</div>
<div class="fields two" style="margin-top:14px">
  <div class="field"><span class="field-label" id="<?= h($idp) ?>-jk">Jenis kelamin <small>(untuk pembagian kamar)</small></span>
    <div class="choices" role="radiogroup" aria-labelledby="<?= h($idp) ?>-jk">
      <label class="choice"><input type="radio" name="<?= h($n('jk')) ?>" value="L" <?= $v('jk') === 'L' ? 'checked' : '' ?>><span>Laki-laki</span></label>
      <label class="choice"><input type="radio" name="<?= h($n('jk')) ?>" value="P" <?= $v('jk') === 'P' ? 'checked' : '' ?>><span>Perempuan</span></label>
    </div>
  </div>
  <div class="field"><span class="field-label" id="<?= h($idp) ?>-inap">Menginap di hotel panitia<?= !empty($malam) ? ' <small>(malam ' . h($malam) . ')</small>' : '' ?></span>
    <div class="choices" role="radiogroup" aria-labelledby="<?= h($idp) ?>-inap">
      <label class="choice"><input type="radio" name="<?= h($n('menginap')) ?>" value="ya" <?= $inap === 'ya' ? 'checked' : '' ?>><span>Menginap</span></label>
      <label class="choice"><input type="radio" name="<?= h($n('menginap')) ?>" value="tidak" <?= $inap === 'tidak' ? 'checked' : '' ?>><span>Tidak menginap</span></label>
    </div>
  </div>
</div>
<label class="check small share-contact" style="margin-top:12px" <?= $inap === 'ya' ? '' : 'hidden' ?>>
  <input type="checkbox" name="<?= h($n('bagikan_kontak')) ?>" value="1" <?= $v('bagikan_kontak') === '1' ? 'checked' : '' ?>>
  <span>Boleh tampilkan No. HP/WA saya ke teman sekamar (untuk koordinasi check-in).</span>
</label>
