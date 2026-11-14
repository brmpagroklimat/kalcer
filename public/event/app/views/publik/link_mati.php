<h1>Link tidak berlaku</h1>
<p class="lead">Link instansi ini tidak dikenali. Mungkin panitia sudah menerbitkan link baru, sehingga link lama tidak bisa dipakai lagi.</p>
<p>Gunakan link terbaru yang Anda terima, atau hubungi panitia<?= setting('kontak_panitia') !== '' ? ' (' . h(setting('kontak_panitia')) . ')' : '' ?> untuk meminta link baru.</p>
<p><a href="<?= h(url('/')) ?>">Ke halaman pendaftaran</a></p>
