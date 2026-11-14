<?php /** @var string $tanggal @var array $orang @var array $rooms @var array $belum */
$publik = setting('kamar_dipublikasikan') === '1';
$l = count(array_filter($orang, static fn ($o) => $o['jk'] === 'L'));
$tamu = array_filter($orang, static fn ($o) => $o['tipe'] === 'tamu');
$tanpaNomor = count(array_filter($rooms, static fn ($k) => !$k['nomor'] && $k['penghuni']));
$jkLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan', 'campur' => 'Campur'];
$opsiKamar = static function (array $rooms, ?int $current) use ($jkLabel): string {
    $h = '<option value="">Pilih kamar…</option>';
    foreach ($rooms as $k) {
        $label = ($k['nomor'] ? 'Kamar ' . $k['nomor'] : 'Kamar tanpa nomor #' . $k['id']) . ' (' . count($k['penghuni']) . '/' . $k['kapasitas'] . ($k['jk'] ? ', ' . $jkLabel[$k['jk']] : '') . ')';
        $h .= '<option value="' . (int) $k['id'] . '"' . ($current === (int) $k['id'] ? ' selected' : '') . '>' . h($label) . '</option>';
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

<div class="note <?= $publik ? '' : 'warn' ?>" style="display:flex;flex-wrap:wrap;gap:10px 20px;align-items:center;justify-content:space-between">
  <span><?= $publik ? '<b>Dipublikasikan.</b> Peserta melihat nomor kamar dan teman sekamarnya di halaman instansi (hanya kamar yang sudah bernomor).' : '<b>Belum dipublikasikan.</b> Peserta belum melihat pembagian kamar. Publikasikan setelah nomor kamar lengkap.' ?></span>
  <form method="post" action="<?= h(url('/admin/kamar/publikasi')) ?>"><?= csrf_field() ?><input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
    <input type="hidden" name="publikasi" value="<?= $publik ? '0' : '1' ?>"><button class="btn small <?= $publik ? 'secondary' : 'deep' ?>"><?= $publik ? 'Sembunyikan dari peserta' : 'Publikasikan ke peserta' ?></button></form>
</div>

<div class="stats">
  <div class="stat"><b><?= count($orang) ?></b><span>menginap (<?= count($tamu) ?> tamu panitia)</span></div>
  <div class="stat"><b><?= $l ?> / <?= count($orang) - $l ?></b><span>laki-laki / perempuan</span></div>
  <div class="stat"><b><?= count($rooms) ?></b><span>kamar<?= $tanpaNomor ? ', ' . $tanpaNomor . ' belum bernomor' : '' ?></span></div>
  <div class="stat"><b><?= count($belum) ?></b><span>belum dapat kamar</span></div>
</div>

<section class="panel">
  <div class="panel-head"><h2>Belum dapat kamar</h2>
    <?php if ($belum): ?>
      <form method="post" action="<?= h(url('/admin/kamar/rekomendasi')) ?>"><?= csrf_field() ?><input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
        <button class="btn small deep">Rekomendasikan pembagian otomatis</button></form>
    <?php endif; ?>
  </div>
  <p class="small muted">Rekomendasi: rekan satu instansi dengan jenis kelamin sama ditempatkan sekamar; sisanya digabung dengan instansi lain yang jenis kelaminnya sama (kapasitas <?= (int) setting('kapasitas_kamar') ?> orang per kamar, ubah di Pengaturan). Kamar yang sudah diatur tidak diubah.</p>
  <?php if (!$belum): ?><p class="muted" style="margin:0">Semua yang menginap sudah mendapat kamar.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Nama</th><th>JK</th><th>Instansi / keterangan</th><th>Tempatkan</th></tr></thead>
    <tbody>
    <?php foreach ($belum as $o): ?>
      <tr><td><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?></td><td><?= h($o['jk']) ?></td><td><?= h($o['instansi']) ?></td>
        <td><?php if ($rooms): ?><form method="post" action="<?= h(url('/admin/kamar/tempatkan')) ?>" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>"><input type="hidden" name="orang" value="<?= h($o['key']) ?>">
          <select name="kamar_id" aria-label="Kamar untuk <?= h($o['nama']) ?>" required><?= $opsiKamar($rooms, null) ?></select><button class="btn small secondary">Tempatkan</button></form>
        <?php else: ?><span class="muted small">Buat kamar dulu</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head"><h2>Kamar malam <?= h(malam_menginap($tanggal)) ?></h2>
    <?php if ($rooms): ?><form method="post" action="<?= h(url('/admin/kamar/reset')) ?>" data-confirm="Kosongkan seluruh pembagian kamar malam ini? Semua kamar dihapus."><?= csrf_field() ?><input type="hidden" name="tanggal" value="<?= h($tanggal) ?>"><button class="linkbtn danger-link">Kosongkan semua</button></form><?php endif; ?>
  </div>
  <form method="post" action="<?= h(url('/admin/kamar/tambah')) ?>" class="inline-form" style="margin-bottom:16px"><?= csrf_field() ?><input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
    <input type="text" name="nomor" placeholder="Nomor kamar (mis. 305)" aria-label="Nomor kamar baru" style="max-width:220px">
    <input type="number" name="kapasitas" min="1" max="10" value="<?= (int) setting('kapasitas_kamar') ?>" aria-label="Kapasitas" style="max-width:90px">
    <button class="btn small secondary">Tambah kamar</button></form>
  <?php if (!$rooms): ?><p class="muted">Belum ada kamar. Gunakan rekomendasi otomatis atau tambah kamar manual.</p><?php endif; ?>
  <div class="rooms">
    <?php foreach ($rooms as $k): $full = count($k['penghuni']) >= $k['kapasitas']; ?>
      <div class="room<?= $k['jk'] === 'campur' ? ' warn' : '' ?>">
        <form method="post" action="<?= h(url('/admin/kamar/' . $k['id'])) ?>" class="room-head"><?= csrf_field() ?>
          <label class="sr-only" for="nomor-<?= $k['id'] ?>">Nomor kamar</label>
          <input id="nomor-<?= $k['id'] ?>" type="text" name="nomor" value="<?= h($k['nomor']) ?>" placeholder="Nomor kamar">
          <label class="sr-only" for="kap-<?= $k['id'] ?>">Kapasitas</label>
          <input id="kap-<?= $k['id'] ?>" type="number" name="kapasitas" min="1" max="10" value="<?= (int) $k['kapasitas'] ?>" title="Kapasitas">
          <input type="text" name="catatan" value="<?= h($k['catatan']) ?>" placeholder="Catatan (mis. lantai 3)" aria-label="Catatan kamar">
          <button class="btn small secondary">Simpan</button>
        </form>
        <p class="room-meta">
          <?= $k['jk'] ? '<span class="badge ' . ($k['jk'] === 'campur' ? 'err' : 'inap') . '">' . h($jkLabel[$k['jk']]) . '</span>' : '<span class="badge draft">Kosong</span>' ?>
          <span class="small muted"><?= count($k['penghuni']) ?>/<?= (int) $k['kapasitas'] ?> orang<?= $full ? ', penuh' : '' ?></span>
          <?php if (!$k['nomor'] && $k['penghuni']): ?><span class="badge warn">Belum bernomor</span><?php endif; ?>
        </p>
        <ul class="people small">
          <?php foreach ($k['penghuni'] as $o): ?>
            <li><b><?= h($o['nama']) ?></b><?= $o['tipe'] === 'tamu' ? ' <span class="chip">Tamu</span>' : '' ?><div class="meta"><?= h($o['instansi']) ?></div>
              <form method="post" action="<?= h(url('/admin/kamar/tempatkan')) ?>" class="inline-form"><?= csrf_field() ?>
                <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>"><input type="hidden" name="orang" value="<?= h($o['key']) ?>">
                <select name="kamar_id" aria-label="Pindahkan <?= h($o['nama']) ?>"><option value="0">Keluarkan dari kamar</option><?= $opsiKamar(array_filter($rooms, static fn ($r) => (int) $r['id'] !== (int) $k['id']), null) ?></select>
                <button class="btn small secondary">Pindah</button></form></li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= h(url('/admin/kamar/' . $k['id'] . '/hapus')) ?>" data-confirm="Hapus kamar ini? Penghuninya kembali ke daftar belum dapat kamar."><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus kamar</button></form>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel" id="tamu">
  <h2>Tamu menginap (panitia / pejabat)</h2>
  <p class="small muted">Untuk yang menginap tetapi tidak registrasi sebagai peserta. Tamu ikut dibagi kamar, tetapi tidak punya halaman instansi.</p>
  <form method="post" action="<?= h(url('/admin/tamu')) ?>"><?= csrf_field() ?><input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
    <div class="fields two">
      <label class="field"><span>Nama</span><input type="text" name="nama" required maxlength="150"></label>
      <label class="field"><span>Keterangan</span><input type="text" name="keterangan" maxlength="150" placeholder="Panitia / Kepala BRMP Agroklimat"></label>
      <div class="field"><span class="field-label" id="tamu-jk">Jenis kelamin</span><div class="choices" role="radiogroup" aria-labelledby="tamu-jk">
        <label class="choice"><input type="radio" name="jk" value="L" required><span>Laki-laki</span></label>
        <label class="choice"><input type="radio" name="jk" value="P"><span>Perempuan</span></label></div></div>
      <label class="field"><span>No. HP <small>(opsional)</small></span><input type="tel" name="hp" maxlength="30"></label>
    </div>
    <div class="actions"><button class="btn small deep">Tambah tamu malam <?= h(malam_menginap($tanggal)) ?></button></div>
  </form>
  <?php if ($tamu): ?>
    <ul class="people" style="margin-top:14px">
      <?php foreach ($tamu as $o): ?>
        <li><div class="who"><b><?= h($o['nama']) ?></b><span class="chip"><?= h($o['jk']) ?></span><span class="muted small"><?= h($o['instansi']) ?></span></div>
          <form method="post" action="<?= h(url('/admin/tamu/' . $o['id'] . '/hapus')) ?>" data-confirm="Hapus <?= h($o['nama']) ?> dari daftar menginap?"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
