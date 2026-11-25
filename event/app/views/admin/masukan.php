<?php
/** @var array $total @var array $perSumber @var array $perPeran @var array $perModul @var array $teks @var array $respons @var array $gita @var array $gitaBuruk @var string $modul @var string $sumber */
$n = (int) $total['n'];
$umux = umux_skor($total['u1'] !== null ? (float) $total['u1'] : null, $total['u2'] !== null ? (float) $total['u2'] : null);
$pct = static fn ($a, $b) => $b > 0 ? (int) round($a / $b * 100) : 0;
$bar = static function (string $label, $avg, int $max = 5): string {
    if ($avg === null) {
        return '<div class="bar-row"><span>' . h($label) . '</span><span class="bar"></span><span class="muted small">–</span></div>';
    }
    $w = round((float) $avg / $max * 100);
    return '<div class="bar-row"><span>' . h($label) . '</span><span class="bar"><i style="width:' . $w . '%"></i></span><b>' . number_format((float) $avg, 1, ',', '') . '</b></div>';
};
$warna = ['sesuai' => 'var(--leaf)', 'sebagian' => 'var(--sun)', 'tidak' => 'var(--coral)', 'belum' => '#cfc8bb'];
?>
<div class="panel-head"><h1 style="margin:0">Rekap masukan</h1>
  <div class="inline-actions"><a class="btn secondary small" href="<?= h(url('/admin/masukan.csv')) ?>">Unduh CSV</a> <a class="btn small" href="<?= h(url('/admin/masukan/bagikan')) ?>">QR & tombol modul</a></div></div>
<p class="muted">Masukan pengguna untuk keenam modul KALCER, dari sesi sosialisasi, tombol di modul, dan akses langsung.</p>

<form class="filters" method="get">
  <label class="field"><span>Asal respons</span><select name="sumber"><option value="">Semua</option>
    <?php foreach (SUMBER as $k => $l): ?><option value="<?= h($k) ?>" <?= $sumber === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Jawaban teks untuk modul</span><select name="modul"><option value="">Semua modul</option>
    <?php foreach (MODUL as $k => $m): ?><option value="<?= h($k) ?>" <?= $modul === $k ? 'selected' : '' ?>><?= h($m['nama']) ?></option><?php endforeach; ?></select></label>
  <button class="btn secondary">Terapkan</button>
</form>

<div class="stats">
  <div class="stat"><b><?= $n ?></b><span>respons</span></div>
  <div class="stat"><b><?= $umux ?? '–' ?></b><span>skor UMUX-Lite (0–100, ≥68 = baik)</span></div>
  <div class="stat"><b><?= $total['lagi_n'] ? $pct($total['lagi'], $total['lagi_n']) . '%' : '–' ?></b><span>akan menggunakan lagi</span></div>
  <div class="stat"><b><?= (int) $total['bersedia'] ?></b><span>bersedia dihubungi untuk uji lanjutan</span></div>
</div>
<?php if ($n): ?>
<p class="small">
  <?php foreach (SUMBER as $k => $l): ?><span class="chip"><?= h($l) ?>: <?= (int) ($perSumber[$k] ?? 0) ?></span> <?php endforeach; ?>
  <?php foreach ($perPeran as $k => $c): ?><span class="chip"><?= h(PERAN[$k] ?? $k) ?>: <?= (int) $c ?></span> <?php endforeach; ?>
</p>
<?php endif; ?>

<h2>Per modul</h2>
<div class="modul-rekap">
  <?php foreach (MODUL as $slug => $m): $r = $perModul[$slug]; $mn = (int) $r['n']; ?>
    <section class="panel" style="margin:0">
      <div class="panel-head"><h3><?= h($m['nama']) ?></h3><span class="small muted"><?= $mn ?> penilai</span></div>
      <?php if (!$mn): ?><p class="muted small" style="margin:0">Belum ada penilaian.</p><?php else: ?>
        <p class="small" style="margin:0"><b>Isi sesuai kondisi lapangan?</b></p>
        <div class="stack" role="img" aria-label="<?= h(implode(', ', array_map(static fn ($k) => SESUAI[$k] . ' ' . (int) $r[$k], array_keys(SESUAI)))) ?>">
          <?php foreach (array_keys(SESUAI) as $k): if ($r[$k]): ?><i class="s-<?= $k ?>" style="width:<?= $pct($r[$k], $mn) ?>%"></i><?php endif; endforeach; ?></div>
        <p class="small muted" style="margin:0 0 10px"><?php foreach (SESUAI as $k => $l): ?><span class="legend-dot" style="background:<?= $warna[$k] ?>"></span><?= h($l) ?> <?= (int) $r[$k] ?><?php endforeach; ?></p>
        <?= $bar($slug === 'gita' ? 'Bisa langsung dipakai' : 'Bisa diterapkan', $r['diterapkan']) ?>
        <?php foreach (UI_BUTIR as $k => $l): if ($k === 'ui_peta' && !$m['peta']) { continue; } ?><?= $bar($l, $r[$k]) ?><?php endforeach; ?>
        <p class="small" style="margin:8px 0 0"><a href="<?= h(url('/admin/masukan?modul=' . $slug . ($sumber ? '&sumber=' . $sumber : '') . '#teks')) ?>">Lihat jawaban teks</a></p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

