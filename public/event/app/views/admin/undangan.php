<?php /** @var array $perTanggal @var array $lepas */
$total = array_sum(array_map('count', $perTanggal));
$sisa = $_SESSION['undangan_sisa'] ?? null;
unset($_SESSION['undangan_sisa']);
$tglDefault = $sisa['tanggal'] ?? event_dates()[0];
$pilihTanggal = static function (string $name, string $pilih, string $idp): string {
    $o = '';
    foreach (event_dates() as $d) {
        $o .= '<label class="choice"><input type="radio" name="' . $name . '" value="' . h($d) . '" id="' . $idp . '-' . h($d) . '"' . ($d === $pilih ? ' checked' : '') . ' required><span>' . h(tgl($d, true)) . '</span></label>';
    }
    return '<div class="choices">' . $o . '</div>';
};
?>
<h1>Undangan & tanggal</h1>
<p class="lead">Tetapkan instansi mana ikut tanggal berapa, dan mana yang dari Jabodetabek (pulang-pergi, tanpa pilihan menginap). Di formulir registrasi, instansi cukup memilih nama instansinya, dan tanggalnya muncul otomatis, jadi tidak ada yang salah pilih tanggal.</p>
<?php if (!$total): ?>
  <p class="note warn">Daftar masih kosong, jadi instansi <b>memilih tanggal sendiri</b> di formulir registrasi. Begitu daftar ini diisi, formulir otomatis berubah menjadi pilihan nama instansi, dan hanya instansi di daftar ini yang bisa registrasi.</p>
<?php endif; ?>

<div class="stats">
  <?php foreach ($perTanggal as $d => $list): $reg = count(array_filter($list, static fn ($u) => $u['instansi_id'])); ?>
    <div class="stat"><b><?= $reg ?> / <?= count($list) ?></b><span>sudah registrasi, <?= h(tgl($d, true)) ?></span></div>
  <?php endforeach; ?>
</div>

<div class="grid2" id="tambah">
  <form class="panel" method="post" action="<?= h(url('/admin/undangan')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <h2>Tambah satu instansi</h2>
    <div class="fields">
      <label class="field"><span>Nama instansi</span><input type="text" name="nama" maxlength="200" required placeholder="mis. BRMP Aceh"></label>
      <label class="field"><span>Provinsi</span><select name="provinsi" required autocomplete="off"><option value="">Pilih provinsi…</option>
        <?php foreach (provinsi_list() as $pv): ?><option><?= h($pv) ?></option><?php endforeach; ?></select></label>
      <div class="field"><span>Tanggal</span><?= $pilihTanggal('tanggal', $tglDefault, 'u1') ?></div>
    </div>
    <div class="actions"><button class="btn">Tambah</button></div>
  </form>

  <form class="panel" method="post" action="<?= h(url('/admin/undangan')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <h2>Tempel banyak dari Excel</h2>
    <p class="small muted">Satu instansi per baris: <b>Nama instansi</b> lalu <b>Provinsi</b>. Salin dua kolom dari Excel langsung, atau pisahkan dengan titik koma atau koma. Singkatan (Sumut, DIY, NTB) dan nama kota (Medan → Sumatera Utara) dikenali. Baris yang gagal dikembalikan ke kotak ini untuk diperbaiki. Instansi Jabodetabek (DKI Jakarta, atau nama berisi Bogor/Depok/Bekasi/Tangerang) otomatis ditandai pulang-pergi; periksa kolom Menginap.</p>
    <label class="field"><span class="sr-only">Daftar instansi</span>
      <textarea name="banyak" rows="6" required placeholder="BRMP Aceh; Aceh&#10;BRMP Sumatera Utara; Sumatera Utara&#10;Dinas Pertanian Kabupaten Bogor; Jawa Barat"><?= h($sisa['teks'] ?? '') ?></textarea></label>
    <div class="field" style="margin-top:10px"><span>Semua baris ikut tanggal</span><?= $pilihTanggal('tanggal', $tglDefault, 'u2') ?></div>
    <div class="actions"><button class="btn">Tambahkan semua</button></div>
  </form>
</div>

