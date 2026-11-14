<?php /** @var array $list */ ?>
<ol class="agenda">
  <?php foreach ($list as $j): ?>
    <li><span class="agenda-time"><?= h(jam($j['jam_mulai'])) ?><?= $j['jam_selesai'] ? '–' . h(jam($j['jam_selesai'])) : '' ?></span>
      <span><b><?= h($j['acara']) ?></b><?= $j['keterangan'] ? '<br><span class="small muted">' . h($j['keterangan']) . '</span>' : '' ?></span></li>
  <?php endforeach; ?>
</ol>
