<?php /** @var array $ok */
$sisa = array_values(array_diff(array_keys(MODUL), $ok['modul'])); ?>
<h1>Terima kasih!</h1>
<p class="lead">Masukan Anda untuk <?= $sisa ? h(implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'], $ok['modul']))) : 'keenam modul KALCER' ?> sudah kami terima.</p>
<?php if ($sisa): ?>
  <section class="panel">
    <h2>Ingin menguji modul KALCER lainnya?</h2>
    <p class="small muted" style="margin-top:0">Form uji coba lengkap menilai keenam modul sekaligus.</p>
    <div class="actions" style="margin-top:8px">
      <a class="btn" href="<?= h(url('/masukan?src=langsung')) ?>">Uji keenam modul</a>
    </div>
  </section>
<?php else: ?>
  <p><a class="btn secondary" href="<?= h(url('/')) ?>">Kembali ke beranda</a></p>
<?php endif; ?>
