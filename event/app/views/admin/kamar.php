<?php /** @var string $tanggal @var array $orang @var array $rooms @var array $belum @var array $pending @var array $errors */
$tamu = array_filter($orang, static fn ($o) => $o['tipe'] === 'tamu');
$nPeserta = count($orang) - count($tamu);
$bernomor = count(array_filter($rooms, static fn ($k) => $k['nomor']));
$tanpaNomor = count(array_filter($rooms, static fn ($k) => !$k['nomor'] && $k['penghuni']));
$terisi = array_filter($rooms, static fn ($k) => $k['penghuni']);
$publikN = count(array_filter($terisi, static fn ($k) => $k['publik']));
$jkLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan', 'campur' => 'Campur'];
$hidden = '<input type="hidden" name="tanggal" value="' . h($tanggal) . '">';
$steps = [
    ['Nomor kamar dari hotel', 'Masukkan nomor kamar yang disediakan hotel, bisa sekaligus (mis. 301-310).', $bernomor > 0, $bernomor . ' kamar'],
    ['Bagi kamar & periksa', 'Klik "Bagi kamar otomatis", lalu periksa. Ganti kamar seseorang lewat dropdown.', $orang && !$belum && !$tanpaNomor, $belum ? count($belum) . ' orang belum dapat kamar' : (count($orang) ? 'semua dapat kamar' : 'belum ada yang menginap')],
    ['Publikasikan', 'Pilih kamar yang sudah final untuk ditampilkan ke peserta.', $terisi && $publikN === count($terisi), $publikN . ' dari ' . count($terisi) . ' kamar'],
];
$opsi = static function (?int $sel) use ($rooms, $jkLabel): string {
    $h = '<option value="">Belum dapat kamar</option>';
    foreach ($rooms as $id => $k) {
        $h .= '<option value="' . (int) $id . '"' . ($sel === (int) $id ? ' selected' : '') . '>' . h(($k['nomor'] ? 'Kamar ' . $k['nomor'] : 'Tanpa nomor #' . $id) . ' (' . count($k['penghuni']) . '/' . $k['kapasitas'] . ($k['jk'] ? ', ' . $jkLabel[$k['jk']] : '') . ')') . '</option>';
    }
    return $h;
};
?>
<h1>Pembagian kamar</h1>
<nav class="tabs" aria-label="Malam" style="margin-bottom:18px">
  <?php foreach (event_dates() as $d): ?>
    <a href="<?= h(url('/admin/kamar?tanggal=' . $d)) ?>" <?= $tanggal === $d ? 'aria-current="page"' : '' ?>>Malam <?= h(malam_menginap($d)) ?></a>
  <?php endforeach; ?>
</nav>

<ol class="steps-flow" aria-label="Alur pembagian kamar">
  <?php foreach ($steps as $i => [$judul, $ket, $done, $status]): ?>
    <li class="<?= $done ? 'done' : '' ?>"><a href="#langkah-<?= $i + 1 ?>">
      <span class="num" aria-hidden="true"><?= $done ? '✓' : $i + 1 ?></span>
      <span><b><?= h($judul) ?></b><span class="small"><?= h($ket) ?></span><span class="status"><?= h($status) ?></span></span></a></li>
  <?php endforeach; ?>
</ol>

