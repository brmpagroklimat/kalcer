<?php /** @var ?string $error */ ?>
<h1>Setup KALCER Event</h1>
<p class="lead">Membuat tabel database dan akun panitia pertama. Halaman ini hanya bisa dipakai sekali.</p>
<form method="post" class="panel"><?= csrf_field() ?>
  <?php if ($error): ?><div class="errors" role="alert"><?= h($error) ?></div><?php endif; ?>
  <div class="fields">
    <label class="field"><span>Kunci setup</span><input type="password" name="setup_key" required autocomplete="off"><small>Nilai <code>setup_key</code> di event-config.php.</small></label>
    <label class="field"><span>Nama panitia</span><input type="text" name="nama" value="<?= h(post('nama')) ?>" required></label>
    <label class="field"><span>Username</span><input type="text" name="username" value="<?= h(post('username')) ?>" autocapitalize="off" required></label>
    <label class="field"><span>Password <small>(minimal 10 karakter)</small></span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
  </div>
  <div class="actions"><button class="btn deep">Jalankan setup</button></div>
</form>
