# Arsip: landing KALCER lama (Astro)

Landing kalcer.agroklimat.id versi Astro (desain geometris, font Raleway, 6 kartu alat + Tanya Gita),
dipakai sampai 24 November 2026. Sejak itu halaman depan diganti salinan https://agroklimat.id/
(`public/index.html`, diperbarui lewat `scripts/salin-agroklimat.sh`).

- Sumber lengkap ada di folder `src/` ini (komponen, data alat di `src/data/tools.ts`, gaya di `src/styles/`).
- Versi persis sebelum diganti juga ditandai git tag `landing-kalcer-lama`.

## Memakai lagi

1. Pindahkan `arsip/landing-kalcer-lama/src` kembali ke `src/` di root repo.
2. Hapus `public/index.html` dan `public/404.html` (bentrok dengan halaman Astro).
3. `npm run build`, lalu commit & push.

Atau lihat versi lamanya: `git checkout landing-kalcer-lama`.
