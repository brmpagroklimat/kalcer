<?php
// Microsite publik: beranda, materi unduhan, galeri foto. Dikelola panitia di /admin/konten.
declare(strict_types=1);

function daftar_open(): bool
{
    return setting('daftar_dibuka') === '1' && before('batas_daftar');
}

function publik_beranda(): void
{
    render('publik/beranda', setting('nama_kegiatan'), [
        'dates' => event_dates(),
        'open' => daftar_open(),
        'nMateri' => (int) row('SELECT COUNT(*) n FROM materi')['n'],
        'nFoto' => (int) row('SELECT COUNT(*) n FROM galeri')['n'],
    ], ['nav' => 'beranda']);
}

function publik_materi(): void
{
    render('publik/materi', 'Materi', ['items' => rows('SELECT * FROM materi ORDER BY id DESC')], ['nav' => 'materi']);
}

function galeri_albums(): array
{
    $albums = [];
    foreach (rows('SELECT * FROM galeri ORDER BY id') as $g) {
        $albums[$g['album']][] = $g;
    }
    return $albums;
}

function publik_galeri(): void
{
    render('publik/galeri', 'Galeri', ['albums' => galeri_albums()], ['nav' => 'galeri']);
}

function media_materi(string $id): void
{
    $m = row('SELECT * FROM materi WHERE id = ?', [(int) $id]);
    if (!$m) {
        not_found();
    }
    $inline = in_array($m['ext'], ['pdf', 'jpg', 'jpeg', 'png'], true);
    send_file($m['file'], MATERI_EXT[$m['ext']], $m['nama_file'], $inline);
}

function media_galeri(string $id, string $thumb = ''): void
{
    $g = row('SELECT * FROM galeri WHERE id = ?', [(int) $id]);
    if (!$g) {
        not_found();
    }
    send_file($thumb !== '' ? $g['thumb'] : $g['file'], 'image/jpeg', slug($g['album']) . "-$id.jpg");
}

function galeri_zip(): void
{
    $album = (string) ($_GET['album'] ?? '');
    $items = rows('SELECT * FROM galeri WHERE album = ? ORDER BY id', [$album]);
    if (!$items) {
        not_found();
    }
    if (!class_exists('ZipArchive')) {
        throw new UserError('Ekstensi ZIP tidak tersedia di server.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'kcg');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($items as $i => $g) {
        if (is_file(file_path($g['file']))) {
            $zip->addFile(file_path($g['file']), sprintf('%s/%03d.jpg', slug($album), $i + 1));
            $zip->setCompressionName(sprintf('%s/%03d.jpg', slug($album), $i + 1), ZipArchive::CM_STORE);
        }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="galeri-' . slug($album) . '.zip"');
    readfile($tmp);
    unlink($tmp);
}

// ---------- panitia ----------

function admin_konten(): void
{
    require_admin();
    render('admin/konten', 'Materi & galeri', [
        'materi' => rows('SELECT * FROM materi ORDER BY id DESC'),
        'albums' => galeri_albums(),
        'limit' => server_upload_limit(),
    ], ['admin' => true, 'scripts' => ['upload.js']]);
}

function admin_materi_tambah(): void
{
    require_admin();
    $judul = post('judul');
    if ($judul === '') {
        throw new UserError('Isi judul materi.');
    }
    $saved = store_materi($_FILES['file'] ?? []);
    q('INSERT INTO materi (judul, deskripsi, file, nama_file, ext, size) VALUES (?, ?, ?, ?, ?, ?)', [
        $judul, post('deskripsi') ?: null, $saved['file'], $saved['nama_file'], $saved['ext'], $saved['size'],
    ]);
    flash('ok', "Materi \"$judul\" ditambahkan.");
    redirect('/admin/konten');
}

function admin_materi_hapus(string $id): void
{
    require_admin();
    $m = row('SELECT * FROM materi WHERE id = ?', [(int) $id]);
    if ($m) {
        q('DELETE FROM materi WHERE id = ?', [$m['id']]);
        delete_upload($m['file']);
        flash('ok', 'Materi "' . $m['judul'] . '" dihapus.');
    }
    redirect('/admin/konten');
}

function admin_galeri_tambah(): void
{
    require_admin();
    $album = post('album');
    if ($album === '' || mb_strlen($album) > 120) {
        throw new UserError('Isi nama album (mis. "Hari 1, 2 Desember").');
    }
    $files = $_FILES['foto'] ?? [];
    // Bisa satu file (dari skrip upload) atau banyak (form biasa tanpa JS).
    $list = is_array($files['name'] ?? null)
        ? array_map(null, $files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size'])
        : [[$files['name'] ?? '', $files['type'] ?? '', $files['tmp_name'] ?? '', $files['error'] ?? UPLOAD_ERR_NO_FILE, $files['size'] ?? 0]];
    $n = 0;
    foreach ($list as [$name, $type, $tmp, $err, $size]) {
        $saved = store_upload(['name' => $name, 'type' => $type, 'tmp_name' => $tmp, 'error' => $err, 'size' => $size], 0, 'galeri', false);
        $thumb = make_thumb($saved['file']);
        q('INSERT INTO galeri (album, file, thumb, caption) VALUES (?, ?, ?, ?)', [$album, $saved['file'], $thumb, post('caption') ?: null]);
        $n++;
    }
    if (wants_json()) {
        json_ok();
    }
    flash('ok', "$n foto ditambahkan ke album \"$album\".");
    redirect('/admin/konten#galeri');
}

function admin_galeri_hapus(string $id): void
{
    require_admin();
    $g = row('SELECT * FROM galeri WHERE id = ?', [(int) $id]);
    if ($g) {
        q('DELETE FROM galeri WHERE id = ?', [$g['id']]);
        delete_upload($g['file']);
        delete_upload($g['thumb']);
        flash('ok', 'Foto dihapus.');
    }
    redirect('/admin/konten#galeri');
}
