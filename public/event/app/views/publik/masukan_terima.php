<?php /** @var array $ok */
$sisa = array_values(array_diff(array_keys(MODUL), $ok['modul'])); ?>
<h1>Terima kasih!</h1>
<p class="lead">Masukan Anda untuk <?= h(implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'], $ok['modul']))) ?> sudah kami terima.</p>
<?php if ($sisa): ?>
  <section class="panel">
    <h2>Sudah mencoba modul lain?</h2>
    <p class="small muted" style="margin-top:0">Anda bisa menilai modul lain dengan form baru.</p>
    <div class="actions" style="margin-top:8px">
      <a class="btn" href="<?= h(url('/masukan?src=' . urlencode($ok['sumber']))) ?>">Nilai modul lain</a>
      <a class="btn secondary" href="<?= h(url('/')) ?>">Kembali ke beranda</a>
    </div>
  </section>
<?php else: ?>
  <p><a class="btn secondary" href="<?= h(url('/')) ?>">Kembali ke beranda</a></p>
<?php endif; ?>
