<?php /** @var ?string $error */ ?>
<h1>Reset akun panitia</h1>
<p class="lead">Setup sudah dijalankan. Lupa username atau password? Masukkan kunci setup untuk mengganti password akun yang ada, atau membuat akun baru.</p>
<form method="post" class="panel"><?= csrf_field() ?>
  <?php if ($error): ?><div class="errors" role="alert"><?= h($error) ?></div><?php endif; ?>
  <div class="fields">
    <label class="field"><span>Kunci setup</span><input type="password" name="setup_key" required autocomplete="off"><small>Nilai <code>setup_key</code> di event-config.php.</small></label>
    <label class="field"><span>Username</span><input type="text" name="username" value="<?= h(post('username')) ?>" autocapitalize="off" required><small>Username yang sudah ada: passwordnya diganti. Username baru: akun baru dibuat. Daftar akun tampil di Pengaturan setelah masuk.</small></label>
    <label class="field"><span>Nama <small>(hanya untuk akun baru)</small></span><input type="text" name="nama" value="<?= h(post('nama')) ?>"></label>
    <label class="field"><span>Password baru <small>(minimal 10 karakter)</small></span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
  </div>
  <div class="actions"><button class="btn deep">Simpan & masuk</button><a href="<?= h(url('/admin/login')) ?>">Kembali ke login</a></div>
</form>