<section class="panel" id="langkah-1">
  <h2>1. Nomor kamar dari hotel</h2>
  <form method="post" action="<?= h(url('/admin/kamar/daftar')) ?>"><?= csrf_field() . $hidden ?>
    <div class="fields two">
      <label class="field"><span>Tambah kamar <small>(pisahkan dengan koma; tanda hubung untuk rentang)</small></span><input type="text" name="nomor_massal" placeholder="mis. 301-310, 401, 402" autocomplete="off"></label>
      <label class="field"><span>Kapasitas tiap kamar <small>(orang)</small></span><input type="number" name="massal_kapasitas" min="1" max="10" value="<?= (int) setting('kapasitas_kamar') ?>"></label>
    </div>
    <?php if ($rooms): ?>
      <div class="table-wrap" style="margin-top:16px"><table class="kamar-table">
        <thead><tr><th>Nomor kamar</th><th>Kapasitas</th><th>Isi</th><th>Penghuni</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $id => $k): ?>
          <tr class="<?= $k['jk'] === 'campur' ? 'row-err' : (!$k['nomor'] && $k['penghuni'] ? 'row-warn' : '') ?>">
            <td><input class="kamar-input" type="text" name="nomor[<?= (int) $id ?>]" value="<?= h($k['nomor']) ?>" placeholder="Isi nomor" aria-label="Nomor kamar #<?= (int) $id ?>"></td>
            <td><input class="num-input" type="number" name="kapasitas[<?= (int) $id ?>]" min="1" max="10" value="<?= (int) $k['kapasitas'] ?>" aria-label="Kapasitas"></td>
            <td class="small"><?= count($k['penghuni']) ?>/<?= (int) $k['kapasitas'] ?><?= $k['jk'] ? ', ' . h($jkLabel[$k['jk']]) : '' ?></td>
            <td class="small"><?= $k['penghuni'] ? h(implode(', ', array_column($k['penghuni'], 'nama'))) : '<span class="muted">Kosong</span>' ?></td>
            <td><button class="linkbtn danger-link small" formaction="<?= h(url('/admin/kamar/' . $id . '/hapus')) ?>" formnovalidate onclick="return confirm('Hapus kamar ini? Penghuninya kembali ke belum dapat kamar.')">Hapus</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <div class="actions"><button class="btn deep">Simpan nomor kamar</button>
      <?php if ($rooms): ?><button class="linkbtn danger-link small" formaction="<?= h(url('/admin/kamar/reset')) ?>" formnovalidate onclick="return confirm('Hapus semua kamar malam ini? Semua penempatan dikosongkan.')">Hapus semua kamar</button><?php endif; ?></div>
  </form>
</section>

