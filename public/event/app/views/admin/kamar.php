<?php /** @var string $tanggal @var array $orang @var array $rooms @var array $belum @var array $pending @var array $errors */
$publik = setting('kamar_dipublikasikan') === '1';
$l = count(array_filter($orang, static fn ($o) => $o['jk'] === 'L'));
$tamu = array_filter($orang, static fn ($o) => $o['tipe'] === 'tamu');
$bernomor = count(array_filter($rooms, static fn ($k) => $k['nomor']));
$tanpaNomor = count(array_filter($rooms, static fn ($k) => !$k['nomor'] && $k['penghuni']));
$jkLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan', 'campur' => 'Campur'];
$hidden = '<input type="hidden" name="tanggal" value="' . h($tanggal) . '">';
$steps = [
    ['Tamu menginap', 'Peserta yang memilih menginap otomatis masuk. Tambahkan panitia/pejabat yang tidak registrasi.', true, count($orang) . ' orang'],
    ['Nomor kamar dari hotel', 'Masukkan nomor kamar yang disediakan hotel, bisa sekaligus (mis. 301-310).', $bernomor > 0, $bernomor . ' kamar bernomor'],
    ['Bagi otomatis & periksa', 'Klik "Bagi kamar otomatis", lalu periksa. Ubah dengan mengetik nomor kamar di tabel.', $orang && !$belum && !$tanpaNomor, $belum ? count($belum) . ' belum dapat kamar' : ($tanpaNomor ? $tanpaNomor . ' kamar belum bernomor' : 'semua dapat kamar')],
    ['Publikasikan', 'Peserta melihat nomor kamar & teman sekamar di halaman instansinya.', $publik, $publik ? 'sudah' : 'belum'],
];
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
  <h2>1. Tamu menginap <span class="muted small">(panitia / pejabat)</span></h2>
  <p class="small muted"><?= count($orang) - count($tamu) ?> peserta menginap dari registrasi (<?= $l ?> laki-laki, <?= count($orang) - $l ?> perempuan termasuk tamu). Tambahkan di sini yang menginap tetapi tidak registrasi.</p>
  <form method="post" action="<?= h(url('/admin/tamu')) ?>" class="tamu-form"><?= csrf_field() . $hidden ?>
    <input type="text" name="nama" required maxlength="150" placeholder="Nama tamu" aria-label="Nama tamu">
    <select name="jk" required aria-label="Jenis kelamin"><option value="">Jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
    <select name="keterangan" required aria-label="Keterangan"><option value="">Keterangan</option><?php foreach (KETERANGAN_TAMU as $k): ?><option><?= h($k) ?></option><?php endforeach; ?></select>
    <input type="tel" name="hp" inputmode="numeric" placeholder="No. HP (opsional)" aria-label="No. HP" data-validate="hp">
    <button class="btn small deep">Tambah tamu</button>
  </form>
  <?php if ($tamu): ?>
    <div class="table-wrap" style="margin-top:14px"><table>
      <thead><tr><th>Nama</th><th>Jenis kelamin</th><th>Keterangan</th><th>No. HP</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tamu as $o): ?>
        <tr><td><b><?= h($o['nama']) ?></b></td><td><?= h(jk_label($o['jk'])) ?></td><td><?= h($o['instansi']) ?></td><td><?= h($o['hp']) ?></td>
          <td><form method="post" action="<?= h(url('/admin/tamu/' . $o['id'] . '/hapus')) ?>" data-confirm="Hapus <?= h($o['nama']) ?> dari daftar menginap?"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="langkah-2">
  <h2>2. Nomor kamar dari hotel</h2>
  <form method="post" action="<?= h(url('/admin/kamar/daftar')) ?>"><?= csrf_field() . $hidden ?>
    <div class="fields two">
      <label class="field"><span>Tambah beberapa kamar sekaligus</span><input type="text" name="nomor_massal" placeholder="mis. 301-310, 401, 402" autocomplete="off">
        <small>Pisahkan dengan koma; gunakan tanda hubung untuk rentang. Nomor yang sudah ada dilewati.</small></label>
      <label class="field"><span>Kapasitas tiap kamar</span><input type="number" name="massal_kapasitas" min="1" max="10" value="<?= (int) setting('kapasitas_kamar') ?>"></label>
    </div>
    <?php if ($rooms): ?>
      <div class="table-wrap" style="margin-top:16px"><table class="kamar-table">
        <thead><tr><th>Nomor kamar</th><th>Kapasitas</th><th>Catatan</th><th>Isi</th><th>Penghuni</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $id => $k): ?>
          <tr class="<?= $k['jk'] === 'campur' ? 'row-err' : (!$k['nomor'] && $k['penghuni'] ? 'row-warn' : '') ?>">
            <td><input class="kamar-input" type="text" name="nomor[<?= (int) $id ?>]" value="<?= h($k['nomor']) ?>" placeholder="Isi nomor" aria-label="Nomor kamar #<?= (int) $id ?>"></td>
            <td><input class="num-input" type="number" name="kapasitas[<?= (int) $id ?>]" min="1" max="10" value="<?= (int) $k['kapasitas'] ?>" aria-label="Kapasitas"></td>
            <td><input type="text" name="catatan[<?= (int) $id ?>]" value="<?= h($k['catatan']) ?>" placeholder="mis. lantai 3" aria-label="Catatan"></td>
            <td class="small"><?= count($k['penghuni']) ?>/<?= (int) $k['kapasitas'] ?><?= $k['jk'] ? '<br>' . h($jkLabel[$k['jk']]) : '' ?></td>
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

