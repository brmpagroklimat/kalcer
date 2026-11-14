# KALCER, Klinik Agroklimat Cerdas

Landing page statis untuk `https://kalcer.agroklimat.id`. Berisi daftar alat agroklimat; setiap alat membuka aplikasinya di tab baru.

Dibuat dengan [Astro](https://astro.build) (output statis, tanpa server).

## Mengubah link alat

Semua alat ada di [`src/data/tools.ts`](src/data/tools.ts). Isi `href` dengan URL tujuan:

```ts
href: 'https://kekeringan.agroklimat.id',
```

`href: null` artinya alat belum tersedia: kartu tampil dengan label "Segera hadir" dan tidak bisa diklik. Tombol "Mulai chat" di blok Tanya KALCER aktif setelah `tanya-kalcer` punya `href`.

## Pengembangan lokal

Butuh Node 22.

```sh
npm install
npm run dev      # http://localhost:4321
npm run build    # hasil di dist/
npm run preview  # cek hasil build
```

## Deploy

1. Push ke `main`.
2. GitHub Actions (`.github/workflows/deploy.yml`) menjalankan build lalu menyimpan isi `dist/` ke branch `deploy`.
3. Hostinger (hPanel > Advanced > Git) menarik branch `deploy` ke folder subdomain lewat webhook auto-deploy.

Jangan edit branch `deploy` secara manual; isinya ditimpa setiap build.

`public/.htaccess` mengatur redirect ke HTTPS, halaman 404, dan cache.

## Event: pendaftaran kegiatan (`/event`)

Aplikasi PHP + MySQL di `public/event/` (tanpa framework/Composer). Ikut tersalin ke `dist/event/` saat build, lalu ter-deploy bersama landing page.

Alur: instansi mendaftar dan mendapat **link khusus** (`/event/i/<token>`, tanpa password) untuk mengelola peserta, mengunggah foto kegiatan (waktu & GPS dibaca dari EXIF, diberi watermark), dan mengajukan reimburse. Panitia masuk di `/event/admin` untuk verifikasi, pengaturan, dan ekspor CSV/ZIP.

### Setup di Hostinger (sekali)

1. hPanel → website kalcer.agroklimat.id → **Databases → Management**: buat database MySQL + user. Catat nama DB, user, dan password.
2. **File Manager**: di folder induk `public_html` (sejajar, *bukan di dalam* `public_html`):
   - upload `dev/event/event-config.sample.php`, ganti nama jadi `event-config.php`, isi kredensial DB dan `setup_key` (teks acak panjang);
   - buat folder `event-uploads/`.
3. Buka `https://kalcer.agroklimat.id/event/admin/setup`, masukkan `setup_key`, lalu buat akun panitia pertama.
4. Di **Pengaturan**: periksa nama kegiatan, lokasi, batas waktu, info hotel & shuttle, dan kontak panitia.

Config dan upload ada di luar `public_html`, jadi tidak bisa dibuka dari browser dan tidak tertimpa deploy. Kebutuhan server: PHP 8.0+ dengan ekstensi `pdo_mysql`, `gd`, `fileinfo`, `exif`, `zip` (standar di Hostinger).

### Lokal

```sh
./dev/event/up.sh            # PHP 8.3 + MariaDB di Docker → http://localhost:8090/event
./dev/event/down.sh --reset  # hentikan + hapus data
```

Setup lokal memakai `setup_key` `dev-setup-key` (lihat `dev/event/event-config.php`).

### Alur kerja panitia (dashboard)

Diagram alur di bagian bawah Dashboard panitia berasal dari `public/event/app/views/admin/_alur.php`. **Perbarui daftar `$fase` di file itu setiap kali proses bisnis berubah** (fitur baru, langkah baru, atau urutan berubah).
