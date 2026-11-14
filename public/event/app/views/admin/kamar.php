<?php /** @var string $tanggal @var array $orang @var array $rooms @var array $belum @var array $pending @var array $errors */
$publik = setting('kamar_dipublikasikan') === '1';
$l = count(array_filter($orang, static fn ($o) => $o['jk'] === 'L'));
$tamu = array_filter($orang, static fn ($o) => $o['tipe'] === 'tamu');
$tanpaNomor = count(array_filter($rooms, static fn ($k) => !$k['nomor'] && $k['penghuni']));
$jkLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan', 'campur' => 'Campur'];
$hidden = '<input type="hidden" name="tanggal" value="' . h($tanggal) . '">';
?>
<h1>Pembagian kamar</h1>
<nav class="tabs" aria-label="Malam" style="margin-bottom:18px">
  <?php foreach (event_dates() as $d): ?>
    <a href="<?= h(url('/admin/kamar?tanggal=' . $d)) ?>" <?= $tanggal === $d ? 'aria-current="page"' : '' ?>>Malam <?= h(malam_menginap($d)) ?></a>
  <?php endforeach; ?>
</nav>

<div class="note <?= $publik ? '' : 'warn' ?>" style="display:flex;flex-wrap:wrap;gap:10px 20px;align-items:center;justify-content:space-between">
  <span><?= $publik ? '<b>Dipublikasikan.</b> Peserta melihat nomor kamar dan teman sekamarnya di halaman instansi (hanya kamar yang sudah bernomor).' : '<b>Belum dipublikasikan.</b> Peserta belum melihat pembagian kamar. Publikasikan setelah nomor kamar lengkap.' ?></span>
  <form method="post" action="<?= h(url('/admin/kamar/publikasi')) ?>"><?= csrf_field() . $hidden ?>
    <input type="hidden" name="publikasi" value="<?= $publik ? '0' : '1' ?>"><button class="btn small <?= $publik ? 'secondary' : 'deep' ?>"><?= $publik ? 'Sembunyikan dari peserta' : 'Publikasikan ke peserta' ?></button></form>
</div>

<div class="stats">
  <div class="stat"><b><?= count($orang) ?></b><span>menginap (<?= count($tamu) ?> tamu panitia)</span></div>
  <div class="stat"><b><?= $l ?> / <?= count($orang) - $l ?></b><span>laki-laki / perempuan</span></div>
  <div class="stat"><b><?= count($rooms) ?></b><span>kamar<?= $tanpaNomor ? ', ' . $tanpaNomor . ' belum bernomor' : '' ?></span></div>
  <div class="stat"><b><?= count($belum) ?></b><span>belum dapat kamar</span></div>
</div>

<section class="panel">
  <div class="panel-head"><h2>Penempatan</h2>
    <div class="actions" style="margin:0">
      <?php if ($belum): ?>
        <form method="post" action="<?= h(url('/admin/kamar/rekomendasi')) ?>"><?= csrf_field() . $hidden ?><button class="btn small secondary">Rekomendasikan otomatis</button></form>
      <?php endif; ?>
      <?php if ($rooms): ?>
        <form method="post" action="<?= h(url('/admin/kamar/reset')) ?>" data-confirm="Kosongkan seluruh pembagian kamar malam ini? Semua kamar dihapus."><?= csrf_field() . $hidden ?><button class="linkbtn danger-link small">Kosongkan semua</button></form>
      <?php endif; ?>
    </div>
  </div>
  <p class="small muted">Ketik <b>nomor kamar</b> di kolom Kamar lalu klik <i>Simpan penempatan</i>. Nomor yang belum ada otomatis menjadi kamar baru (kapasitas <?= (int) setting('kapasitas_kamar') ?>). Kosongkan untuk mengeluarkan dari kamar.
    <i>Rekomendasikan otomatis</i> mengisi yang belum dapat kamar: satu instansi & jenis kelamin sama sekamar, sisanya digabung lintas instansi dengan jenis kelamin sama; kamarnya muncul sebagai "#nomor-urut" sampai diberi nomor di tabel Daftar kamar.</p>
  <?php if ($errors): ?><div class="errors" role="alert"><b>Belum disimpan:</b><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$orang): ?><p class="muted" style="margin:0">Belum ada yang menginap malam ini.</p><?php else: ?>
  <form method="post" action="<?= h(url('/admin/kamar/penempatan')) ?>"><?= csrf_field() . $hidden ?>
    <datalist id="daftar-kamar"><?php foreach ($rooms as $k): ?><option value="<?= h(kamar_label($k)) ?>"><?= h(count($k['penghuni']) . '/' . $k['kapasitas'] . ($k['jk'] ? ', ' . $jkLabel[$k['jk']] : '')) ?></option><?php endforeach; ?></datalist>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th>#</th><th>Nama</th><th>JK</th><th>Instansi / keterangan</th><th>Kamar</th><th>Sekamar dengan</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach ($orang as $key => $o):
        $k = $rooms[$o['kamar_id']] ?? null;
        $val = array_key_exists($key, $pending) ? $pending[$key] : ($k ? kamar_label($k) : '');
        $teman = $k ? array_filter($k['penghuni'], static fn ($p) => $p['key'] !== $key) : [];
      ?>
        <tr class="<?= $k ? '' : 'row-warn' ?>">
          <td class="num"><?= ++$n ?></td>
          <td><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td>
          <td><?= h($o['jk']) ?></td>
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

