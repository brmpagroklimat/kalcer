#!/usr/bin/env bash
# Salin halaman depan https://agroklimat.id/ (HTML + gambar di assets/) ke public/,
# supaya kalcer.agroklimat.id tampil sama dengan agroklimat.id.
# Jalankan lagi setiap kali agroklimat.id diubah, lalu commit & push (deploy otomatis).
#   ./scripts/salin-agroklimat.sh
set -euo pipefail
cd "$(dirname "$0")/.."
SUMBER="https://agroklimat.id"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

curl -fsSL -m 30 -A "Mozilla/5.0 (salin-kalcer)" "$SUMBER/" -o "$TMP/index.html"
grep -qi "<title>.*KALCER" "$TMP/index.html" || { echo "Halaman yang diunduh bukan halaman KALCER (mungkin terkena cek browser). Dibatalkan." >&2; exit 1; }

mkdir -p public/assets
for f in $(grep -oE "assets/[A-Za-z0-9._-]+" "$TMP/index.html" | sort -u); do
  curl -fsSL -m 60 "$SUMBER/$f" -o "public/$f"
  echo "  $f"
done

# Penanda asal salinan (di dalam <head>, supaya tidak memicu quirks mode).
TGL="$(date +%Y-%m-%d)"
sed "0,/<head>/s##<head>\n<!-- Salinan dari $SUMBER/ pada $TGL. Jangan diedit di sini: ubah di agroklimat.id lalu jalankan scripts/salin-agroklimat.sh -->#" \
  "$TMP/index.html" > public/index.html
echo "public/index.html diperbarui dari $SUMBER ($TGL)."
