#!/usr/bin/env bash
# Hentikan lingkungan lokal. Tambah --reset untuk menghapus database & upload.
docker rm -f kalcer-event-db kalcer-event-web >/dev/null 2>&1 || true
if [ "${1:-}" = "--reset" ]; then docker volume rm kalcer-event-db kalcer-event-uploads >/dev/null 2>&1 || true; fi