<section class="panel" id="langkah-3">
  <div class="panel-head"><h2>3. Bagi kamar & periksa</h2>
    <?php if ($belum): ?>
      <form method="post" action="<?= h(url('/admin/kamar/rekomendasi')) ?>"><?= csrf_field() . $hidden ?><button class="btn deep">Bagi kamar otomatis (<?= count($belum) ?> orang)</button></form>
    <?php endif; ?>
  </div>
  <p class="small muted">Pembagian otomatis: rekan satu instansi dengan jenis kelamin sama sekamar; sisanya digabung lintas instansi dengan jenis kelamin sama. Kamar bernomor yang kosong diisi lebih dulu. Untuk mengubah, ketik nomor kamar di kolom <b>Kamar</b> (nomor baru otomatis jadi kamar baru; kosongkan untuk mengeluarkan), lalu <i>Simpan penempatan</i>.</p>
  <?php if ($errors): ?><div class="errors" role="alert"><b>Belum disimpan:</b><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$orang): ?><p class="muted" style="margin:0">Belum ada yang menginap malam ini.</p><?php else: ?>
  <form method="post" action="<?= h(url('/admin/kamar/penempatan')) ?>"><?= csrf_field() . $hidden ?>
    <datalist id="daftar-kamar"><?php foreach ($rooms as $k): ?><option value="<?= h(kamar_label($k)) ?>"><?= h(count($k['penghuni']) . '/' . $k['kapasitas'] . ($k['jk'] ? ', ' . $jkLabel[$k['jk']] : '')) ?></option><?php endforeach; ?></datalist>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th>#</th><th>Nama</th><th>Jenis kelamin</th><th>Instansi / keterangan</th><th>Kamar</th><th>Sekamar dengan</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach ($orang as $key => $o):
        $k = $rooms[$o['kamar_id']] ?? null;
        $val = array_key_exists($key, $pending) ? $pending[$key] : ($k ? kamar_label($k) : '');
        $teman = $k ? array_filter($k['penghuni'], static fn ($p) => $p['key'] !== $key) : [];
      ?>
        <tr class="<?= $k ? '' : 'row-warn' ?>">
          <td class="num"><?= ++$n ?></td>
          <td><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td>
          <td><?= h(jk_label($o['jk'])) ?></td>
          <td class="small"><?= h($o['instansi']) ?></td>
          <td><input class="kamar-input" type="text" name="kamar[<?= h($key) ?>]" value="<?= h($val) ?>" list="daftar-kamar" placeholder="Belum" aria-label="Kamar untuk <?= h($o['nama']) ?>" autocomplete="off"></td>
          <td class="small"><?= $teman ? h(implode(', ', array_map(static fn ($p) => $p['nama'], $teman))) : ($k ? '<span class="muted">Sendiri</span>' : '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="actions"><button class="btn deep">Simpan penempatan</button></div>
  </form>
  <?php endif; ?>
</section>

<section class="panel" id="langkah-4">
  <h2>4. Publikasikan ke peserta</h2>
  <div style="display:flex;flex-wrap:wrap;gap:10px 20px;align-items:center;justify-content:space-between">
    <p style="margin:0"><?= $publik ? '<span class="badge ok">Dipublikasikan</span> Peserta melihat nomor kamar dan teman sekamar di halaman instansi (hanya kamar bernomor). Nomor WhatsApp teman sekamar hanya tampil bila pemiliknya mengizinkan.' : '<span class="badge warn">Belum dipublikasikan</span> Peserta melihat "menunggu pembagian panitia".' ?></p>
    <form method="post" action="<?= h(url('/admin/kamar/publikasi')) ?>"><?= csrf_field() . $hidden ?>
      <input type="hidden" name="publikasi" value="<?= $publik ? '0' : '1' ?>"><button class="btn small <?= $publik ? 'secondary' : 'deep' ?>"><?= $publik ? 'Sembunyikan dari peserta' : 'Publikasikan ke peserta' ?></button></form>
  </div>
</section>
