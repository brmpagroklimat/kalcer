<?php
// Kirim email lewat SMTP (SSL, AUTH LOGIN) tanpa library. Opsional: aktif hanya
// kalau 'smtp' di event-config.php diisi. Mengembalikan false kalau gagal.
declare(strict_types=1);

function mail_enabled(): bool
{
    return is_array(config('smtp'));
}

function send_mail(string $to, string $subject, string $text): bool
{
    $c = config('smtp');
    if (!is_array($c) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fp = @stream_socket_client('ssl://' . $c['host'] . ':' . ($c['port'] ?? 465), $errno, $errstr, 15);
    if (!$fp) {
        error_log("SMTP connect gagal: $errstr");
        return false;
    }
    stream_set_timeout($fp, 15);
    $expect = static function (string $code) use ($fp): bool {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if (strncmp($resp, $code, 3) !== 0) {
            error_log('SMTP: ' . trim($resp));
            return false;
        }
        return true;
    };
    $cmd = static function (string $line, string $code) use ($fp, $expect): bool {
        fwrite($fp, $line . "\r\n");
        return $expect($code);
    };
    $from = $c['user'];
    $headers = [
        'From: ' . mb_encode_mimeheader($c['from_name'] ?? 'Panitia KALCER') . " <$from>",
        "To: <$to>",
        'Subject: ' . mb_encode_mimeheader($subject),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($text));
    $ok = $expect('220')
        && $cmd('EHLO ' . (parse_url(config('base_url'), PHP_URL_HOST) ?: 'localhost'), '250')
        && $cmd('AUTH LOGIN', '334')
        && $cmd(base64_encode($c['user']), '334')
        && $cmd(base64_encode($c['pass']), '235')
        && $cmd("MAIL FROM:<$from>", '250')
        && $cmd("RCPT TO:<$to>", '250')
        && $cmd('DATA', '354')
        && $cmd($body . "\r\n.", '250');
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

function mail_link_instansi(array $inst, string $token): bool
{
    if (!mail_enabled() || empty($inst['email'])) {
        return false;
    }
    $link = instansi_link($token);
    $text = "Yth. {$inst['koordinator']},\n\n"
        . "Pendaftaran {$inst['nama']} untuk " . setting('nama_kegiatan') . " sudah kami terima.\n"
        . 'Kode instansi: ' . kode_instansi((int) $inst['id']) . "\n\n"
        . "Gunakan link berikut untuk mengubah data peserta, mengunggah foto kegiatan, dan mengajukan reimburse:\n$link\n\n"
        . "Simpan link ini dan jangan dibagikan di luar rombongan instansi Anda. Siapa pun yang memegang link ini dapat mengubah data instansi.\n\n"
        . "Salam,\nPanitia KALCER";
    return send_mail($inst['email'], 'Link instansi ' . setting('nama_kegiatan'), $text);
}
