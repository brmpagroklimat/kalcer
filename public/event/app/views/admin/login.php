<?php /** @var ?string $error */ ?>
<h1>Login panitia</h1>
<form method="post" class="panel"><?= csrf_field() ?>
  <?php if ($error): ?><div class="errors" role="alert"><?= h($error) ?></div><?php endif; ?>
  <div class="fields">
    <label class="field"><span>Username</span><input type="text" name="username" value="<?= h(post('username')) ?>" autocapitalize="off" autocomplete="username" required></label>
    <label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
  </div>
  <div class="actions"><button class="btn deep">Masuk</button></div>
</form>
