<?php /** @var string $tanggal @var array $orang @var array $mobil @var array $belum @var array $pending @var array $errors */
$kapasitas = array_sum(array_map(static fn ($k) => (int) $k['kapasitas'], $mobil));
$terisi = array_filter($mobil, static fn ($k) => $k['penumpang']);
$publikN = count(array_filter($terisi, static fn ($k) => $k['publik']));
$hidden = '<input type="hidden" name="tanggal" value="' . h($tanggal) . '">';
$steps = [
    ['Kendaraan', 'Tambahkan mobil/bus: kapasitas, jam berangkat, supir & PIC teknis.', $mobil && $kapasitas >= count($orang), count($mobil) . ' kendaraan, ' . $kapasitas . ' kursi untuk ' . count($orang) . ' orang'],
    ['Bagi penumpang', 'Klik "Bagi otomatis", lalu periksa. Satu orang hanya di satu kendaraan.', $orang && !$belum, $belum ? count($belum) . ' orang belum dapat kendaraan' : (count($orang) ? 'semua dapat kendaraan' : 'belum ada yang menginap')],
    ['Publikasikan', 'Peserta melihat kendaraan, jam, titik jemput, dan kontak supir/PIC.', $terisi && $publikN === count($terisi), $publikN . ' dari ' . count($terisi) . ' kendaraan'],
];
$opsi = static function (?int $sel) use ($mobil): string {
    $h = '<option value="">Belum dapat kendaraan</option>';
    foreach ($mobil as $id => $k) {
        $h .= '<option value="' . (int) $id . '"' . ($sel === (int) $id ? ' selected' : '') . '>' . h(kendaraan_label($k) . ' (' . count($k['penumpang']) . '/' . $k['kapasitas'] . ')') . '</option>';
    }
    return $h;
};
$kolom = ['nama' => ['Nama kendaraan', 'mis. Bus 1', 'text'], 'plat' => ['Plat', 'B 1234 XY', 'text'], 'kapasitas' => ['Kapasitas', '', 'number'], 'jam' => ['Jam berangkat', '', 'time'],
    'titik_jemput' => ['Titik jemput', 'Lobi hotel', 'text'], 'supir_nama' => ['Supir', 'Nama', 'text'], 'supir_hp' => ['HP supir', '08…', 'tel'], 'pic_nama' => ['PIC teknis', 'Nama', 'text'], 'pic_hp' => ['HP PIC', '08…', 'tel']];
$input = static function (string $k, $id, $val) use ($kolom): string {
    [$label, $ph, $type] = $kolom[$k];
    $extra = $type === 'number' ? ' min="1" max="60"' : ($type === 'tel' ? ' inputmode="numeric" data-validate="hp"' : '');
    $v = $k === 'jam' && $val ? substr((string) $val, 0, 5) : (string) $val;
    return '<input type="' . $type . '" name="' . $k . '[' . h((string) $id) . ']" value="' . h($v) . '" placeholder="' . h($ph) . '" aria-label="' . h($label) . '"' . $extra . ($k === 'nama' && $id !== 'baru' ? ' required' : '') . '>';
};
?>
<h1>Shuttle hotel → lokasi</h1>
<nav class="tabs" aria-label="Tanggal acara" style="margin-bottom:18px">
  <?php foreach (event_dates() as $d): ?>
    <a href="<?= h(url('/admin/shuttle?tanggal=' . $d)) ?>" <?= $tanggal === $d ? 'aria-current="page"' : '' ?>><?= h(tgl($d, true)) ?></a>
  <?php endforeach; ?>
</nav>
<p class="small muted">Penumpang = peserta yang menginap malam <?= h(malam_menginap($tanggal)) ?> dan tamu panitia/pejabat di menu Kamar (<?= count($orang) ?> orang).</p>

<ol class="steps-flow" aria-label="Alur shuttle">
  <?php foreach ($steps as $i => [$judul, $ket, $done, $status]): ?>
    <li class="<?= $done ? 'done' : '' ?>"><a href="#langkah-<?= $i + 1 ?>">
      <span class="num" aria-hidden="true"><?= $done ? '✓' : $i + 1 ?></span>
      <span><b><?= h($judul) ?></b><span class="small"><?= h($ket) ?></span><span class="status"><?= h($status) ?></span></span></a></li>
  <?php endforeach; ?>
</ol>

<section class="panel" id="langkah-1">
  <div class="panel-head"><h2>1. Kendaraan</h2><a class="btn small secondary" href="<?= h(url('/admin/shuttle/manifest.csv?tanggal=' . $tanggal)) ?>">Unduh manifest (CSV)</a></div>
  <form method="post" action="<?= h(url('/admin/shuttle/kendaraan')) ?>"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table mobil-table">
      <thead><tr><?php foreach ($kolom as [$label]): ?><th><?= h($label) ?></th><?php endforeach; ?><th>Isi</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mobil as $id => $k): ?>
        <tr><?php foreach (array_keys($kolom) as $c): ?><td><?= $input($c, $id, $k[$c]) ?></td><?php endforeach; ?>
          <td class="small"><?= count($k['penumpang']) ?>/<?= (int) $k['kapasitas'] ?></td>
          <td><button class="linkbtn danger-link small" formaction="<?= h(url('/admin/shuttle/kendaraan/' . $id . '/hapus')) ?>" formnovalidate onclick="return confirm('Hapus kendaraan ini? Penumpangnya kembali ke belum dapat kendaraan.')">Hapus</button></td></tr>
      <?php endforeach; ?>
        <tr class="row-new"><?php foreach (array_keys($kolom) as $c): ?><td><?= $input($c, 'baru', $c === 'kapasitas' ? 10 : ($c === 'titik_jemput' ? 'Lobi hotel' : '')) ?></td><?php endforeach; ?><td colspan="2" class="small muted">Baris baru</td></tr>
      </tbody>
    </table></div>
    <p class="small muted">Isi baris baru untuk menambah kendaraan. Supir & PIC teknis tampil ke peserta (dengan tombol WhatsApp) setelah dipublikasikan.</p>
    <div class="actions"><button class="btn deep">Simpan kendaraan</button></div>
  </form>