<?php if ($total): ?>
<label class="field" style="max-width:420px;margin:8px 0 16px"><span>Cari di daftar</span><input type="search" data-filter="#daftar-undangan" placeholder="Nama instansi atau provinsi"></label>
<?php endif; ?>
<div id="daftar-undangan">
<?php foreach ($perTanggal as $d => $list): ?>
  <section class="panel">
    <div class="panel-head"><h2><?= h(tgl($d, true)) ?></h2><span class="small muted"><?= count($list) ?> instansi</span></div>
    <?php if (!$list): ?><p class="muted small" style="margin:0">Belum ada instansi untuk tanggal ini.</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Instansi</th><th>Status</th><th>Menginap</th><th>Pindah tanggal</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $u): ?>
        <tr data-row>
          <td><b><?= h($u['nama']) ?></b><br><span class="small muted"><?= h($u['provinsi']) ?></span></td>
          <td><?php if ($u['instansi_id']): ?><span class="badge dibayar">Sudah registrasi</span><br><a class="small" href="<?= h(url('/admin/instansi/' . $u['instansi_id'])) ?>"><?= h(kode_instansi((int) $u['instansi_id'])) ?>, <?= (int) $u['peserta'] ?> peserta</a>
            <?php else: ?><span class="badge draft">Belum registrasi</span><?php endif; ?></td>
          <td><form method="post" action="<?= h(url('/admin/undangan/' . $u['id'] . '/pp')) ?>"
                <?= !$u['pp'] && $u['instansi_id'] ? 'data-confirm="Tandai ' . h($u['nama']) . ' sebagai Jabodetabek? Peserta yang memilih menginap akan dibatalkan menginapnya dan dilepas dari kamar & shuttle."' : '' ?>><?= csrf_field() ?>
              <input type="hidden" name="pp" value="<?= $u['pp'] ? '0' : '1' ?>">
              <?php if ($u['pp']): ?><span class="badge diajukan">Pulang-pergi</span><br><button class="linkbtn small">Buka pilihan menginap</button>
              <?php else: ?><span class="small">Boleh menginap</span><br><button class="linkbtn small">Tandai Jabodetabek</button><?php endif; ?>
            </form></td>
          <td><form class="inline-form" method="post" action="<?= h(url('/admin/undangan/' . $u['id'])) ?>" autocomplete="off"
                <?= $u['instansi_id'] ? 'data-confirm="Pindahkan ' . h($u['nama']) . ' beserta pesertanya? Kamar dan shuttle mereka dilepas dan perlu dibagi ulang."' : '' ?>><?= csrf_field() ?>
              <label class="sr-only" for="pt-<?= (int) $u['id'] ?>">Tanggal <?= h($u['nama']) ?></label>
              <select id="pt-<?= (int) $u['id'] ?>" name="tanggal"><?php foreach (event_dates() as $d2): ?><option value="<?= h($d2) ?>" <?= $d2 === $d ? 'selected' : '' ?>><?= h(tgl_pendek($d2)) ?></option><?php endforeach; ?></select>
              <button class="btn secondary small"<?= $u['foto'] ? ' disabled title="Sudah ada foto kegiatan"' : '' ?>>Pindah</button></form></td>
          <td><?php if (!$u['instansi_id']): ?><form method="post" action="<?= h(url('/admin/undangan/' . $u['id'] . '/hapus')) ?>" data-confirm="Hapus <?= h($u['nama']) ?> dari daftar undangan?"><?= csrf_field() ?><button class="linkbtn danger-link">Hapus</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<?php if ($lepas): ?>
<section class="panel">
  <h2>Sudah registrasi, belum ada di daftar</h2>
  <p class="small muted">Instansi ini registrasi sebelum daftar undangan diisi. Masukkan ke daftar supaya tanggalnya tercatat dan bisa dipindah di sini.</p>
  <div class="table-wrap"><table>
    <tbody>
    <?php foreach ($lepas as $i): $t = instansi_tanggal((int) $i['id']); ?>
      <tr><td><a href="<?= h(url('/admin/instansi/' . $i['id'])) ?>"><b><?= h($i['nama']) ?></b></a> <span class="kode"><?= h(kode_instansi((int) $i['id'])) ?></span><br><span class="small muted"><?= h(wilayah($i)) ?><?= $t ? ', ' . h(tgl($t)) : '' ?></span></td>
        <td style="text-align:right"><?php if ($t && $i['provinsi'] !== ''): ?><form method="post" action="<?= h(url('/admin/undangan')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="nama" value="<?= h($i['nama']) ?>"><input type="hidden" name="provinsi" value="<?= h($i['provinsi']) ?>"><input type="hidden" name="tanggal" value="<?= h($t) ?>">
          <button class="btn secondary small">Masukkan ke daftar (<?= h(tgl_pendek($t)) ?>)</button></form><?php else: ?><span class="small muted"><?= $t ? 'Provinsi belum diketahui' : 'Belum punya peserta' ?></span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
