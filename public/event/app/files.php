<?php
// Penyimpanan file upload di luar public_html, disajikan lewat PHP setelah cek akses.
declare(strict_types=1);

const MAX_UPLOAD = 15 * 1024 * 1024;
const MAX_SIDE = 2400;

function upload_root(): string
{
    $dir = config('uploads_dir');
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        throw new RuntimeException('Folder upload tidak bisa dibuat: ' . $dir);
    }
    return $dir;
}

/**
 * Simpan file upload. Gambar selalu di-encode ulang jadi JPEG (membuang data
 * tersembunyi & memperkecil ukuran); PDF hanya diterima kalau $allowPdf.
 * @return array{file:string,mime:string,size:int}
 */
function store_upload(array $f, int $instansiId, string $kind, bool $allowPdf): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new UserError('Pilih file terlebih dahulu.');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAX_UPLOAD) {
        throw new UserError('File terlalu besar (maksimal 15 MB).');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new UserError('Upload gagal. Coba lagi.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    $rel = $instansiId . '/' . $kind . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(6));
    $dir = upload_root() . '/' . dirname($rel);
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException('Folder upload tidak bisa dibuat.');
    }

    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if (!$img) {
            throw new UserError('Gambar tidak bisa dibaca. Gunakan foto JPG atau PNG.');
        }
        if ($mime === 'image/jpeg') {
            $img = apply_exif_orientation($img, $f['tmp_name']);
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, MAX_SIDE / max($w, $h));
        if ($scale < 1) {
            $resized = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
            imagedestroy($img);
            $img = $resized;
        }
        $rel .= '.jpg';
        imagejpeg($img, upload_root() . '/' . $rel, 82);
        imagedestroy($img);
        return ['file' => $rel, 'mime' => 'image/jpeg', 'size' => (int) filesize(upload_root() . '/' . $rel)];
    }
    if ($allowPdf && $mime === 'application/pdf') {
        $rel .= '.pdf';
        if (!move_uploaded_file($f['tmp_name'], upload_root() . '/' . $rel)) {
            throw new RuntimeException('Gagal menyimpan file.');
        }
        return ['file' => $rel, 'mime' => 'application/pdf', 'size' => (int) $f['size']];
    }
    if (in_array($mime, ['image/heic', 'image/heif'], true)) {
        throw new UserError('Format HEIC belum didukung. Di iPhone, pilih foto lewat tombol "Ambil foto" atau ubah ke JPG.');
    }
    throw new UserError($allowPdf ? 'File harus berupa foto (JPG/PNG) atau PDF.' : 'File harus berupa foto (JPG/PNG).');
}

function apply_exif_orientation($img, string $path)
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
    return $angle ? imagerotate($img, $angle, 0) : $img;
}

/** Baca waktu & GPS dari EXIF file asli (cadangan kalau browser tidak mengirimnya). */
function read_exif_meta(string $path): array
{
    $out = ['taken_at' => null, 'lat' => null, 'lon' => null];
    if (!function_exists('exif_read_data')) {
        return $out;
    }
    $e = @exif_read_data($path);
    if (!$e) {
        return $out;
    }
    $dt = $e['DateTimeOriginal'] ?? $e['DateTime'] ?? null;
    if ($dt && ($t = DateTime::createFromFormat('Y:m:d H:i:s', $dt))) {
        $out['taken_at'] = $t->format('Y-m-d H:i:s');
    }
    if (isset($e['GPSLatitude'], $e['GPSLongitude'])) {
        $out['lat'] = gps_decimal($e['GPSLatitude'], $e['GPSLatitudeRef'] ?? 'N');
        $out['lon'] = gps_decimal($e['GPSLongitude'], $e['GPSLongitudeRef'] ?? 'E');
    }
    return $out;
}

function gps_decimal(array $parts, string $ref): ?float
{
    $f = static function ($v) {
        $p = explode('/', (string) $v);
        return count($p) === 2 && (float) $p[1] != 0.0 ? (float) $p[0] / (float) $p[1] : (float) $p[0];
    };
    if (count($parts) < 3) {
        return null;
    }
    $d = $f($parts[0]) + $f($parts[1]) / 60 + $f($parts[2]) / 3600;
    return in_array($ref, ['S', 'W'], true) ? -$d : $d;
}

function file_path(string $rel): string
{
    if (str_contains($rel, '..')) {
        throw new RuntimeException('Path tidak valid');
    }
    return upload_root() . '/' . $rel;
}

function delete_upload(string $rel): void
{
    $p = file_path($rel);
    if (is_file($p)) {
        unlink($p);
    }
}

function send_file(string $rel, string $mime, string $downloadName, bool $inline = true): void
{
    $p = file_path($rel);
    if (!is_file($p)) {
        not_found();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($p));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '"');
    header('Cache-Control: private, max-age=86400');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; object-src 'self'");
    readfile($p);
    exit;
}

function slug(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s === '' ? 'file' : substr($s, 0, 50);
}