</section>

<section class="panel" id="langkah-2">
  <div class="panel-head"><h2>2. Bagi penumpang</h2>
    <?php if ($belum && $mobil): ?>
      <form method="post" action="<?= h(url('/admin/shuttle/bagi')) ?>"><?= csrf_field() . $hidden ?><button class="btn deep">Bagi otomatis (<?= count($belum) ?> orang)</button></form>
    <?php endif; ?>
  </div>
  <p class="small muted">Pembagian otomatis mengusahakan satu rombongan instansi satu kendaraan, mengisi kendaraan yang berangkat lebih dulu. Ubah lewat dropdown lalu <b>Simpan penempatan</b>; kendaraan yang kelebihan penumpang ditolak.</p>
  <?php if ($errors): ?><div class="errors" role="alert"><b>Belum disimpan:</b><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$orang): ?><p class="muted" style="margin:0">Belum ada yang menginap malam ini.</p>
  <?php elseif (!$mobil): ?><p class="note warn" style="margin:0">Tambahkan kendaraan di langkah 1 terlebih dahulu.</p>
  <?php else: ?>
  <form method="post" action="<?= h(url('/admin/shuttle/penempatan')) ?>"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th>#</th><th>Nama</th><th>Instansi / keterangan</th><th>Kamar</th><th>Kendaraan</th></tr></thead>
      <tbody>
      <?php $n = 0; $rooms = kamar_list($tanggal, $orang); foreach ($orang as $key => $o):
        $sel = array_key_exists($key, $pending) ? ($pending[$key] === '' ? null : (int) $pending[$key]) : (isset($mobil[$o['shuttle_id']]) ? (int) $o['shuttle_id'] : null); ?>
        <tr class="<?= isset($mobil[$o['shuttle_id']]) ? '' : 'row-warn' ?>">
          <td class="num"><?= ++$n ?></td>
          <td><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td>
          <td class="small"><?= h($o['instansi']) ?></td>
          <td class="small"><?= isset($rooms[$o['kamar_id']]) ? h(kamar_label($rooms[$o['kamar_id']])) : '<span class="muted">-</span>' ?></td>
          <td><select class="kamar-select" name="kendaraan[<?= h($key) ?>]" aria-label="Kendaraan untuk <?= h($o['nama']) ?>"><?= $opsi($sel) ?></select></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="actions"><button class="btn deep">Simpan penempatan</button></div>
  </form>
  <?php endif; ?>
</section>

<section class="panel" id="langkah-3">
  <h2>3. Publikasikan ke peserta</h2>
  <p class="small muted">Centang kendaraan yang sudah final. Penumpangnya melihat nama kendaraan, plat, jam berangkat, titik jemput, serta WhatsApp supir & PIC teknis di halaman instansi.</p>
  <?php if (!$terisi): ?><p class="muted" style="margin:0">Belum ada kendaraan yang berpenumpang.</p><?php else: ?>
  <form method="post" action="<?= h(url('/admin/shuttle/publikasi')) ?>"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th><label class="check small"><input type="checkbox" data-check-all="publik[]" aria-label="Pilih semua kendaraan"> Tampilkan</label></th><th>Kendaraan</th><th>Berangkat</th><th>Penumpang</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($terisi as $id => $k): ?>
        <tr>
          <td><input type="checkbox" name="publik[]" value="<?= (int) $id ?>" <?= $k['publik'] ? 'checked' : '' ?> aria-label="Tampilkan <?= h($k['nama']) ?>"></td>
          <td><b><?= h($k['nama']) ?></b><?= $k['plat'] ? '<br><span class="small muted">' . h($k['plat']) . '</span>' : '' ?></td>
          <td class="small"><?= $k['jam'] ? h(jam($k['jam'])) : '<span class="badge warn">Jam belum diisi</span>' ?><?= $k['titik_jemput'] ? '<br>' . h($k['titik_jemput']) : '' ?></td>
          <td class="small"><?= count($k['penumpang']) ?>/<?= (int) $k['kapasitas'] ?>: <?= h(implode(', ', array_column($k['penumpang'], 'nama'))) ?></td>
          <td><?= $k['publik'] ? '<span class="badge ok">Terlihat peserta</span>' : '<span class="badge draft">Belum</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="actions"><button class="btn deep">Simpan publikasi</button></div>
  </form>
  <?php endif; ?>
</section>