<section class="panel" id="langkah-2">
  <div class="panel-head"><h2>2. Bagi kamar & periksa</h2>
    <?php if ($belum && $rooms): ?>
      <form method="post" action="<?= h(url('/admin/kamar/rekomendasi')) ?>"><?= csrf_field() . $hidden ?><button class="btn deep">Bagi kamar otomatis (<?= count($belum) ?> orang)</button></form>
    <?php endif; ?>
  </div>
  <p class="small muted"><?= $nPeserta ?> peserta memilih menginap<?= $tamu ? ' dan ' . count($tamu) . ' tamu panitia/pejabat' : '' ?>. Pembagian otomatis menempatkan rekan satu instansi dengan jenis kelamin sama sekamar, sisanya digabung lintas instansi. Hasilnya bisa diubah: pilih kamar lain di kolom <b>Kamar</b>, lalu <b>Simpan penempatan</b>.</p>

  <details class="tamu-box" <?= $tamu ? '' : '' ?>>
    <summary>Ada panitia/pejabat yang ikut menginap tetapi tidak registrasi? Tambahkan di sini<?= $tamu ? ' (' . count($tamu) . ' tamu)' : '' ?></summary>
    <form method="post" action="<?= h(url('/admin/tamu')) ?>" class="tamu-form"><?= csrf_field() . $hidden ?>
      <input type="text" name="nama" required maxlength="150" placeholder="Nama" aria-label="Nama tamu">
      <select name="jk" required aria-label="Jenis kelamin"><option value="">Jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
      <select name="keterangan" required aria-label="Keterangan"><option value="">Keterangan</option><?php foreach (KETERANGAN_TAMU as $k): ?><option><?= h($k) ?></option><?php endforeach; ?></select>
      <input type="tel" name="hp" inputmode="numeric" placeholder="No. HP (opsional)" aria-label="No. HP" data-validate="hp">
      <button class="btn small deep">Tambah tamu</button>
    </form>
    <?php if ($tamu): ?>
      <ul class="people small" style="margin-top:10px">
        <?php foreach ($tamu as $o): ?>
          <li><div class="who"><b><?= h($o['nama']) ?></b><span class="muted"><?= h(jk_label($o['jk'])) ?>, <?= h($o['instansi']) ?></span>
            <form method="post" action="<?= h(url('/admin/tamu/' . $o['id'] . '/hapus')) ?>" data-confirm="Hapus <?= h($o['nama']) ?> dari daftar menginap?"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></div></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </details>

  <?php if ($errors): ?><div class="errors" role="alert"><b>Belum disimpan:</b><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$orang): ?><p class="muted" style="margin:0">Belum ada yang menginap malam ini.</p>
  <?php elseif (!$rooms): ?><p class="note warn" style="margin:0">Masukkan nomor kamar di langkah 1 terlebih dahulu.</p>
  <?php else: ?>
  <form method="post" action="<?= h(url('/admin/kamar/penempatan')) ?>" autocomplete="off"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th>#</th><th>Nama</th><th>Jenis kelamin</th><th>Instansi / keterangan</th><th>Kamar</th><th>Sekamar dengan</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach ($orang as $key => $o):
        $k = $rooms[$o['kamar_id']] ?? null;
        $sel = array_key_exists($key, $pending) ? ($pending[$key] === '' ? null : (int) $pending[$key]) : ($k ? (int) $k['id'] : null);
        $teman = $k ? array_filter($k['penghuni'], static fn ($p) => $p['key'] !== $key) : [];
      ?>
        <tr class="<?= $k ? '' : 'row-warn' ?>">
          <td class="num"><?= ++$n ?></td>
          <td><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td>
          <td><?= h(jk_label($o['jk'])) ?></td>
          <td class="small"><?= h($o['instansi']) ?></td>
          <td><select class="kamar-select" name="kamar[<?= h($key) ?>]" aria-label="Kamar untuk <?= h($o['nama']) ?>"><?= $opsi($sel) ?></select></td>
          <td class="small"><?= $teman ? h(implode(', ', array_map(static fn ($p) => $p['nama'], $teman))) : ($k ? '<span class="muted">Sendiri</span>' : '') ?></td>
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
  <p class="small muted">Centang kamar yang sudah final. Penghuni kamar yang dicentang melihat nomor kamar dan teman sekamarnya di halaman instansi; yang lain melihat "menunggu pembagian panitia". Nomor WhatsApp teman sekamar hanya tampil bila pemiliknya mengizinkan.</p>
  <?php if (!$terisi): ?><p class="muted" style="margin:0">Belum ada kamar yang berpenghuni.</p><?php else: ?>
  <form method="post" action="<?= h(url('/admin/kamar/publikasi')) ?>"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th><label class="check small"><input type="checkbox" data-check-all="publik[]" aria-label="Pilih semua kamar"> Tampilkan</label></th><th>Kamar</th><th>Penghuni</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($terisi as $id => $k): ?>
        <tr>
          <td><input type="checkbox" name="publik[]" value="<?= (int) $id ?>" <?= $k['publik'] ? 'checked' : '' ?> <?= $k['nomor'] ? '' : 'disabled' ?> aria-label="Tampilkan kamar <?= h(kamar_label($k)) ?>"></td>
          <td><b><?= h($k['nomor'] ?: 'Tanpa nomor #' . $id) ?></b></td>
          <td class="small"><?= h(implode(', ', array_column($k['penghuni'], 'nama'))) ?></td>
          <td><?= !$k['nomor'] ? '<span class="badge warn">Beri nomor dulu</span>' : ($k['publik'] ? '<span class="badge ok">Terlihat peserta</span>' : '<span class="badge draft">Belum</span>') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="actions"><button class="btn deep">Simpan publikasi</button></div>
  </form>
  <?php endif; ?>
</section>
