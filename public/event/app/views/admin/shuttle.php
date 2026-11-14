<?php /** @var string $tanggal @var array $orang @var array $mobil @var array $belum @var array $pending @var array $errors */
$kapasitas = array_sum(array_map(static fn ($k) => (int) $k['kapasitas'], $mobil));
$terisi = array_filter($mobil, static fn ($k) => $k['penumpang']);
$publikN = count(array_filter($terisi, static fn ($k) => $k['publik']));
$hidden = '<input type="hidden" name="tanggal" value="' . h($tanggal) . '">';
$steps = [
    ['Kendaraan', 'Tambahkan mobil/bus: kapasitas, jam berangkat, supir & PIC Shuttle.', $mobil && $kapasitas >= count($orang), count($mobil) . ' kendaraan, ' . $kapasitas . ' kursi untuk ' . count($orang) . ' orang'],
    ['Isi penumpang', 'Pilih kendaraan, centang penumpangnya (atau "Bagi otomatis"). Satu orang hanya di satu kendaraan.', $orang && !$belum, $belum ? count($belum) . ' orang belum dapat kendaraan' : (count($orang) ? 'semua dapat kendaraan' : 'belum ada yang menginap')],
    ['Publikasikan', 'Peserta melihat kendaraan, jam, titik jemput, dan kontak supir & PIC Shuttle.', $terisi && $publikN === count($terisi), $publikN . ' dari ' . count($terisi) . ' kendaraan'],
];
$opsi = static function (?int $sel) use ($mobil): string {
    $h = '<option value="">Belum dapat kendaraan</option>';
    foreach ($mobil as $id => $k) {
        $h .= '<option value="' . (int) $id . '"' . ($sel === (int) $id ? ' selected' : '') . '>' . h(kendaraan_label($k) . ' (' . count($k['penumpang']) . '/' . $k['kapasitas'] . ')') . '</option>';
    }
    return $h;
};
$kolom = ['nama' => ['Nama kendaraan', 'mis. Bus 1', 'text'], 'plat' => ['Plat', 'B 1234 XY', 'text'], 'kapasitas' => ['Kapasitas', '', 'number'], 'jam' => ['Jam berangkat', '', 'time'],
    'titik_jemput' => ['Titik jemput', 'Lobi hotel', 'text'], 'supir_nama' => ['Supir', 'Nama', 'text'], 'supir_hp' => ['HP supir', '08…', 'tel'], 'pic_nama' => ['PIC Shuttle', 'Nama', 'text'], 'pic_hp' => ['HP PIC Shuttle', '08…', 'tel']];
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
  <form method="post" action="<?= h(url('/admin/shuttle/kendaraan')) ?>" autocomplete="off"><?= csrf_field() . $hidden ?>
    <div class="mobil-cards">
      <?php foreach ($mobil + ['baru' => null] as $id => $k): $isBaru = $id === 'baru'; ?>
        <div class="mobil-card<?= $isBaru ? ' baru' : '' ?>">
          <div class="mobil-card-head"><b><?= $isBaru ? '+ Kendaraan baru' : h($k['nama']) ?></b>
            <?php if (!$isBaru): ?><span class="small muted"><?= count($k['penumpang']) ?>/<?= (int) $k['kapasitas'] ?> penumpang</span>
              <button class="linkbtn danger-link small" formaction="<?= h(url('/admin/shuttle/kendaraan/' . $id . '/hapus')) ?>" formnovalidate onclick="return confirm('Hapus kendaraan ini? Penumpangnya kembali ke belum dapat kendaraan.')">Hapus</button><?php endif; ?></div>
          <div class="mobil-grid">
            <?php foreach ($kolom as $c => [$label]): ?>
              <label><span><?= h($label) ?></span><?= $input($c, $id, $isBaru ? ($c === 'kapasitas' ? 10 : ($c === 'titik_jemput' ? 'Lobi hotel' : '')) : $k[$c]) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="small muted">Isi kartu "+ Kendaraan baru" untuk menambah kendaraan. Kontak cukup salah satu (supir atau PIC Shuttle); yang diisi tampil ke peserta beserta tombol WhatsApp setelah dipublikasikan.</p>
    <div class="actions"><button class="btn deep">Simpan kendaraan</button></div>
  </form>
</section>