<section class="panel">
  <h2>Daftar kamar malam <?= h(malam_menginap($tanggal)) ?></h2>
  <form method="post" action="<?= h(url('/admin/kamar/daftar')) ?>"><?= csrf_field() . $hidden ?>
    <div class="table-wrap"><table class="kamar-table">
      <thead><tr><th>Nomor kamar</th><th>Kapasitas</th><th>Catatan</th><th>Isi</th><th>Penghuni</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rooms as $id => $k): ?>
        <tr class="<?= $k['jk'] === 'campur' ? 'row-err' : (!$k['nomor'] && $k['penghuni'] ? 'row-warn' : '') ?>">
          <td><input class="kamar-input" type="text" name="nomor[<?= (int) $id ?>]" value="<?= h($k['nomor']) ?>" placeholder="#<?= (int) $id ?>" aria-label="Nomor kamar #<?= (int) $id ?>"></td>
          <td><input class="num-input" type="number" name="kapasitas[<?= (int) $id ?>]" min="1" max="10" value="<?= (int) $k['kapasitas'] ?>" aria-label="Kapasitas"></td>
          <td><input type="text" name="catatan[<?= (int) $id ?>]" value="<?= h($k['catatan']) ?>" placeholder="mis. lantai 3" aria-label="Catatan"></td>
          <td class="small"><?= count($k['penghuni']) ?>/<?= (int) $k['kapasitas'] ?><?= $k['jk'] ? '<br>' . h($jkLabel[$k['jk']]) : '' ?></td>
          <td class="small"><?= $k['penghuni'] ? h(implode(', ', array_column($k['penghuni'], 'nama'))) : '<span class="muted">Kosong</span>' ?></td>
          <td><button class="linkbtn danger-link small" formaction="<?= h(url('/admin/kamar/' . $id . '/hapus')) ?>" formnovalidate onclick="return confirm('Hapus kamar ini? Penghuninya kembali ke belum dapat kamar.')">Hapus</button></td>
        </tr>
      <?php endforeach; ?>
        <tr class="row-new">
          <td><input class="kamar-input" type="text" name="baru_nomor" placeholder="Kamar baru" aria-label="Nomor kamar baru"></td>
          <td><input class="num-input" type="number" name="baru_kapasitas" min="1" max="10" value="<?= (int) setting('kapasitas_kamar') ?>" aria-label="Kapasitas kamar baru"></td>
          <td colspan="4" class="small muted">Isi nomor untuk menambah kamar kosong.</td>
        </tr>
      </tbody>
    </table></div>
    <div class="actions"><button class="btn deep">Simpan daftar kamar</button></div>
  </form>
</section>

<section class="panel" id="tamu">
  <h2>Tamu menginap (panitia / pejabat)</h2>
  <p class="small muted">Untuk yang menginap tetapi tidak registrasi sebagai peserta. Tamu muncul di tabel Penempatan dan ikut dibagi kamar.</p>
  <form method="post" action="<?= h(url('/admin/tamu')) ?>" class="tamu-form"><?= csrf_field() . $hidden ?>
    <input type="text" name="nama" required maxlength="150" placeholder="Nama" aria-label="Nama tamu">
    <select name="jk" required aria-label="Jenis kelamin"><option value="">JK…</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
    <input type="text" name="keterangan" maxlength="150" placeholder="Keterangan (Panitia / Pejabat)" aria-label="Keterangan">
    <input type="tel" name="hp" maxlength="30" placeholder="No. HP (opsional)" aria-label="No. HP">
    <button class="btn small deep">Tambah tamu</button>
  </form>
  <?php if ($tamu): ?>
    <div class="table-wrap" style="margin-top:14px"><table>
      <thead><tr><th>Nama</th><th>JK</th><th>Keterangan</th><th>HP</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tamu as $o): ?>
        <tr><td><b><?= h($o['nama']) ?></b></td><td><?= h($o['jk']) ?></td><td><?= h($o['instansi']) ?></td><td><?= h($o['hp']) ?></td>
          <td><form method="post" action="<?= h(url('/admin/tamu/' . $o['id'] . '/hapus')) ?>" data-confirm="Hapus <?= h($o['nama']) ?> dari daftar menginap?"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
