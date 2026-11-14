<?php
/*
 * KALCER Event: file konfigurasi.
 *
 * Simpan sebagai  event-config.php  di folder INDUK public_html
 * (contoh di Hostinger: domains/kalcer.agroklimat.id/event-config.php),
 * BUKAN di dalam public_html, supaya tidak bisa dibuka dari browser
 * dan tidak tertimpa saat deploy.
 *
 * Buat juga folder  event-uploads/  di tempat yang sama (sejajar public_html).
 */
return [
    // hPanel > Databases > Management. Host di Hostinger biasanya 'localhost'.
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_kalcer_event',
        'user' => 'u123456789_kalcer',
        'pass' => 'GANTI_DENGAN_PASSWORD_DATABASE',
    ],

    // Kunci untuk halaman /event/admin/setup (membuat tabel & akun panitia pertama).
    // Isi dengan teks acak panjang, dan jangan dibagikan.
    'setup_key' => 'GANTI_DENGAN_TEKS_ACAK_PANJANG',

    'base_url' => 'https://kalcer.agroklimat.id/event',

    // Kosongkan (null) untuk memakai folder event-uploads/ di samping file ini.
    'uploads_dir' => null,

    'secure_cookie' => true,

    // Opsional: kirim link instansi lewat email (mailbox Hostinger).
    // Biarkan null kalau tidak dipakai.
    'smtp' => null,
    /*
    'smtp' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'user' => 'noreply@agroklimat.id',
        'pass' => 'PASSWORD_MAILBOX',
        'from_name' => 'Panitia KALCER',
    ],
    */
];
