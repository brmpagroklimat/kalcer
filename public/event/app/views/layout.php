<?php
/** @var string $title @var string $body */
$inst = $inst ?? null;
$isAdmin = current_admin() !== null;
$admin = !empty($admin);
$curPath = '/' . trim((string) preg_replace('#^/event#', '', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)), '/');
$adminNav = ['/admin' => 'Dashboard', '/admin/instansi' => 'Instansi', '/admin/rekap' => 'Rekap', '/admin/kamar' => 'Kamar', '/admin/shuttle' => 'Shuttle', '/admin/konten' => 'Jadwal & konten', '/admin/pengaturan' => 'Pengaturan'];
$adminAktif = static fn (string $href) => $href === '/admin' ? $curPath === '/admin' : str_starts_with($curPath, $href);
$flashes = take_flash();
$v = '22';
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
    <a class="brand" href="<?= h(url(!empty($admin) ? '/admin' : '/')) ?>">
      <img src="/logo-kalcer.png" alt="KALCER" width="170" height="32">
      <span class="brand-sub">Event</span>
    </a>
    <?php if (!empty($admin)): ?>
      <nav class="topnav" aria-label="Menu panitia">
        <?php foreach ($adminNav as $href => $label): ?>
          <a href="<?= h(url($href)) ?>" <?= $adminAktif($href) ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= h(url('/')) ?>">Lihat situs</a>
        <form method="post" action="<?= h(url('/admin/keluar')) ?>"><?= csrf_field() ?><button class="linkbtn">Keluar</button></form>
      </nav>
    <?php else: $navItems = ['beranda' => ['/', 'Beranda'], 'daftar' => ['/daftar', 'Registrasi'], 'jadwal' => ['/jadwal', 'Jadwal'], 'materi' => ['/materi', 'Materi'], 'galeri' => ['/galeri', 'Galeri']]; ?>
      <nav class="topnav" aria-label="Menu utama">
        <?php foreach ($navItems as $key => [$href, $label]): ?>
          <a href="<?= h(url($href)) ?>" <?= ($nav ?? '') === $key ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
        <?php if ($inst): ?>
          <form method="post" action="<?= h(url('/keluar')) ?>"><?= csrf_field() ?><button class="linkbtn"><?= $isAdmin ? 'Kembali ke panitia' : 'Keluar' ?></button></form>
        <?php elseif (current_instansi()): ?>
          <a class="pill" href="<?= h(url('/saya')) ?>">Halaman instansi</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</header>

<?php if ($inst): ?>
  <div class="inst-bar">
    <div class="wrap">
      <?php if ($isAdmin): ?><p class="as-admin">Anda membuka halaman instansi ini sebagai panitia. Batas waktu tidak berlaku.</p><?php endif; ?>
      <p class="inst-name"><?= h($inst['nama']) ?> <span class="kode"><?= h(kode_instansi((int) $inst['id'])) ?></span></p>
      <nav class="tabs" aria-label="Menu instansi">
        <a href="<?= h(url('/saya')) ?>" <?= ($tab ?? '') === 'ringkasan' ? 'aria-current="page"' : '' ?>>Ringkasan</a>
        <a href="<?= h(url('/saya/peserta')) ?>" <?= ($tab ?? '') === 'peserta' ? 'aria-current="page"' : '' ?>>Peserta</a>
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
  <div class="palette" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
  <div class="wrap">KALCER, Klinik Agroklimat Cerdas. Dikembangkan oleh BRMP Agroklimat.</div>
</footer>
<?php foreach ($scripts ?? [] as $s): ?>
  <script src="<?= h(url('/assets/' . $s)) ?>?v=<?= $v ?>"></script>
<?php endforeach; ?>
<script src="<?= h(url('/assets/app.js')) ?>?v=<?= $v ?>"></script>
</body>
</html>