<section class="panel" id="gita" style="margin-top:22px">
  <div class="panel-head"><h2>Tanya GITA: 👍 / 👎 per jawaban</h2>
    <span class="small muted"><?= (int) $gita['n'] ?> penilaian: 👍 <?= (int) $gita['suka'] ?>, 👎 <?= (int) $gita['tidak'] ?></span></div>
  <?php if (!$gitaBuruk): ?>
    <p class="muted small" style="margin:0">Belum ada jawaban yang dinilai 👎. Penilaian ini masuk setelah tim GITA memasang tombol 👍/👎 (lihat <a href="<?= h(url('/admin/masukan/bagikan#gita-api')) ?>">QR & tombol modul</a>).</p>
  <?php else: ?>
    <ul class="teks-list">
      <?php foreach ($gitaBuruk as $g): ?>
        <li><span class="small muted"><?= h(tgl_jam($g['created_at'])) ?></span>
          <dl><dt>Pertanyaan</dt><dd><?= h($g['pertanyaan']) ?></dd>
            <?php if ($g['catatan']): ?><dt>Catatan pengguna</dt><dd><?= h($g['catatan']) ?></dd><?php endif; ?>
            <?php if ($g['jawaban']): ?><dt>Jawaban GITA</dt><dd><details><summary class="small">Tampilkan</summary><?= h($g['jawaban']) ?></details></dd><?php endif; ?></dl>
          <form method="post" action="<?= h(url('/admin/gita-nilai/' . $g['id'] . '/hapus')) ?>" data-confirm="Hapus penilaian ini?"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel" id="teks">
  <div class="panel-head"><h2>Jawaban teks<?= isset(MODUL[$modul]) ? ': ' . h(MODUL[$modul]['nama']) : '' ?></h2><span class="small muted"><?= count($teks) ?> penilaian</span></div>
  <?php if (!$teks): ?><p class="muted small" style="margin:0">Belum ada jawaban teks.</p><?php else: ?>
  <ul class="teks-list">
    <?php $sudah = []; foreach ($teks as $t): $pertama = !isset($sudah[$t['mid']]); $sudah[$t['mid']] = true; ?>
      <li><b><?= h(MODUL[$t['modul']]['nama'] ?? $t['modul']) ?></b> <span class="badge <?= $t['sesuai'] === 'tidak' ? 'ditolak' : ($t['sesuai'] === 'sesuai' ? 'dibayar' : 'draft') ?>"><?= h(SESUAI[$t['sesuai']] ?? '-') ?></span>
        <span class="small muted"> · <?= h($t['instansi']) ?>, <?= h(PERAN[$t['peran']] ?? $t['peran']) ?>, <?= h($t['provinsi']) ?> · <?= h(tgl_jam($t['created_at'])) ?></span>
        <dl>
          <?php if ($t['data_kurang']): ?><dt><?= $t['modul'] === 'gita' ? 'Topik/dokumen belum dijangkau' : 'Data/parameter kurang' ?></dt><dd><?= h($t['data_kurang']) ?></dd><?php endif; ?>
          <?php if ($t['gita_salah']): ?><dt>Jawaban GITA salah/kurang</dt><dd><?= h($t['gita_salah']) ?></dd><?php endif; ?>
          <?php if ($t['bingung']): ?><dt>Membingungkan</dt><dd><?= h($t['bingung']) ?></dd><?php endif; ?>
          <?php if ($t['saran'] && $pertama): ?><dt>Saran (untuk semua modul)</dt><dd><?= h($t['saran']) ?></dd><?php endif; ?>
          <?php if ($t['bersedia_dihubungi'] && $t['hp'] && $pertama): ?><dt>Bersedia dihubungi</dt><dd><?= h($t['nama'] ?: 'Tanpa nama') ?> <a class="wa-link" href="https://wa.me/<?= h(wa_digits($t['hp'])) ?>" target="_blank" rel="noopener">WhatsApp</a></dd><?php endif; ?>
        </dl></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Respons terbaru</h2>
  <?php if (!$respons): ?><p class="muted small" style="margin:0">Belum ada respons.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Waktu</th><th>Responden</th><th>Modul</th><th>Asal</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($respons as $r): ?>
      <tr><td class="small"><?= h(tgl_jam($r['created_at'])) ?></td>
        <td><b><?= h($r['instansi']) ?></b><?= $r['event_instansi_id'] ? ' <a class="kode" href="' . h(url('/admin/instansi/' . $r['event_instansi_id'])) . '">' . h(kode_instansi((int) $r['event_instansi_id'])) . '</a>' : '' ?><br><span class="small muted"><?= $r['nama'] ? h($r['nama']) . ', ' : '' ?><?= h(PERAN[$r['peran']] ?? $r['peran']) ?>, <?= h($r['provinsi']) ?></span></td>
        <td class="small"><?= h(implode(', ', array_map(static fn ($s) => MODUL[$s]['nama'] ?? $s, array_filter(explode(',', (string) $r['modul']))))) ?></td>
        <td class="small"><?= h(SUMBER[$r['sumber']] ?? $r['sumber']) ?></td>
        <td><form method="post" action="<?= h(url('/admin/masukan/' . $r['id'] . '/hapus')) ?>" data-confirm="Hapus respons dari <?= h($r['instansi']) ?>? (mis. spam atau uji coba)"><?= csrf_field() ?><button class="linkbtn danger-link small">Hapus</button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
