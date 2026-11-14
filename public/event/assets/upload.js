/* KALCER Event: upload foto evidence & struk.
 * Foto evidence: baca EXIF (waktu + GPS) dari file ASLI, kompres, tempel
 * watermark (instansi, tahap, waktu), lalu kirim. Struk: hanya dikompres.
 * Kalau gambar tidak bisa diproses di browser (mis. HEIC di Chrome), file
 * asli dikirim apa adanya dan server yang memprosesnya. */
(function () {
  'use strict';
  var MAX_SIDE = 2000;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmt(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' +
      pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  function human(d) {
    return d.getDate() + ' ' + BULAN[d.getMonth()] + ' ' + d.getFullYear() + ' ' + pad(d.getHours()) + '.' + pad(d.getMinutes());
  }

  // Catatan: opsi `pick` rusak di build lite exifr 7.1.3, jadi pakai parse() default
  // (TIFF + EXIF + GPS, termasuk latitude/longitude desimal).
  function readMeta(file) {
    var out = { taken: null, lat: null, lon: null };
    if (!window.exifr) return Promise.resolve(out);
    return window.exifr.parse(file).then(function (m) {
      if (!m) return out;
      var t = m.DateTimeOriginal || m.CreateDate;
      if (t instanceof Date && !isNaN(t)) out.taken = t;
      if (typeof m.latitude === 'number' && typeof m.longitude === 'number' && !isNaN(m.latitude)) {
        out.lat = m.latitude; out.lon = m.longitude;
      }
      return out;
    }).catch(function () { return out; });
  }

  function loadImage(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return loadViaImg(file); });
    }
    return loadViaImg(file);
  }
  function loadViaImg(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }

  function render(file, lines) {
    return loadImage(file).then(function (img) {
      var w = img.width, h = img.height, s = Math.min(1, MAX_SIDE / Math.max(w, h));
      w = Math.round(w * s); h = Math.round(h * s);
      var c = document.createElement('canvas');
      c.width = w; c.height = h;
      var ctx = c.getContext('2d');
      ctx.drawImage(img, 0, 0, w, h);
      if (lines && lines.length) {
        var fs = Math.max(14, Math.round(Math.min(w, h) * 0.026));
        var lh = Math.round(fs * 1.35), padY = Math.round(fs * 0.7);
        var bandH = lines.length * lh + padY * 2;
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(0, h - bandH, w, bandH);
        ctx.fillStyle = '#fff';
        ctx.font = '600 ' + fs + 'px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
        ctx.textBaseline = 'top';
        lines.forEach(function (t, i) { ctx.fillText(t, Math.round(fs * 0.8), h - bandH + padY + i * lh, w - fs * 1.6); });
      }
      return new Promise(function (resolve) { c.toBlob(resolve, 'image/jpeg', 0.85); });
    });
  }

  function send(form, fields, blob, filename, status) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
      fd.append(form.dataset.fileField || 'foto', blob, filename);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable && status.bar) status.bar.style.width = Math.round(e.loaded / e.total * 100) + '%';
      };
      xhr.onload = function () {
        var res = {};
        try { res = JSON.parse(xhr.responseText); } catch (e) { /* bukan JSON */ }
        if (xhr.status >= 200 && xhr.status < 300 && res.ok) resolve(res);
        else reject(new Error(res.error || (xhr.status === 413 ? 'File terlalu besar.' : 'Upload gagal (' + xhr.status + '). Coba lagi.')));
      };
      xhr.onerror = function () { reject(new Error('Koneksi terputus. Periksa sinyal lalu coba lagi.')); };
      xhr.send(fd);
    });
  }

  function baseFields(form) {
    var f = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'file' || el.type === 'button' || el.type === 'submit') return;
      if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
      f[el.name] = el.value;
    });
    return f;
  }

  function statusUi(form) {
    var s = form.querySelector('.upload-status');
    var prog = form.querySelector('.progress');
    if (!prog) {
      prog = document.createElement('div');
      prog.className = 'progress';
      prog.innerHTML = '<i></i>';
      form.appendChild(prog);
    }
    return {
      el: s, bar: prog.firstChild,
      set: function (msg, err) { s.textContent = msg; s.classList.toggle('err', !!err); },
      lock: function (on) {
        Array.prototype.forEach.call(form.querySelectorAll('input[type=file], button'), function (b) { b.disabled = on; });
        form.querySelectorAll('label.btn').forEach(function (l) { l.toggleAttribute('aria-disabled', on); l.style.pointerEvents = on ? 'none' : ''; l.style.opacity = on ? '.5' : ''; });
      }
    };
  }

  // ---- foto evidence ----
  document.querySelectorAll('form.js-photo').forEach(function (form) {
    form.addEventListener('change', function (e) {
      if (e.target.type !== 'file' || !e.target.files.length) return;
      var files = Array.prototype.slice.call(e.target.files);
      var st = statusUi(form);
      st.lock(true);
      var done = 0, noExif = 0;
      files.reduce(function (p, file, i) {
        return p.then(function () {
          st.set('Memproses foto ' + (i + 1) + ' dari ' + files.length + '…');
          st.bar.style.width = '0';
          return readMeta(file).then(function (meta) {
            var now = new Date();
            var lines = [
              form.dataset.instansi + ' | ' + form.dataset.tahap + ' | ' + form.dataset.tanggal,
              (meta.taken ? 'Foto: ' + human(meta.taken) : 'Foto: tanpa timestamp kamera') + ' | Diunggah: ' + human(now)
            ];
            if (meta.lat !== null) lines.push('GPS: ' + meta.lat.toFixed(5) + ', ' + meta.lon.toFixed(5));
            if (!meta.taken) noExif++;
            var fields = baseFields(form);
            fields.taken_at = meta.taken ? fmt(meta.taken) : '';
            fields.lat = meta.lat !== null ? meta.lat.toFixed(6) : '';
            fields.lon = meta.lon !== null ? meta.lon.toFixed(6) : '';
            return render(file, lines)
              .then(function (blob) { return [blob, 'foto.jpg']; }, function () { return [file, file.name]; })
              .then(function (b) {
                st.set('Mengunggah foto ' + (i + 1) + ' dari ' + files.length + '…');
                return send(form, fields, b[0], b[1], st);
              })
              .then(function () { done++; });
          });
        });
      }, Promise.resolve())
        .then(function () {
          st.set(done + ' foto terunggah' + (noExif ? ' (' + noExif + ' tanpa timestamp kamera)' : '') + '. Memuat ulang…');
          location.reload();
        })
        .catch(function (err) {
          st.set(err.message + (done ? ' (' + done + ' foto sudah terunggah)' : ''), true);
          st.lock(false);
          e.target.value = '';
          if (done) setTimeout(function () { location.reload(); }, 2500);
        });
    });
  });

  // ---- galeri panitia: banyak foto, dikompres, dikirim satu per satu ----
  document.querySelectorAll('form.js-gallery').forEach(function (form) {
    form.dataset.fileField = 'foto';
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[type=file]');
      var files = input ? Array.prototype.slice.call(input.files) : [];
      if (!files.length || !form.checkValidity()) return;
      e.preventDefault();
      var st = statusUi(form);
      st.lock(true);
      var done = 0;
      files.reduce(function (p, file, i) {
        return p.then(function () {
          st.set('Mengunggah foto ' + (i + 1) + ' dari ' + files.length + '…');
          st.bar.style.width = '0';
          return render(file, null)
            .then(function (b) { return [b, 'foto.jpg']; }, function () { return [file, file.name]; })
            .then(function (b) { return send(form, baseFields(form), b[0], b[1], st); })
            .then(function () { done++; });
        });
      }, Promise.resolve())
        .then(function () { st.set(done + ' foto terunggah. Memuat ulang…'); location.reload(); })
        .catch(function (err) {
          st.set(err.message + (done ? ' (' + done + ' foto sudah terunggah)' : ''), true);
          st.lock(false);
          if (done) setTimeout(function () { location.reload(); }, 2500);
        });
    });
  });

  // ---- struk biaya ----
  document.querySelectorAll('form.js-receipt').forEach(function (form) {
    form.dataset.fileField = 'struk';
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[type=file]');
      var file = input && input.files[0];
      if (!file || !form.checkValidity()) return;
      e.preventDefault();
      var st = statusUi(form);
      st.lock(true);
      st.set('Memproses…');
      var prep = file.type === 'application/pdf'
        ? Promise.resolve([file, file.name])
        : render(file, null).then(function (b) { return [b, 'struk.jpg']; }, function () { return [file, file.name]; });
      prep.then(function (b) {
        st.set('Mengunggah…');
        return send(form, baseFields(form), b[0], b[1], st);
      }).then(function () {
        st.set('Tersimpan. Memuat ulang…');
        location.reload();
      }).catch(function (err) {
        st.set(err.message, true);
        st.lock(false);
      });
    });
  });
})();
