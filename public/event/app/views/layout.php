<?php
/** @var string $title @var string $body */
$inst = $inst ?? null;
$isAdmin = current_admin() !== null;
$flashes = take_flash();
$v = '2';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($title) ?> | KALCER Event</title>
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon-64.png" type="image/png" sizes="64x64">
<link rel="stylesheet" href="<?= h(url('/assets/event.css')) ?>?v=<?= $v ?>">
</head>
<body>
<header class="top">
  <div class="wrap top-inner">
    <a class="brand" href="<?= h(url($isAdmin && empty($inst) ? '/admin' : '/')) ?>">
      <img src="/logo.png" alt="" width="31" height="36">
      <span>KALCER <small>Event</small></span>
    </a>
    <?php if (!empty($admin) || ($isAdmin && empty($inst))): ?>
      <nav class="topnav" aria-label="Menu panitia">
        <a href="<?= h(url('/admin')) ?>">Dashboard</a>
        <a href="<?= h(url('/admin/instansi')) ?>">Instansi</a>
        <a href="<?= h(url('/admin/pengaturan')) ?>">Pengaturan</a>
        <form method="post" action="<?= h(url('/admin/keluar')) ?>"><?= csrf_field() ?><button class="linkbtn">Keluar</button></form>
      </nav>
    <?php elseif ($inst): ?>
      <form method="post" action="<?= h(url('/keluar')) ?>" class="topnav"><?= csrf_field() ?><button class="linkbtn"><?= $isAdmin ? 'Kembali ke panitia' : 'Keluar' ?></button></form>
    <?php endif; ?>
  </div>
</header>

<?php if ($inst): ?>
  <div class="inst-bar">
    <div class="wrap">
      <?php if ($isAdmin): ?><p class="as-admin">Anda membuka halaman instansi ini sebagai panitia. Batas waktu tidak berlaku.</p><?php endif; ?>
      <p class="inst-name"><?= h($inst['nama']) ?> <span class="kode"><?= h(kode_instansi((int) $inst['id'])) ?></span></p>
      <nav class="tabs" aria-label="Menu instansi">
        <a href="<?= h(url('/saya')) ?>" <?= ($tab ?? '') === 'peserta' ? 'aria-current="page"' : '' ?>>Peserta</a>
        <a href="<?= h(url('/saya/foto')) ?>" <?= ($tab ?? '') === 'foto' ? 'aria-current="page"' : '' ?>>Foto kegiatan</a>
        <a href="<?= h(url('/saya/biaya')) ?>" <?= ($tab ?? '') === 'biaya' ? 'aria-current="page"' : '' ?>>Reimburse</a>
      </nav>
    </div>
  </div>
<?php endif; ?>

<main class="wrap<?= !empty($narrow) ? ' narrow' : '' ?>">
  <?php foreach ($flashes as [$type, $msg]): ?>
    <div class="flash <?= h($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= h($msg) ?></div>
  <?php endforeach; ?>
  <?= $body ?>
</main>

<footer class="foot">
  <div class="wrap">KALCER, Klinik Agroklimat Cerdas. Dikembangkan oleh BRMP Agroklimat.</div>
</footer>
<?php foreach ($scripts ?? [] as $s): ?>
  <script src="<?= h(url('/assets/' . $s)) ?>?v=<?= $v ?>"></script>
<?php endforeach; ?>
<script src="<?= h(url('/assets/app.js')) ?>?v=<?= $v ?>"></script>
</body>
</html>
