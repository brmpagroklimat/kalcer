<h1>Masuk lewat link instansi</h1>
<p class="lead">Halaman ini hanya bisa dibuka lewat link khusus yang diberikan saat instansi mendaftar.</p>
<p>Buka link tersebut dari WhatsApp atau email Anda. Kalau link hilang, hubungi panitia<?= setting('kontak_panitia') !== '' ? ' (' . h(setting('kontak_panitia')) . ')' : '' ?> untuk meminta link baru.</p>
<p><a class="btn secondary" href="<?= h(url('/')) ?>">Ke halaman pendaftaran</a></p>
