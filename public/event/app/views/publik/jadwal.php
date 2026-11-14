<?php /** @var array $jadwal */ ?>
<h1>Jadwal acara</h1>
<p class="lead"><?= h(setting('nama_kegiatan')) ?><?= setting('lokasi') !== '' ? ', ' . h(setting('lokasi')) : '' ?>.</p>
<?php if (!$jadwal): ?><section class="panel"><p class="muted" style="margin:0">Jadwal acara akan diumumkan panitia di sini.</p></section><?php endif; ?>
<?php foreach ($jadwal as $d => $list): ?>
  <section class="panel">
    <h2><?= h(tgl($d, true)) ?></h2>
    <?= view('_jadwal_list', ['list' => $list]) ?>
  </section>
<?php endforeach; ?>