<section class="panel" id="langkah-2">
  <div class="panel-head"><h2>2. Isi penumpang</h2>
    <?php if ($belum && $mobil): ?>
      <form method="post" action="<?= h(url('/admin/shuttle/bagi')) ?>"><?= csrf_field() . $hidden ?><button class="btn secondary">Bagi otomatis (<?= count($belum) ?> orang)</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$orang): ?><p class="muted" style="margin:0">Belum ada yang menginap malam ini.</p>
  <?php elseif (!$mobil): ?><p class="note warn" style="margin:0">Tambahkan kendaraan di langkah 1 terlebih dahulu.</p>
  <?php else:
    $pilihId = (int) ($_GET['k'] ?? 0);
    if (!isset($mobil[$pilihId])) { $pilihId = (int) array_key_first($mobil); }
    $km = $mobil[$pilihId];
    $grup = [];
    foreach ($orang as $key => $o) { $grup[$o['instansi']][$key] = $o; }
    $rooms = kamar_list($tanggal, $orang);
  ?>
    <p class="small muted">Pilih kendaraan, centang penumpangnya, lalu simpan. Yang sudah di kendaraan lain tidak bisa dicentang (keluarkan dulu dari kendaraan tersebut). <?= $belum ? '<b>' . count($belum) . ' orang belum dapat kendaraan.</b>' : 'Semua sudah dapat kendaraan.' ?></p>
    <div class="mobil-pills" role="tablist" aria-label="Pilih kendaraan">
      <?php foreach ($mobil as $id => $k): $penuh = count($k['penumpang']) >= $k['kapasitas']; ?>
        <a role="tab" aria-selected="<?= $id === $pilihId ? 'true' : 'false' ?>" class="<?= $id === $pilihId ? 'on' : '' ?>" href="<?= h(url('/admin/shuttle?tanggal=' . $tanggal . '&k=' . $id . '#langkah-2')) ?>"><b><?= h(kendaraan_label($k)) ?></b> <span class="<?= $penuh ? 'full' : '' ?>"><?= count($k['penumpang']) ?>/<?= (int) $k['kapasitas'] ?></span></a>
      <?php endforeach; ?>
    </div>
    <form method="post" action="<?= h(url('/admin/shuttle/isi')) ?>" autocomplete="off" class="isi-form" data-kapasitas="<?= (int) $km['kapasitas'] ?>"><?= csrf_field() . $hidden ?>
      <input type="hidden" name="kendaraan_id" value="<?= (int) $pilihId ?>">
      <div class="isi-head"><b>Penumpang <?= h($km['nama']) ?></b> <span class="kursi" aria-live="polite"><span data-count><?= count($km['penumpang']) ?></span>/<?= (int) $km['kapasitas'] ?> kursi</span></div>
      <div class="isi-grup">
        <?php foreach ($grup as $nama => $list): ?>
          <fieldset class="grup">
            <legend><label class="check small"><input type="checkbox" data-group-toggle> <span><b><?= h($nama) ?></b> (<?= count($list) ?>)</span></label></legend>
            <?php foreach ($list as $key => $o):
              $di = isset($mobil[$o['shuttle_id']]) ? $mobil[$o['shuttle_id']] : null;
              $lain = $di && (int) $di['id'] !== $pilihId; ?>
              <label class="check small orang<?= $lain ? ' lain' : '' ?>">
                <input type="checkbox" name="orang[]" value="<?= h($key) ?>" <?= $di && !$lain ? 'checked' : '' ?> <?= $lain ? 'disabled' : '' ?>>
                <span><?= h($o['nama']) ?><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?>
                  <span class="muted"><?= isset($rooms[$o['kamar_id']]) ? 'kamar ' . h(kamar_label($rooms[$o['kamar_id']])) : '' ?><?= $lain ? ', di ' . h($di['nama']) : '' ?></span></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
      </div>
      <div class="actions"><button class="btn deep">Simpan penumpang <?= h($km['nama']) ?></button></div>
    </form>
  <?php endif; ?>
</section>

<section class="panel" id="langkah-3">
  <h2>3. Publikasikan ke peserta</h2>
  <p class="small muted">Centang kendaraan yang sudah final. Penumpangnya melihat nama kendaraan, plat, jam berangkat, titik jemput, serta WhatsApp supir & PIC Shuttle di halaman instansi.</p>
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
