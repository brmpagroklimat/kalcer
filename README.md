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
