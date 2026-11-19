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
        'jadwal' => jadwal_ada(),
        'nFoto' => (int) row('SELECT COUNT(*) n FROM galeri')['n'],
    ], ['nav' => 'beranda']);
}

/** Jadwal acara = link (mis. Google Docs) dan/atau satu file (PDF/gambar) dari panitia. */
function jadwal_info(): array
{
    return [
        'link' => setting('jadwal_link'),
        'file' => setting('jadwal_file'),
        'ext' => setting('jadwal_ext'),
        'nama' => setting('jadwal_nama'),
        'diubah' => setting('jadwal_diubah'),
    ];
}

function jadwal_ada(): bool
{
    $j = jadwal_info();
    return $j['link'] !== '' || $j['file'] !== '';
}

function publik_jadwal(): void
{
    render('publik/jadwal', 'Jadwal acara', ['j' => jadwal_info()], ['nav' => 'jadwal']);
}

function media_jadwal(): void
{
    $j = jadwal_info();
    if ($j['file'] === '') {
        not_found();
    }
    send_file($j['file'], MATERI_EXT[$j['ext']], $j['nama'], true);
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
    render('admin/konten', 'Jadwal, materi & galeri', [
        'materi' => rows('SELECT * FROM materi ORDER BY id DESC'),
        'jadwal' => jadwal_info(),
        'albums' => galeri_albums(),
    ], ['admin' => true, 'scripts' => ['upload.js']]);
}

function admin_jadwal_simpan(): void
{
    require_admin();
    $link = post('jadwal_link');
    if (!preg_match('#^https://\S+$#i', $link)) {
        throw new UserError('Isi link jadwal yang diawali https:// (mis. link Google Docs yang sudah dibagikan).');
    }
    set_setting('jadwal_link', $link);
    if (!empty($_FILES['jadwal_file']['name'])) {
        $saved = store_materi($_FILES['jadwal_file']);
        if (!in_array($saved['ext'], ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            delete_upload($saved['file']);
            throw new UserError('File jadwal harus PDF, JPG, atau PNG.');
        }
        if (setting('jadwal_file') !== '') {
            delete_upload(setting('jadwal_file'));
        }
        set_setting('jadwal_file', $saved['file']);
        set_setting('jadwal_ext', $saved['ext']);
        set_setting('jadwal_nama', $saved['nama_file']);
    }
    if (post('hapus_file') === '1' && setting('jadwal_file') !== '') {
        delete_upload(setting('jadwal_file'));
        foreach (['jadwal_file', 'jadwal_ext', 'jadwal_nama'] as $k) {
            set_setting($k, '');
        }
    }
    set_setting('jadwal_diubah', date('Y-m-d H:i:s'));
    flash('ok', 'Jadwal acara disimpan.');
    redirect('/admin/konten#jadwal');
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
        if ((int) $size > MAX_KONTEN) {
            throw new UserError('Foto maksimal 2 MB (' . $name . ' ' . ukuran($size) . ').');
        }
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
