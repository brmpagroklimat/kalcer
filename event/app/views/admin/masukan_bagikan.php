<?php
$qrUrl = masukan_url_pendek('src=sesi');
$js = event_url_abs('/assets/masukan.js');
$api = event_url_abs('/api/gita-nilai');
?>
<h1>QR & tombol modul</h1>
<p class="lead">Tiga jalur masukan: QR di sesi sosialisasi, tombol "Beri masukan" di keenam modul, dan 👍/👎 di setiap jawaban Tanya GITA. Semua masuk ke <a href="<?= h(url('/admin/masukan')) ?>">Rekap masukan</a>.</p>

<div class="grid2">
  <section class="panel">
    <h2>1. QR untuk slide terakhir</h2>
    <p class="small muted" style="margin-top:0">Peserta menguji <b>keenam modul</b> lalu menilai semuanya di satu form. Sisihkan sekitar 5 menit praktik per modul dan <b>15 menit</b> untuk mengisi, di dalam sesi. Kalau diminta diisi setelah acara, biasanya jumlah respons anjlok.</p>
    <div id="qr-masukan" data-qr="<?= h($qrUrl) ?>" aria-label="QR form masukan"></div>
    <div class="linkbox" style="margin-top:12px">
      <label class="sr-only" for="link-qr">Alamat form untuk sesi</label>
      <input id="link-qr" type="text" readonly value="<?= h($qrUrl) ?>">
      <button class="btn deep" type="button" data-copy="#link-qr">Salin</button>
    </div>
    <div class="actions"><a class="btn secondary" href="#" data-qr-unduh="qr-masukan-kalcer.png" download>Unduh QR (PNG)</a>
      <a class="btn secondary" href="<?= h($qrUrl) ?>" target="_blank" rel="noopener">Coba buka form</a></div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Tugas praktik per modul</h2><button class="btn secondary small no-print" type="button" onclick="window.print()">Cetak</button></div>
    <p class="small muted" style="margin-top:0">Semua peserta mengerjakan keenam tugas ini, supaya menilai setelah benar-benar mencoba. Tugas yang sama muncul di form.</p>
    <ol class="tugas-list">
      <?php foreach (MODUL as $m): ?><li><b><?= h($m['nama']) ?></b> <span class="small muted">(<?= h(preg_replace('#^https://#', '', $m['url'])) ?>)</span><br><?= h($m['tugas']) ?></li><?php endforeach; ?>
    </ol>
  </section>
</div>

<section class="panel no-print">
  <h2>2. Tombol "Beri masukan" di setiap modul</h2>
  <p>Kirim snippet di bawah ke pengelola masing-masing modul. Cukup ditempel sekali sebelum <code>&lt;/body&gt;</code>; tombol melayang muncul di pojok kanan bawah dan membuka form khusus modul itu (pengguna umum cukup menilai modul yang dipakainya).</p>
  <ul class="small muted">
    <li>Bila menutupi tombol peta atau kotak chat, tambahkan <code>data-posisi="kiri"</code> atau naikkan dengan <code>data-bawah="90"</code> (piksel dari bawah).</li>
    <li>Tombol tidak memengaruhi tampilan modul (terisolasi), tanpa pustaka tambahan, dan bisa disembunyikan pengguna dengan tombol ×.</li>
  </ul>
  <div class="table-wrap"><table>
    <thead><tr><th>Modul</th><th>Snippet</th><th></th></tr></thead>
    <tbody>
    <?php foreach (MODUL as $slug => $m): $sn = '<script src="' . $js . '" data-modul="' . $slug . '" defer></script>'; ?>
      <tr><td><b><?= h($m['nama']) ?></b><br><span class="small muted"><?= h(preg_replace('#^https://#', '', $m['url'])) ?></span></td>
        <td style="min-width:320px"><label class="sr-only" for="sn-<?= h($slug) ?>">Snippet <?= h($m['nama']) ?></label><textarea id="sn-<?= h($slug) ?>" class="snippet" rows="2" readonly><?= h($sn) ?></textarea></td>
        <td><button class="btn secondary small" type="button" data-copy="#sn-<?= h($slug) ?>">Salin</button></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="small muted">Contoh tampilan: <a href="<?= h(url('/assets/masukan-contoh.html')) ?>" target="_blank" rel="noopener">halaman contoh dengan tombol</a>.</p>
</section>

<section class="panel no-print" id="gita-api">
  <h2>3. 👍 / 👎 di setiap jawaban Tanya GITA (untuk tim GITA)</h2>
  <p>Masukan paling berharga untuk menyempurnakan dokumen sumber GITA. Tim GITA menambahkan tombol 👍/👎 di bawah tiap jawaban (dan kolom "jawaban salah atau kurang" saat 👎), lalu mengirimnya ke KALCER:</p>
  <label class="sr-only" for="api-gita">Contoh kode</label>
  <textarea id="api-gita" class="snippet" rows="12" readonly><?= h("// Panggil saat pengguna menekan 👍 (nilai 1) atau 👎 (nilai -1).\n"
    . "fetch('" . $api . "', {\n"
    . "  method: 'POST',\n"
    . "  headers: { 'Content-Type': 'application/json' },\n"
    . "  body: JSON.stringify({\n"
    . "    pertanyaan: pertanyaanPengguna,   // wajib\n"
    . "    jawaban: jawabanGita,             // disarankan\n"
    . "    nilai: -1,                        // 1 = 👍, -1 = 👎\n"
    . "    catatan: isiKolomJawabanSalah     // opsional, terutama saat 👎\n"
    . "  })\n"
    . "});") ?></textarea>
  <div class="actions"><button class="btn secondary small" type="button" data-copy="#api-gita">Salin kode</button></div>
  <p class="small muted">Hanya diterima dari <code><?= h(GITA_ORIGIN) ?></code>. Batas 30 penilaian per 10 menit per perangkat. Hasilnya tampil di Rekap masukan, bagian Tanya GITA.</p>
</section>
